<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

/**
 * Kartu log proses di Master Data: beberapa kartu dalam satu halaman, masing-masing dengan Cari · Sort · filter dan
 * "Tampilkan [n] data" + ‹ › sendiri. Parameter tiap kartu memakai awalan kuncinya agar tidak saling bentrok:
 *   {key}_q (cari), {key}_urut / {key}_arah (Sort), {key}_{filter} (filter dropdown), {key}_per (jumlah data), {key}_hal (halaman).
 *
 * Baris disusun pemanggil sebagai array: 'search' (teks dicari), 'sort' => [kunci => nilai], 'filter' => [nama => nilai],
 * selebihnya data tampilan bebas. Data per periode tidak besar (ratusan–ribuan baris), jadi disaring & diurutkan di memori.
 */
class ProcessTable
{
    public const PER_PAGE = [5, 10, 25, 50, 100];

    public const DEFAULT_PER_PAGE = 5;

    /**
     * @param  Collection<int, array>  $rows
     * @param  array<string, string>  $sorts  kunci Sort yang tersedia (nilai = label, dipakai tampilan)
     * @param  list<string>  $filters  nama filter dropdown
     */
    public static function paginate(Request $request, string $key, Collection $rows, array $sorts, string $default, string $dir, array $filters = []): LengthAwarePaginator
    {
        $term = mb_strtolower(trim((string) $request->input("{$key}_q")));
        if ($term !== '') {
            $rows = $rows->filter(fn (array $row) => str_contains(mb_strtolower((string) ($row['search'] ?? '')), $term));
        }
        foreach ($filters as $filter) {
            $value = $request->input("{$key}_{$filter}");
            if (is_scalar($value) && $value !== '') {
                $rows = $rows->filter(fn (array $row) => (string) ($row['filter'][$filter] ?? '') === (string) $value);
            }
        }

        $sortKey = array_key_exists((string) $request->input("{$key}_urut"), $sorts) ? (string) $request->input("{$key}_urut") : $default;
        $sortDir = in_array($request->input("{$key}_arah"), ['asc', 'desc'], true) ? $request->input("{$key}_arah") : $dir;
        $rows = $rows->values()->sort(function (array $a, array $b) use ($sortKey, $sortDir) {
            $x = $a['sort'][$sortKey] ?? null;
            $y = $b['sort'][$sortKey] ?? null;
            // Nilai kosong selalu di akhir
            if ($x === null || $x === '' || $y === null || $y === '') {
                return ($x === null || $x === '') <=> ($y === null || $y === '');
            }
            $cmp = is_numeric($x) && is_numeric($y) ? $x <=> $y : strnatcasecmp((string) $x, (string) $y);

            return $sortDir === 'desc' ? -$cmp : $cmp;
        })->values();

        $perPage = PerPage::from($request, "{$key}_per", self::DEFAULT_PER_PAGE, self::PER_PAGE);
        $pageName = "{$key}_hal";
        $page = max(1, min((int) $request->input($pageName, 1), max(1, (int) ceil($rows->count() / $perPage))));

        return (new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page, [
            'path' => $request->url(),
            'pageName' => $pageName,
        ]))->withQueryString()->fragment($key);
    }
}
