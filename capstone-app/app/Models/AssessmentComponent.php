<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Throwable;

/**
 * F13 — bentuk penilaian (master data). "roles" = siapa yang mengisi: pembimbing, penguji (boleh keduanya),
 * atau mahasiswa (peer review). Daftar aktif dicerminkan ke config('capstone.components') agar seluruh kode
 * membaca satu sumber: [key => ['label', 'role' (penilai pertama), 'roles']].
 */
class AssessmentComponent extends Model
{
    public const LECTURER_ROLES = ['pembimbing' => 'Pembimbing', 'penguji' => 'Penguji'];

    protected $fillable = ['key', 'label', 'roles', 'sort_order'];

    protected function casts(): array
    {
        return ['roles' => 'array'];
    }

    public function rolesLabel(): string
    {
        return collect($this->roles)->map(fn ($r) => self::LECTURER_ROLES[$r] ?? ucfirst($r))->implode(' & ');
    }

    /** Muat daftar bentuk penilaian dari database ke config; bawaan config dipakai bila tabel belum tersedia. */
    public static function syncConfig(): void
    {
        try {
            $rows = static::query()->orderBy('sort_order')->orderBy('id')->get()
                ->mapWithKeys(fn (self $c) => [$c->key => ['label' => $c->label, 'role' => $c->roles[0] ?? null, 'roles' => $c->roles]])
                ->all();
        } catch (Throwable) {
            $rows = [];
        }

        config(['capstone.components' => $rows ?: collect(config('capstone.default_components'))
            ->map(fn ($c) => $c + ['roles' => [$c['role']]])->all()]);
    }

    /** @return list<string> penilai bentuk penilaian (pembimbing / penguji / mahasiswa) */
    public static function rolesOf(string $key): array
    {
        return config("capstone.components.{$key}.roles", []);
    }
}
