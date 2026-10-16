<?php

namespace App\Services;

use App\Models\Team;
use App\Models\TeamQrToken;
use App\Models\User;
use chillerlan\QRCode\Common\EccLevel;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * F30–F32, F58–F61 — QR code tim.
 * QR hanya memuat token acak; identitas tim selalu diverifikasi dari tabel
 * team_qr_tokens di backend, bukan dari teks pada QR.
 */
class QrService
{
    public function ensureToken(Team $team): TeamQrToken
    {
        return $team->activeQrToken() ?? $team->qrTokens()->create(['token' => Str::random(48)]);
    }

    /** Token lama dicabut sehingga QR yang sudah tersebar menjadi tidak berlaku. */
    public function regenerate(Team $team): TeamQrToken
    {
        $team->qrTokens()->whereNull('revoked_at')->update(['revoked_at' => now()]);

        return $team->qrTokens()->create(['token' => Str::random(48)]);
    }

    public function url(TeamQrToken $token): string
    {
        return route('qr.resolve', $token->token);
    }

    public function svg(TeamQrToken $token, bool $standalone = false): string
    {
        $options = new QROptions([
            'outputBase64' => false,
            'eccLevel' => EccLevel::M,
            'addQuietzone' => true,
            'svgAddXmlHeader' => $standalone,
            'drawLightModules' => false,
            'connectPaths' => true,
        ]);

        return (new QRCode($options))->render($this->url($token));
    }

    /**
     * @return array{status: string, message: string, team: ?Team}
     */
    public function resolve(string $token): array
    {
        if (! preg_match('/^[A-Za-z0-9]{20,64}$/', $token)) {
            return ['status' => 'invalid', 'message' => 'QR Code tidak valid.', 'team' => null];
        }

        $record = TeamQrToken::query()->where('token', $token)->with('team.period')->first();

        return match (true) {
            ! $record => ['status' => 'unknown', 'message' => 'QR Code tidak dikenal oleh sistem.', 'team' => null],
            $record->revoked_at !== null => ['status' => 'expired', 'message' => 'QR Code ini sudah tidak berlaku. Minta tim menampilkan QR terbaru.', 'team' => $record->team],
            $record->team->status !== 'diterima' || ! $record->team->period->is_active => ['status' => 'inactive', 'message' => 'Tim ini tidak terdaftar pada periode Capstone yang aktif.', 'team' => $record->team],
            default => ['status' => 'ok', 'message' => '', 'team' => $record->team],
        };
    }

    /**
     * F59 / F61 — tahap penilaian aktif yang menjadi kewenangan dosen untuk tim ini.
     *
     * @return Collection<int, string> kode komponen
     */
    public function activeComponentsFor(Team $team, User $lecturer): Collection
    {
        $role = $team->lecturerRole($lecturer);
        if (! $role) {
            return collect();
        }

        return $team->period->assessmentWindows()->get()
            ->filter(fn ($w) => $w->isOpen() && config("capstone.components.{$w->component}.role") === $role)
            ->pluck('component')
            ->values();
    }
}
