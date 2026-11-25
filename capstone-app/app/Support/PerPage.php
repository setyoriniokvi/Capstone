<?php

namespace App\Support;

use Illuminate\Http\Request;

/** Pilihan "Tampilkan [n] data" pada tabel berhalaman: hanya nilai yang tersedia di dropdown yang diterima. */
final class PerPage
{
    public const OPTIONS = [10, 25, 50, 100];

    /** @param  list<int>  $options */
    public static function from(Request $request, string $param = 'per_page', int $default = 10, array $options = self::OPTIONS): int
    {
        $value = (int) $request->input($param);

        return in_array($value, $options, true) ? $value : $default;
    }
}
