<?php

namespace App\Http\Controllers\Student;

use App\Models\ActivityLog;
use App\Services\QrService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** F30 — QR Code penilaian pameran tim untuk ditampilkan atau diunduh (PNG) anggota tim. */
class QrController extends StudentController
{
    public function show(QrService $qr): View
    {
        $team = $this->team();
        $token = $team?->status === 'diterima' ? $qr->ensureToken($team) : null;

        return view('mahasiswa.qr', ['team' => $team, 'token' => $token, 'qr' => $qr]);
    }

    /** Buat QR baru bila QR lama bermasalah; token lama dicabut sehingga QR lama tidak berlaku lagi. */
    public function regenerate(QrService $qr): RedirectResponse
    {
        $team = $this->teamOrFail();
        $qr->regenerate($team);
        ActivityLog::record('regenerate_qr', $team);

        return redirect()->route('mahasiswa.qr.show')->with('status', 'QR Code baru dibuat. QR lama tidak berlaku lagi — unduh ulang bila sudah dicetak.');
    }

    public function download(QrService $qr): Response
    {
        $team = $this->teamOrFail();
        $token = $qr->ensureToken($team);

        return response($qr->png($token), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => "attachment; filename=\"qr-pameran-{$team->code}.png\"",
        ]);
    }
}
