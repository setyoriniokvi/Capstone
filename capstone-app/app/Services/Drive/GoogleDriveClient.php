<?php

namespace App\Services\Drive;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Klien Google Drive API v3 berbasis service account (JWT bearer).
 * Folder tujuan sebaiknya berada di Shared Drive yang membagikan akses ke
 * email service account, karena service account tidak memiliki kuota My Drive.
 */
class GoogleDriveClient implements DriveGateway
{
    private const API = 'https://www.googleapis.com/drive/v3';

    private const UPLOAD = 'https://www.googleapis.com/upload/drive/v3';

    private ?array $credentials = null;

    public function __construct(private readonly ?string $credentialsPath) {}

    public function createFolder(string $name, ?string $parentId): string
    {
        $response = $this->http()->post(self::API.'/files?supportsAllDrives=true&fields=id', array_filter([
            'name' => $name,
            'mimeType' => 'application/vnd.google-apps.folder',
            'parents' => $parentId ? [$parentId] : null,
        ]));

        return $this->idFrom($response->throw()->json());
    }

    public function upload(string $folderId, string $name, string $mime, string $localPath): string
    {
        $session = $this->http()
            ->withHeaders(['X-Upload-Content-Type' => $mime, 'X-Upload-Content-Length' => (string) filesize($localPath)])
            ->post(self::UPLOAD.'/files?uploadType=resumable&supportsAllDrives=true&fields=id', [
                'name' => $name,
                'parents' => [$folderId],
            ])->throw();

        return $this->idFrom($this->sendContent($session->header('Location'), $mime, $localPath));
    }

    public function update(string $fileId, string $name, string $mime, string $localPath): string
    {
        $session = $this->http()
            ->withHeaders(['X-Upload-Content-Type' => $mime, 'X-Upload-Content-Length' => (string) filesize($localPath)])
            ->patch(self::UPLOAD."/files/{$fileId}?uploadType=resumable&supportsAllDrives=true&fields=id", ['name' => $name])
            ->throw();

        return $this->idFrom($this->sendContent($session->header('Location'), $mime, $localPath));
    }

    public function trash(string $fileId): void
    {
        $this->http()->patch(self::API."/files/{$fileId}?supportsAllDrives=true", ['trashed' => true])->throw();
    }

    public function delete(string $fileId): void
    {
        $this->http()->delete(self::API."/files/{$fileId}?supportsAllDrives=true")->throw();
    }

    public function shareWith(string $fileId, string $email): void
    {
        $this->http()->post(self::API."/files/{$fileId}/permissions?supportsAllDrives=true&sendNotificationEmail=false", [
            'type' => 'user',
            'role' => 'reader',
            'emailAddress' => $email,
        ])->throw();
    }

    private function sendContent(?string $location, string $mime, string $localPath): array
    {
        if (! $location) {
            throw new RuntimeException('Google Drive tidak mengembalikan sesi unggah.');
        }

        $stream = fopen($localPath, 'rb');
        try {
            return Http::withToken($this->token())
                ->timeout(300)
                ->withBody($stream, $mime)
                ->put($location)
                ->throw()
                ->json();
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    private function idFrom(?array $json): string
    {
        return $json['id'] ?? throw new RuntimeException('Respons Google Drive tidak berisi ID file.');
    }

    private function http(): PendingRequest
    {
        return Http::withToken($this->token())->acceptJson()->timeout(60);
    }

    private function token(): string
    {
        $credentials = $this->credentials();

        return Cache::remember('gdrive.token.'.md5($credentials['client_email']), 3000, function () use ($credentials) {
            $now = time();
            $header = $this->base64(json_encode(['alg' => 'RS256', 'typ' => 'JWT']));
            $claims = $this->base64(json_encode([
                'iss' => $credentials['client_email'],
                'scope' => 'https://www.googleapis.com/auth/drive',
                'aud' => $credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token',
                'iat' => $now,
                'exp' => $now + 3600,
            ]));

            if (! openssl_sign("{$header}.{$claims}", $signature, $credentials['private_key'], 'sha256WithRSAEncryption')) {
                throw new RuntimeException('Gagal menandatangani JWT service account.');
            }

            return Http::asForm()->post($credentials['token_uri'] ?? 'https://oauth2.googleapis.com/token', [
                'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
                'assertion' => "{$header}.{$claims}.".$this->base64($signature),
            ])->throw()->json('access_token');
        });
    }

    private function credentials(): array
    {
        if ($this->credentials) {
            return $this->credentials;
        }

        if (! $this->credentialsPath || ! is_readable($this->credentialsPath)) {
            throw new RuntimeException('File kredensial service account Google Drive tidak ditemukan.');
        }

        return $this->credentials = json_decode(file_get_contents($this->credentialsPath), true, flags: JSON_THROW_ON_ERROR);
    }

    private function base64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
