<?php

namespace App\Jobs;

use App\Models\DocumentVersion;
use App\Services\DocumentService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

/** F33 / F40 — sinkronisasi otomatis ke Google Drive dengan percobaan ulang. */
class SyncDocumentVersion implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries;

    public function __construct(public int $versionId)
    {
        $this->tries = (int) config('capstone.drive.max_attempts', 3);
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    /** Satu dokumen tidak disinkronkan paralel agar tidak tercipta file ganda (F41). */
    public function middleware(): array
    {
        $documentId = DocumentVersion::query()->whereKey($this->versionId)->value('document_id');

        return [(new WithoutOverlapping("document-sync-{$documentId}"))->releaseAfter(30)->expireAfter(600)];
    }

    public function handle(DocumentService $documents): void
    {
        $version = DocumentVersion::query()->find($this->versionId);

        if (! $version || in_array($version->sync_status, ['berhasil', 'dilewati'], true)) {
            return;
        }

        $documents->sync($version);
    }
}
