<?php

namespace App\Support;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;

/**
 * Urutan daftar yang dibagi halaman di server (dropdown <x-sort-by mode="server">): ?urut=<kunci>&arah=asc|desc.
 * Kolom berupa nama kolom (nilai kosong selalu di akhir) atau closure ($query, $dir). Tie-breaker: id.
 */
class Sorting
{
    /**
     * @param  array<string, string|Closure>  $columns
     * @return array{0: string, 1: string} [kunci terpilih, arah]
     */
    public static function apply(Builder|Relation $query, Request $request, array $columns, string $default, string $defaultDir = 'asc',
        string $name = 'urut', string $dirName = 'arah'): array
    {
        $key = array_key_exists((string) $request->input($name), $columns) ? (string) $request->input($name) : $default;
        $dir = in_array($request->input($dirName), ['asc', 'desc'], true) ? $request->input($dirName) : $defaultDir;
        $column = $columns[$key];
        $query->reorder();
        if ($column instanceof Closure) {
            $column($query, $dir);
        } else {
            $query->orderByRaw("{$column} is null")->orderBy($column, $dir);
        }
        $query->orderBy(($query instanceof Relation ? $query->getRelated() : $query->getModel())->getQualifiedKeyName(), $dir);

        return [$key, $dir];
    }
}
