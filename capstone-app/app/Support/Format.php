<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Format
{
    /** Jarak ke deadline dalam hari kalender: "Hari ini", "Besok", "5 hari lagi", "Terlambat 2 hari". */
    public static function dueIn(CarbonInterface $due): string
    {
        if ($due->isPast()) {
            $late = (int) $due->copy()->startOfDay()->diffInDays(now()->startOfDay());

            return $late === 0 ? 'Deadline terlewat hari ini' : "Terlambat {$late} hari";
        }

        $days = (int) now()->startOfDay()->diffInDays($due->copy()->startOfDay());

        return match ($days) {
            0 => 'Hari ini',
            1 => 'Besok',
            default => "{$days} hari lagi",
        };
    }

    public static function bytes(int $bytes): string
    {
        return match (true) {
            $bytes >= 1048576 => number_format($bytes / 1048576, 1, ',', '.').' MB',
            $bytes >= 1024 => number_format($bytes / 1024, 0, ',', '.').' KB',
            default => $bytes.' B',
        };
    }

    /**
     * Teks bebas → HTML aman: seluruh teks di-escape, lalu setiap URL http(s) menjadi tautan yang dibuka di tab baru.
     * Dipakai untuk catatan yang dapat berisi tautan (mis. Notes log bimbingan); tampilkan dengan {!! !!}.
     */
    public static function linkify(?string $text): string
    {
        $escaped = e((string) $text);

        return preg_replace_callback('~https?://[^\s<]+~i', function (array $m) {
            // Tanda baca penutup kalimat di ujung URL bukan bagian tautan.
            $url = rtrim($m[0], '.,;:!?)');
            $tail = substr($m[0], strlen($url));

            return '<a href="'.$url.'" target="_blank" rel="noopener noreferrer" class="link break-all">'.$url.'</a>'.$tail;
        }, $escaped);
    }
}
