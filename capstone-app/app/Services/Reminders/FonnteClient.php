<?php

namespace App\Services\Reminders;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * F51 — kirim WhatsApp lewat Fonnte (https://fonnte.com). Token perangkat diambil dari menu Device di dashboard
 * Fonnte dan diisi di FONNTE_TOKEN; pesan terkirim dari nomor WhatsApp yang terhubung ke perangkat itu.
 */
class FonnteClient
{
    private const SEND_URL = 'https://api.fonnte.com/send';

    public function configured(): bool
    {
        return filled(config('capstone.fonnte.token'));
    }

    /** Kirim satu pesan; gagal (token salah, perangkat terputus, nomor tidak valid, kuota habis) → RuntimeException berisi alasan dari Fonnte. */
    public function send(string $target, string $message): void
    {
        if (! $this->configured()) {
            throw new RuntimeException('FONNTE_TOKEN belum diisi di .env.');
        }

        $response = Http::asForm()->timeout(30)
            ->withHeaders(['Authorization' => (string) config('capstone.fonnte.token')])
            ->post(self::SEND_URL, [
                'target' => $target,
                'message' => $message,
                'countryCode' => (string) config('capstone.fonnte.country_code', '62'),
            ]);

        $json = $response->json() ?? [];
        if (! $response->successful() || ($json['status'] ?? false) !== true) {
            throw new RuntimeException('Fonnte: '.($json['reason'] ?? $json['detail'] ?? 'HTTP '.$response->status()));
        }
    }
}
