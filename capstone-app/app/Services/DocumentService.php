<?php

namespace App\Services;

use App\Jobs\SyncDocumentVersion;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\DriveOperation;
use App\Models\Milestone;
use App\Models\Team;
use App\Models\User;
use App\Services\Drive\DriveGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Psr\Http\Message\StreamInterface;
use Throwable;

/**
 * F26, F36–F41 — unggah, revisi, hapus, dan buka dokumen milestone. Database adalah sumber kebenaran; pengiriman ke
 * Google Drive dijalankan di antrean (job SyncDocumentVersion → DocumentDriveSync).
 */
class DocumentService
{
    /** @deprecated pakai DocumentVersion::DISK */
    public const DISK = DocumentVersion::DISK;

    public function __construct(private readonly DriveGateway $drive) {}

    public function driveEnabled(): bool
    {
        return (bool) config('capstone.drive.enabled');
    }

    /**
     * Unggah baru atau revisi. Permintaan dengan idempotency key yang sama
     * mengembalikan versi yang sudah ada (F41 — cegah duplikat akibat kirim ulang).
     */
    public function upload(Team $team, Milestone $milestone, User $owner, UploadedFile $file, string $idempotencyKey): DocumentVersion
    {
        if ($existing = DocumentVersion::query()->where('idempotency_key', $idempotencyKey)->first()) {
            return $existing;
        }

        if (! $milestone->hasStarted()) {
            throw ValidationException::withMessages(['file' => 'Pengumpulan milestone ini belum dibuka.']);
        }
        // Setelah deadline pengumpulan ditutup (Overdue): unggah maupun unggah ulang ditolak.
        if ($milestone->isPast()) {
            throw ValidationException::withMessages(['file' => 'Deadline '.$milestone->name.' sudah lewat (Overdue); dokumen tidak dapat diunggah lagi.']);
        }

        $version = DB::transaction(function () use ($team, $milestone, $owner, $file, $idempotencyKey) {
            // Satu dokumen per tim per milestone: unggahan anggota mana pun menjadi revisi dokumen tim.
            // owner_id = pengunggah pertama (pemilik folder Drive); pengunggah tiap versi ada di uploaded_by.
            $document = Document::query()->lockForUpdate()
                ->where('team_id', $team->id)->where('milestone_id', $milestone->id)
                ->orderByRaw("state = 'aktif' desc")->latest('updated_at')->first()
                ?? Document::create([
                    'team_id' => $team->id, 'milestone_id' => $milestone->id, 'owner_id' => $owner->id,
                    'owner_email' => $owner->email, 'document_type' => $milestone->document_type,
                ]);

            // Dokumen yang sebelumnya dipindah ke Trash diunggah sebagai file baru di Drive.
            if ($document->state !== 'aktif' && $document->drive_file_id) {
                $document->forceFill(['drive_file_id' => null, 'current_version_id' => null])->save();
            }

            $revision = (int) $document->versions()->max('revision_no') + 1;
            $extension = strtolower($file->getClientOriginalExtension());
            // Nama file otomatis "Namadokumen_Namatim.ext" (nama milestone & nama tim), dipakai saat diunduh & di Drive.
            // Tiap revisi disimpan di foldernya sendiri agar revisi lama tidak tertimpa walau namanya sama.
            $storedName = self::fileName($milestone->name, $team->name, $extension);
            $directory = "documents/{$team->period_id}/tim-{$team->id}/{$document->id}/r{$revision}";
            $path = $file->storeAs($directory, $storedName, DocumentVersion::DISK);

            $version = $document->versions()->create([
                'revision_no' => $revision,
                'original_name' => $file->getClientOriginalName(),
                'stored_name' => $storedName,
                'mime' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
                'local_path' => $path,
                'checksum' => hash_file('sha256', $file->getRealPath()),
                'idempotency_key' => $idempotencyKey,
                'sync_action' => $document->drive_file_id ? 'update' : 'create',
                'uploaded_by' => $owner->id,
            ]);

            $document->update([
                'state' => 'aktif',
                'review_status' => 'dikumpulkan',
            ]);

            // Tanpa Drive, salinan lokal adalah satu-satunya penyimpanan.
            if (! $this->driveEnabled()) {
                $document->update(['current_version_id' => $version->id, 'current_revision' => $revision]);
            }

            return $version;
        });

        ActivityLog::record('upload_document', $version->document, ['revision' => $version->revision_no]);

        if ($this->driveEnabled()) {
            SyncDocumentVersion::dispatch($version->id)->afterCommit();
        }

        return $version;
    }

