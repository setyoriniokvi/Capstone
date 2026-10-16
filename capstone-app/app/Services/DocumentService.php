<?php

namespace App\Services;

use App\Jobs\SyncDocumentVersion;
use App\Models\ActivityLog;
use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\DriveOperation;
use App\Models\Milestone;
use App\Models\StudentDriveFolder;
use App\Models\Team;
use App\Models\User;
use App\Services\Drive\DriveGateway;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * F26, F33–F41 — unggah, revisi, hapus, dan sinkronisasi dokumen milestone.
 * Database adalah sumber kebenaran; status Drive hanya "berhasil" setelah
 * Google Drive mengonfirmasi operasi.
 */
class DocumentService
{
    public const DISK = 'local';

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

        $version = DB::transaction(function () use ($team, $milestone, $owner, $file, $idempotencyKey) {
            $document = Document::query()->lockForUpdate()->firstOrCreate(
                ['team_id' => $team->id, 'milestone_id' => $milestone->id, 'owner_id' => $owner->id],
                ['owner_email' => $owner->email, 'document_type' => $milestone->document_type]
            );

            // Dokumen yang sebelumnya dipindah ke Trash diunggah sebagai file baru di Drive.
            if ($document->state !== 'aktif' && $document->drive_file_id) {
                $document->forceFill(['drive_file_id' => null, 'current_version_id' => null])->save();
            }

            $revision = (int) $document->versions()->max('revision_no') + 1;
            $extension = strtolower($file->getClientOriginalExtension());
            $storedName = sprintf('%s_%s_%s_r%d.%s', $owner->nim ?? $owner->id, Str::slug($owner->name), $milestone->document_type, $revision, $extension);
            $directory = "documents/{$team->period_id}/{$team->code}/{$owner->id}";
            $path = $file->storeAs($directory, $storedName, self::DISK);

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
                'is_late' => now()->greaterThan($milestone->due_at),
                'owner_email' => $owner->email,
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
            try {
                $permanent ? $this->drive->delete($document->drive_file_id) : $this->drive->trash($document->drive_file_id);
                $this->log($document, null, $permanent ? 'delete' : 'trash', 'berhasil', null, $actor);
            } catch (Throwable $e) {
                $this->log($document, null, $permanent ? 'delete' : 'trash', 'gagal', $e->getMessage(), $actor);

                throw ValidationException::withMessages(['document' => 'Gagal menghapus file di Google Drive. Status dokumen tidak diubah, silakan coba lagi.']);
            }
        }

        if ($permanent) {
            foreach ($document->versions as $version) {
                Storage::disk(self::DISK)->delete($version->local_path);
            }
            ActivityLog::record('delete_document_permanent', $document);
            $document->delete();

            return;
        }

        $document->update(['state' => 'trash']);
        ActivityLog::record('trash_document', $document);
    }

    /** Dipanggil job: jalankan satu versi ke Drive. */
    public function sync(DocumentVersion $version): void
    {
        $document = $version->document()->with('team.period', 'owner')->first();

        // Hanya revisi terbaru yang disinkronkan; versi lama yang tertunda dilewati
        // agar tidak menimpa konten yang lebih baru.
        $latest = (int) $document->versions()->max('revision_no');
        if ($version->revision_no < $latest) {
            $version->update(['sync_status' => 'dilewati', 'sync_error' => "Digantikan revisi {$latest}."]);

            return;
        }

        $version->update(['sync_status' => 'proses', 'sync_attempts' => $version->sync_attempts + 1]);
        $localPath = Storage::disk(self::DISK)->path($version->local_path);

        try {
            if ($document->drive_file_id) {
                $action = 'update';
                $this->drive->update($document->drive_file_id, $version->stored_name, $version->mime, $localPath);
            } else {
                $action = 'create';
                $folderId = $this->studentFolder($document);
                $fileId = $this->drive->upload($folderId, $version->stored_name, $version->mime, $localPath);
                $document->update(['drive_file_id' => $fileId, 'drive_folder_id' => $folderId]);

                if (config('capstone.drive.share_with_owner')) {
                    $this->drive->shareWith($fileId, $document->owner_email);
                }
            }
        } catch (Throwable $e) {
            $version->update(['sync_status' => 'gagal', 'sync_error' => Str::limit($e->getMessage(), 1000)]);
            $this->log($document, $version, $action ?? 'create', 'gagal', $e->getMessage());

            throw $e;
        }

        $version->update(['sync_status' => 'berhasil', 'sync_action' => $action, 'sync_error' => null, 'synced_at' => now()]);
        $document->update(['current_version_id' => $version->id, 'current_revision' => $version->revision_no]);
        $this->log($document, $version, $action, 'berhasil');
    }

    /** F35 — folder per mahasiswa, dibuat saat unggahan pertama, ID disimpan. */
    private function studentFolder(Document $document): string
    {
        $owner = $document->owner;
        $period = $document->team->period;

        $existing = StudentDriveFolder::query()->where('user_id', $owner->id)->where('period_id', $period->id)->first();
        if ($existing) {
            return $existing->folder_id;
        }

        $parent = $period->driveFolders()->where('prodi_code', $owner->prodi_code)->value('folder_id')
            ?? $period->drive_root_folder_id;

        if (! $parent) {
            throw new RuntimeException("Folder Drive untuk prodi {$owner->prodi_code} pada periode ini belum dikonfigurasi.");
        }

        $name = trim(($owner->nim ? $owner->nim.' - ' : '').$owner->name);
        $folderId = $this->drive->createFolder($name, $parent);

        StudentDriveFolder::create([
            'user_id' => $owner->id,
            'period_id' => $period->id,
            'folder_id' => $folderId,
            'folder_name' => $name,
        ]);

        return $folderId;
    }

    private function log(Document $document, ?DocumentVersion $version, string $action, string $status, ?string $error = null, ?User $actor = null): void
    {
        DriveOperation::create([
            'document_id' => $document->id,
            'document_version_id' => $version?->id,
            'action' => $action,
            'status' => $status,
            'error' => $error ? Str::limit($error, 1000) : null,
            'actor_id' => $actor?->id,
        ]);
    }
}
