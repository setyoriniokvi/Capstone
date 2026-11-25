<?php

use App\Models\Document;
use App\Models\PeerReview;
use App\Models\Period;
use App\Notifications\SystemNotice;
use App\Services\Reminders\ReminderSender;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

/*
| F48 — notifikasi deadline yang mendekat bagi mahasiswa (dalam aplikasi).
| Pengingat dosen: REMINDER_SENDER=app → capstone:send-reminders (WhatsApp Fonnte & email); n8n → workflow n8n.
| Jalankan scheduler di server: * * * * * php artisan schedule:run
*/
Artisan::command('capstone:notify-deadlines {--hours=48}', function () {
    $hours = (int) $this->option('hours');
    $sent = 0;

    // Beberapa periode boleh aktif bersamaan (tiap prodi berjalan paralel).
    foreach (Period::query()->where('is_active', true)->get() as $period) {
        $milestones = $period->milestones()->whereIn('kind', ['dokumen', 'peer_review'])
            ->whereBetween('due_at', [now(), now()->addHours($hours)])->get();

        foreach ($milestones as $milestone) {
            $teams = $period->teams()->where('status', 'diterima')->with('activeUsers')->get();
            foreach ($teams as $team) {
                foreach ($team->activeUsers as $student) {
                    $done = $milestone->kind === 'dokumen'
                        ? Document::query()->where('team_id', $team->id)->where('milestone_id', $milestone->id)->where('state', 'aktif')->exists()
                        : PeerReview::query()->where('reviewer_id', $student->id)->where('team_id', $team->id)->exists();

                    // Satu kali per mahasiswa per milestone.
                    if ($done || ! Cache::add("deadline-notice:{$milestone->id}:{$student->id}", true, now()->addDays(30))) {
                        continue;
                    }

                    SystemNotice::sendTo([$student], 'Deadline mendekat: '.$milestone->name,
                        'Batas waktu '.$milestone->due_at->translatedFormat('l, j F Y H:i').' WIB ('.$milestone->due_at->diffForHumans().').',
                        route($milestone->kind === 'dokumen' ? 'mahasiswa.documents.index' : 'mahasiswa.peer.index'));
                    $sent++;
                }
            }
        }
    }

    $this->info("{$sent} notifikasi deadline dikirim.");
})->purpose('Kirim notifikasi deadline milestone yang mendekat ke mahasiswa');

Schedule::command('capstone:notify-deadlines')->hourly()->withoutOverlapping();

// F50–F55 — antrekan pengingat dosen yang mencapai ambang waktu lalu kirim (WhatsApp lewat Fonnte, email lewat SMTP).
Artisan::command('capstone:send-reminders {--limit=50}', function (ReminderSender $sender) {
    if (! $sender->enabled()) {
        $this->warn('REMINDER_SENDER bukan "app"; pengingat dikirim oleh workflow n8n.');

        return;
    }
    $result = $sender->sendQueued((int) $this->option('limit'));
    $this->info("{$result['sent']} pengingat terkirim, {$result['failed']} gagal.");
})->purpose('Kirim pengingat dosen (WhatsApp Fonnte & email) yang sudah waktunya');

Schedule::command('capstone:send-reminders')->everyFiveMinutes()->withoutOverlapping()
    ->when(fn () => config('capstone.automation.sender') === 'app');

// Master Data: catat data lama yang belum punya log (aman diulang; migration sudah menjalankannya sekali).
Artisan::command('capstone:backfill-master-data', function () {
    $this->info('Log ditambahkan: '.(new \App\Services\MasterData\MasterDataLogger)->backfill());
})->purpose('Isi Master Data dengan data yang sudah ada di database');

/*
| Google Drive: server hanya tempat transit. Hapus salinan lokal dokumen yang sudah tersinkron ke Drive (revisi yang
| sudah ada di Drive & revisi sebelumnya). Dipakai sekali untuk file lama sebelum aturan ini; unggahan baru dibersihkan
| otomatis setelah sinkron berhasil. --dry-run = hanya hitung.
*/
Artisan::command('capstone:prune-local-documents {--dry-run}', function () {
    $disk = Storage::disk(\App\Services\DocumentService::DISK);
    $files = 0;
    $bytes = 0;

    Document::query()->whereNotNull('drive_file_id')->whereNotNull('current_version_id')->with('currentVersion')
        ->each(function (Document $document) use ($disk, &$files, &$bytes) {
            $current = $document->currentVersion;
            if (! $current || $current->sync_status !== 'berhasil') {
                return;
            }
            foreach ($document->versions()->where('revision_no', '<=', $current->revision_no)->get() as $version) {
                if ($version->local_path && $disk->exists($version->local_path)) {
                    $files++;
                    $bytes += $disk->size($version->local_path);
                    if (! $this->option('dry-run')) {
                        $disk->deleteDirectory(dirname($version->local_path));
                    }
                }
            }
        });

    $this->info(($this->option('dry-run') ? 'Akan dihapus: ' : 'Dihapus: ')."{$files} file (".round($bytes / 1048576, 2).' MB).');
})->purpose('Hapus salinan lokal dokumen yang sudah tersinkron ke Google Drive');