    /** F40 — percobaan ulang manual untuk versi yang gagal. */
    public function retry(DocumentVersion $version): void
    {
        if (! $this->driveEnabled()) {
            throw ValidationException::withMessages(['sync' => 'Integrasi Google Drive belum diaktifkan.']);
        }
        if (! in_array($version->sync_status, ['gagal', 'menunggu'], true)) {
            throw ValidationException::withMessages(['sync' => 'Versi ini tidak perlu disinkronkan ulang.']);
        }

        $version->update(['sync_status' => 'menunggu', 'sync_attempts' => 0, 'sync_error' => null]);
        SyncDocumentVersion::dispatch($version->id);
    }

    /**
     * F38 — penghapusan konsisten dengan Drive. Standar: pindah ke Trash.
     * Permanen hanya untuk admin. Status database berubah hanya bila Drive berhasil.
     */
    public function delete(Document $document, User $actor, bool $permanent = false): void
    {
        if ($permanent && ! $actor->isAdmin()) {
            throw ValidationException::withMessages(['document' => 'Penghapusan permanen hanya untuk admin.']);
        }

        if ($document->drive_file_id && $this->driveEnabled()) {
            $action = $permanent ? 'delete' : 'trash';
            try {
                $permanent ? $this->drive->delete($document->drive_file_id) : $this->drive->trash($document->drive_file_id);
                DriveOperation::record($document, null, $action, 'berhasil', null, $actor);
            } catch (Throwable $e) {
                DriveOperation::record($document, null, $action, 'gagal', $e->getMessage(), $actor);

                throw ValidationException::withMessages(['document' => 'Gagal menghapus file di Google Drive. Status dokumen tidak diubah, silakan coba lagi.']);
            }
        }

        if ($permanent) {
            foreach ($document->versions as $version) {
                Storage::disk(DocumentVersion::DISK)->delete($version->local_path);
            }
            ActivityLog::record('delete_document_permanent', $document);
            $document->delete();

            return;
        }

        $document->update(['state' => 'trash']);
        ActivityLog::record('trash_document', $document);
    }

    /**
     * Isi file sebuah revisi untuk dibuka di aplikasi: salinan lokal bila masih ada (belum / gagal tersinkron, atau
     * Drive nonaktif); bila sudah dipindah ke Drive, diambil dari Drive lewat server (hak akses tetap dicek aplikasi).
     * null = revisi lama yang sudah tertimpa revisi lebih baru di Drive.
     *
     * @return array{local: string}|array{drive: string}|null
     */
    public function source(DocumentVersion $version): ?array
    {
        if ($version->local_path && Storage::disk(DocumentVersion::DISK)->exists($version->local_path)) {
            return ['local' => $version->local_path];
        }
        $document = $version->document;

        return $document->drive_file_id && (int) $document->current_version_id === (int) $version->id ? ['drive' => $document->drive_file_id] : null;
    }

    /** Stream isi file dari Google Drive (dipakai DocumentFileController). */
    public function driveStream(string $fileId): StreamInterface
    {
        return $this->drive->download($fileId);
    }

    /** "Namadokumen_Namatim.ext" tanpa karakter yang tidak boleh ada di nama file (/ \ : * ? " < > |). */
    public static function fileName(string $document, string $team, string $extension): string
    {
        $clean = fn (string $part) => trim(preg_replace('/\s+/u', ' ', preg_replace('#[/\\\\:*?"<>|\x00-\x1F]#u', '', $part)));

        return $clean($document).'_'.$clean($team).($extension !== '' ? '.'.$extension : '');
    }
}
