<?php

namespace App\Services\Drive;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * F33–F41 — mode OAuth Google Drive (GOOGLE_DRIVE_AUTH=oauth): admin menghubungkan akun Google-nya sendiri
 * (persetujuan di halaman Google, aplikasi tidak pernah melihat password). Refresh token disimpan terenkripsi
 * (APP_KEY) di storage/app/private/google-drive-oauth.json — di luar git — lalu dipakai untuk meminta access token.
 */
class DriveOAuth
{
    private const AUTH_URL = 'https://accounts.google.com/o/oauth2/v2/auth';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const REVOKE_URL = 'https://oauth2.googleapis.com/revoke';

    // Akses penuh Drive diperlukan karena folder tujuan dibuat admin sendiri (bukan oleh aplikasi); email untuk menampilkan akun terhubung.
    private const SCOPES = ['openid', 'email', 'https://www.googleapis.com/auth/drive'];

    private const FILE = 'google-drive-oauth.json';

    private const CACHE_KEY = 'gdrive.oauth.access_token';

    /** Client ID & secret OAuth sudah diisi di .env. */
    public function configured(): bool
    {
        return filled(config('capstone.drive.oauth.client_id')) && filled(config('capstone.drive.oauth.client_secret'));
    }

    public function redirectUri(): string
    {
        return config('capstone.drive.oauth.redirect_uri') ?: route('admin.drive.callback');
    }

    /** URL halaman persetujuan Google. access_type=offline + prompt=consent agar Google selalu memberi refresh token. */
    public function authorizationUrl(string $state): string
    {
        return self::AUTH_URL.'?'.http_build_query([
            'client_id' => config('capstone.drive.oauth.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'response_type' => 'code',
            'scope' => implode(' ', self::SCOPES),
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);
    }

    /** Tukar kode dari Google dengan token, lalu simpan refresh token. @return string email akun yang terhubung */
    public function connect(string $code): string
    {
        $json = Http::asForm()->post(self::TOKEN_URL, [
            'code' => $code,
            'client_id' => config('capstone.drive.oauth.client_id'),
            'client_secret' => config('capstone.drive.oauth.client_secret'),
            'redirect_uri' => $this->redirectUri(),
            'grant_type' => 'authorization_code',
        ])->throw()->json();

        if (empty($json['refresh_token'])) {
            throw new RuntimeException('Google tidak mengirim refresh token. Cabut akses aplikasi di myaccount.google.com/permissions lalu hubungkan ulang.');
        }
        if (! str_contains($json['scope'] ?? '', 'auth/drive')) {
            throw new RuntimeException('Akses Google Drive tidak diberikan. Centang izin Google Drive saat menghubungkan akun.');
        }

        $email = $this->emailFrom($json['id_token'] ?? null);
        $this->write(['refresh_token' => $json['refresh_token'], 'email' => $email, 'connected_at' => now()->toIso8601String()]);
        Cache::put(self::CACHE_KEY, $json['access_token'], max(60, (int) ($json['expires_in'] ?? 3600) - 120));

        return $email ?? 'akun Google';
    }

    /** @return array{email: ?string, connected_at: ?Carbon}|null */
    public function status(): ?array
    {
        $data = $this->read();

        return $data ? ['email' => $data['email'] ?? null, 'connected_at' => isset($data['connected_at']) ? Carbon::parse($data['connected_at']) : null] : null;
    }

    /** Access token dari refresh token (di-cache sampai mendekati kedaluwarsa). */
    public function accessToken(): string
    {
        if ($token = Cache::get(self::CACHE_KEY)) {
            return $token;
        }

        $refresh = $this->read()['refresh_token'] ?? throw new RuntimeException('Akun Google Drive belum dihubungkan. Hubungkan di menu Sinkronisasi Dokumen.');
        $response = Http::asForm()->post(self::TOKEN_URL, [
            'refresh_token' => $refresh,
            'client_id' => config('capstone.drive.oauth.client_id'),
            'client_secret' => config('capstone.drive.oauth.client_secret'),
            'grant_type' => 'refresh_token',
        ]);
        if ($response->json('error') === 'invalid_grant') {
            throw new RuntimeException('Koneksi Google Drive kedaluwarsa atau dicabut. Hubungkan ulang akun di menu Sinkronisasi Dokumen.');
        }

        $json = $response->throw()->json();
        Cache::put(self::CACHE_KEY, $json['access_token'], max(60, (int) ($json['expires_in'] ?? 3600) - 120));

        return $json['access_token'];
    }

    /** Putuskan: cabut token di Google (bila bisa) dan hapus token tersimpan. */
    public function disconnect(): void
    {
        if ($refresh = $this->read()['refresh_token'] ?? null) {
            try {
                Http::asForm()->timeout(10)->post(self::REVOKE_URL, ['token' => $refresh]);
            } catch (Throwable) {
                // Token tetap dihapus dari aplikasi walau pencabutan di Google gagal.
            }
        }
        Storage::disk('local')->delete(self::FILE);
        Cache::forget(self::CACHE_KEY);
    }

    /** Email dari id_token (diterima langsung dari endpoint token Google lewat HTTPS, jadi cukup dibaca payload-nya). */
    private function emailFrom(?string $idToken): ?string
    {
        $payload = $idToken ? explode('.', $idToken)[1] ?? null : null;
        $claims = $payload ? json_decode(base64_decode(strtr($payload, '-_', '+/')), true) : null;

        return $claims['email'] ?? null;
    }

    private function read(): ?array
    {
        if (! Storage::disk('local')->exists(self::FILE)) {
            return null;
        }
        try {
            return json_decode(Crypt::decryptString(Storage::disk('local')->get(self::FILE)), true, flags: JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return null; // APP_KEY berganti → token tidak terbaca, perlu dihubungkan ulang
        }
    }

    private function write(array $data): void
    {
        Storage::disk('local')->put(self::FILE, Crypt::encryptString(json_encode($data)));
    }
}
