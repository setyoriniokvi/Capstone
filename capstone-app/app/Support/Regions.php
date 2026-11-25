<?php

namespace App\Support;

/**
 * Kabupaten/kota di Indonesia untuk isian "Daerah asal (berdasarkan KTP)" — data di resources/data/kabupaten-kota.php
 * (Kepmendagri No. 300.2.2-2430 Tahun 2025), urut abjad nama tanpa awalan "Kabupaten"/"Kota". Mahasiswa boleh mengetik
 * manual bila kabupaten/kotanya tidak ada di daftar (pilihan "Gunakan …" saat pencarian kosong).
 */
class Regions
{
    private static ?array $all = null;

    /** @return list<array{0: string, 1: string}> [nama kabupaten/kota, provinsi] */
    public static function all(): array
    {
        return self::$all ??= require resource_path('data/kabupaten-kota.php');
    }

    /** @return list<string> */
    public static function names(): array
    {
        return array_column(self::all(), 0);
    }

    /**
     * Daerah asal ada di daftar resmi? Bila tidak, berarti diisi manual oleh mahasiswa (kabupaten/kota tidak ditemukan
     * di daftar) — ditandai "diisi manual" di detail tim agar admin mudah memeriksa salah ketik.
     */
    public static function isListed(?string $name): bool
    {
        static $index = null;
        $index ??= array_flip(array_map('mb_strtolower', self::names()));

        return $name !== null && isset($index[mb_strtolower(trim($name))]);
    }
}
