<?php

namespace App\Http\Controllers;

use App\Services\QrService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * F31 / F32 / F58 / F59 / F61 — memindai QR tim.
 * Rute ini berada di balik middleware auth; dosen yang belum login diarahkan
 * ke halaman login lalu kembali ke sini (intended URL).
 */
class QrResolveController extends Controller
{
    public function __invoke(Request $request, string $token, QrService $qr): Response
    {
        $user = $request->user();
        $result = $qr->resolve($token);

        if ($result['status'] !== 'ok') {
            return response()->view('shared.qr-result', ['result' => $result], $result['status'] === 'unknown' ? 404 : 410);
        }

        $team = $result['team'];

        if ($user->isAdmin()) {
            return redirect()->route('admin.teams.show', $team);
        }

        if (! $user->isLecturer()) {
            return response()->view('shared.qr-result', ['result' => [
                'status' => 'forbidden', 'team' => $team,
                'message' => 'QR Code ini hanya untuk penilaian oleh dosen pembimbing atau penguji.',
            ]], 403);
        }

        if (! $team->lecturerRole($user)) {
            return response()->view('shared.qr-result', ['result' => [
                'status' => 'forbidden', 'team' => $team,
                'message' => 'Anda bukan pembimbing atau penguji tim ini, sehingga tidak dapat menilai.',
            ]], 403);
        }

        $components = $qr->activeComponentsFor($team, $user);

        if ($components->isEmpty()) {
            return response()->view('shared.qr-result', ['result' => [
                'status' => 'no_stage', 'team' => $team,
                'message' => 'Tidak ada tahap penilaian aktif yang menjadi kewenangan Anda untuk tim ini saat ini.',
            ]], 409);
        }

        if ($components->count() === 1) {
            return redirect()->route('dosen.assessments.edit', [$team, $components->first(), 'via' => 'qr']);
        }

        return response()->view('shared.qr-result', ['result' => ['status' => 'choose', 'team' => $team, 'message' => ''], 'components' => $components]);
    }
}
