<?php

namespace App\Http\Controllers\Student;

use App\Services\QrService;
use Illuminate\Http\Response;
use Illuminate\View\View;

/** F30 — QR Code tim untuk ditampilkan atau diunduh anggota tim. */
class QrController extends StudentController
{
    public function show(QrService $qr): View
    {
        $team = $this->team();
        $token = $team?->status === 'diterima' ? $qr->ensureToken($team) : null;

        return view('mahasiswa.qr', ['team' => $team, 'token' => $token, 'qr' => $qr]);
    }

    public function download(QrService $qr): Response
    {
        $team = $this->teamOrFail();
        $token = $qr->ensureToken($team);

        return response($qr->svg($token, true), 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => "attachment; filename=\"qr-{$team->code}.svg\"",
        ]);
    }
}
