<?php

namespace App\Support;

use Carbon\CarbonInterface;

class Format
{
    /** Jarak ke tenggat dalam hari kalender: "Hari ini", "Besok", "5 hari lagi", "Terlambat 2 hari". */
    public static function dueIn(CarbonInterface $due): string
    {
        if ($due->isPast()) {
            $late = (int) $due->copy()->startOfDay()->diffInDays(now()->startOfDay());

            return $late === 0 ? 'Tenggat terlewat hari ini' : "Terlambat {$late} hari";
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
}
