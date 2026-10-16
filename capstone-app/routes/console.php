<?php

use App\Models\Document;
use App\Models\PeerReview;
use App\Models\Period;
use App\Notifications\SystemNotice;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;

/*
| F48 — notifikasi tenggat yang mendekat bagi mahasiswa (dalam aplikasi).
| Pengingat dosen dikirim lewat workflow n8n (lihat ReminderService).
| Jalankan scheduler di server: * * * * * php artisan schedule:run
*/
Artisan::command('capstone:notify-deadlines {--hours=48}', function () {
    $period = Period::active();
    if (! $period) {
        return;
    }

    $hours = (int) $this->option('hours');
    $sent = 0;
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

                SystemNotice::sendTo([$student], 'Tenggat mendekat: '.$milestone->name,
                    'Batas waktu '.$milestone->due_at->translatedFormat('l, j F Y H:i').' WIB ('.$milestone->due_at->diffForHumans().').',
                    route($milestone->kind === 'dokumen' ? 'mahasiswa.documents.index' : 'mahasiswa.peer.index'));
                $sent++;
            }
        }
    }

    $this->info("{$sent} notifikasi tenggat dikirim.");
})->purpose('Kirim notifikasi tenggat milestone yang mendekat ke mahasiswa');

Schedule::command('capstone:notify-deadlines')->hourly()->withoutOverlapping();
