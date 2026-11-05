<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\Drive\DriveOAuth;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

/**
 * F33–F41 — mode OAuth Google Drive: admin menghubungkan / memutuskan akun Google yang menyimpan dokumen.
 * Persetujuan dilakukan di halaman Google; aplikasi hanya menerima kode lalu menukarnya dengan token.
 */
class DriveConnectionController extends Controller
{
    private const STATE_KEY = 'gdrive_oauth_state';

    public function connect(Request $request, DriveOAuth $oauth): RedirectResponse
    {
        if (config('capstone.drive.auth') !== 'oauth' || ! $oauth->configured()) {
            return redirect()->route('admin.documents.index')->withErrors(['drive' => 'Isi GOOGLE_DRIVE_AUTH=oauth, GOOGLE_DRIVE_CLIENT_ID, dan GOOGLE_DRIVE_CLIENT_SECRET di .env terlebih dahulu.']);
        }

        // state acak dicocokkan saat kembali dari Google (cegah CSRF pada callback)
        $state = Str::random(40);
        $request->session()->put(self::STATE_KEY, $state);

        return redirect()->away($oauth->authorizationUrl($state));
    }

    public function callback(Request $request, DriveOAuth $oauth): RedirectResponse
    {
        $expected = $request->session()->pull(self::STATE_KEY);
        if (! $expected || ! hash_equals($expected, (string) $request->query('state'))) {
            return redirect()->route('admin.documents.index')->withErrors(['drive' => 'Sesi menghubungkan Google Drive tidak valid atau kedaluwarsa. Coba lagi.']);
        }
        if ($request->filled('error') || ! $request->filled('code')) {
            return redirect()->route('admin.documents.index')->withErrors(['drive' => 'Menghubungkan Google Drive dibatalkan.']);
        }

        try {
            $email = $oauth->connect((string) $request->query('code'));
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('admin.documents.index')->withErrors(['drive' => 'Gagal menghubungkan Google Drive: '.Str::limit($e->getMessage(), 300)]);
        }

        ActivityLog::record('drive_connect', null, ['email' => $email]);

        return redirect()->route('admin.documents.index')->with('status', "Google Drive terhubung dengan {$email}.");
    }

    public function destroy(DriveOAuth $oauth): RedirectResponse
    {
        $oauth->disconnect();
        ActivityLog::record('drive_disconnect');

        return redirect()->route('admin.documents.index')->with('status', 'Google Drive diputuskan. Sinkronisasi berikutnya akan gagal sampai akun dihubungkan lagi.');
    }
}
