<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentVersion;
use App\Models\DriveOperation;
use App\Services\Drive\DriveGateway;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * F33 / F35 / F40 — kirim satu revisi dokumen ke Google Drive (dipanggil job SyncDocumentVersion). Status Drive hanya
 * "berhasil" setelah Google Drive mengonfirmasi operasi; setelah itu salinan lokal dilepas (server hanya tempat transit).
 */
class DocumentDriveSync
{
    public function __construct(private readonly DriveGateway $drive) {}

    public function sync(DocumentVersion $version): void
    {
        $document = $version->document()->with('team.period', 'owner', 'milestone')->first();

        // Hanya revisi terbaru yang disinkronkan; versi lama yang tertunda dilewati
        // agar tidak menimpa konten yang lebih baru.
        $latest = (int) $document->versions()->max('revision_no');
        if ($version->revision_no < $latest) {
            $version->update(['sync_status' => 'dilewati', 'sync_error' => "Digantikan revisi {$latest}."]);

            return;
        }

        $version->update(['sync_status' => 'proses', 'sync_attempts' => $version->sync_attempts + 1]);
        $localPath = Storage::disk(DocumentVersion::DISK)->path($version->local_path);

        try {
            // Nama file di Drive = nama file dokumen ("Namadokumen_Namatim.ext"); revisi memperbarui file yang sama.
            if ($document->drive_file_id) {
                $action = 'update';
                $this->drive->update($document->drive_file_id, $version->stored_name, $version->mime, $localPath);
            } else {
                $action = 'create';
                $folderId = $this->milestoneFolder($document);
                $fileId = $this->drive->upload($folderId, $version->stored_name, $version->mime, $localPath);
                $document->update(['drive_file_id' => $fileId, 'drive_folder_id' => $folderId]);

                if (config('capstone.drive.share_with_owner')) {
                    $this->shareWithOwner($document, $version, $fileId);
                }
            }
        } catch (Throwable $e) {
            $version->update(['sync_status' => 'gagal', 'sync_error' => Str::limit($e->getMessage(), 1000)]);
            DriveOperation::record($document, $version, $action ?? 'create', 'gagal', $e->getMessage());

            throw $e;
        }

        $version->update(['sync_status' => 'berhasil', 'sync_action' => $action, 'sync_error' => null, 'synced_at' => now()]);
        $document->update(['current_version_id' => $version->id, 'current_revision' => $version->revision_no]);
        DriveOperation::record($document, $version, $action, 'berhasil');
        $this->releaseLocalCopies($document, $version);
    }

    /**
     * Setelah Drive mengonfirmasi, salinan lokal revisi ini dan revisi sebelumnya dihapus (isi Drive sudah tertimpa
     * revisi ini, revisi lama tidak disimpan). Revisi lama yang masih menunggu / gagal ditandai "dilewati" agar tidak
     * disinkronkan lagi. Revisi yang lebih baru (belum tersinkron) tetap disimpan.
     */
    private function releaseLocalCopies(Document $document, DocumentVersion $version): void
    {
        $document->versions()->where('revision_no', '<=', $version->revision_no)->get()->each(function (DocumentVersion $old) use ($version) {
            if ($old->local_path) {
                Storage::disk(DocumentVersion::DISK)->deleteDirectory(dirname($old->local_path));
            }
            if ($old->isNot($version) && in_array($old->sync_status, ['menunggu', 'proses', 'gagal'], true)) {
                $old->update(['sync_status' => 'dilewati', 'sync_error' => "Digantikan revisi {$version->revision_no}."]);
            }
        });
    }

    /**
     * Bagikan akses baca ke pemilik dokumen. Hanya pelengkap: bila gagal (mis. email bukan akun Google), file tetap
     * tersimpan di Drive dan sinkronisasi tetap berhasil; kegagalannya dicatat di log operasi Drive.
     */
    private function shareWithOwner(Document $document, DocumentVersion $version, string $fileId): void
    {
        try {
            $this->drive->shareWith($fileId, $document->owner_email);
            DriveOperation::record($document, $version, 'share', 'berhasil');
        } catch (Throwable $e) {
            DriveOperation::record($document, $version, 'share', 'gagal', $e->getMessage());
        }
    }

    /** F35 — dokumen diunggah ke folder Google Drive Database milestone dokumennya (diatur admin di halaman periode). */
    private function milestoneFolder(Document $document): string
    {
        $milestone = $document->milestone;

        return $milestone?->drive_folder_id
            ?? throw new RuntimeException('Folder Google Drive Database untuk milestone '.($milestone?->name ?? 'ini').' belum diisi.');
    }
}
