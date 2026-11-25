<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Data master per periode. Periode baru menyalin data master dari periode lain atau dari data bawaan
 * (template: period_id NULL). Memakai query builder (tanpa model) agar dapat dipakai juga di migrasi.
 */
class PeriodMasterData
{
    /** Tabel data master sederhana (tanpa relasi) yang disalin apa adanya. */
    public const SIMPLE = ['assessment_components', 'oral_rubric_levels', 'peer_aspects', 'score_rules',
        'topic_instructions', 'forbidden_keywords', 'reminder_rules'];

    /** Seluruh tabel ber-period_id yang dikelola per periode. */
    public const TABLES = [...self::SIMPLE, 'clos', 'indicators', 'support_files', 'master_data_logs'];

    /**
     * Salin data master $from → $to (null = template). Dosen ikut disalin bila sumbernya periode.
     * File pendukung & log tidak disalin (berkas fisik / riwayat milik periode sumber).
     */
    public function copy(?int $from, ?int $to): void
    {
        DB::transaction(function () use ($from, $to) {
            foreach (self::SIMPLE as $table) {
                $this->copyRows($table, $from, $to);
            }

            $cloIds = $this->copyRows('clos', $from, $to);
            $this->copyChildren('clo_component_weights', 'clo_id', $cloIds);

            $indicatorIds = $this->copyRows('indicators', $from, $to, ['clo_id' => $cloIds]);
            $this->copyChildren('indicator_component_weights', 'indicator_id', $indicatorIds);
            $this->copyChildren('document_rubrics', 'indicator_id', $indicatorIds);

            if ($from && $to) {
                $now = now();
                $rows = DB::table('period_users')->where('period_id', $from)
                    ->whereIn('user_id', DB::table('users')->where('role', 'dosen')->select('id'))
                    ->get()
                    ->map(fn ($r) => ['period_id' => $to, 'user_id' => $r->user_id, 'is_active' => $r->is_active, 'created_at' => $now, 'updated_at' => $now]);
                DB::table('period_users')->insertOrIgnore($rows->all());
                // Profil dosen (kode, NIP, peminatan, hak akses, WhatsApp) ikut disalin ke periode baru.
                foreach (DB::table('lecturer_profiles')->where('period_id', $from)->get() as $profile) {
                    $data = (array) $profile;
                    unset($data['id']);
                    DB::table('lecturer_profiles')->insertOrIgnore(['period_id' => $to, 'created_at' => $now, 'updated_at' => $now] + $data);
                }
            }
        });
    }

    /** Apakah periode sudah memiliki data master penilaian. */
    public function has(int $period): bool
    {
        return DB::table('assessment_components')->where('period_id', $period)->exists()
            || DB::table('clos')->where('period_id', $period)->exists();
    }

    /**
     * @param  array<string, array<int, int>>  $remap  kolom → [id lama => id baru]
     * @return array<int, int> id lama → id baru
     */
    private function copyRows(string $table, ?int $from, ?int $to, array $remap = []): array
    {
        $rows = DB::table($table)->when($from, fn ($q) => $q->where('period_id', $from), fn ($q) => $q->whereNull('period_id'))
            ->orderBy('id')->get();
        $map = [];
        foreach ($rows as $row) {
            $data = (array) $row;
            $old = $data['id'];
            unset($data['id']);
            $data['period_id'] = $to;
            foreach ($remap as $column => $ids) {
                if (! isset($ids[$data[$column]])) {
                    continue 2; // induk tidak ikut tersalin
                }
                $data[$column] = $ids[$data[$column]];
            }
            $map[$old] = DB::table($table)->insertGetId($data);
        }

        return $map;
    }

    /** @param  array<int, int>  $ids  id induk lama → baru */
    private function copyChildren(string $table, string $foreign, array $ids): void
    {
        if ($ids === []) {
            return;
        }
        foreach (DB::table($table)->whereIn($foreign, array_keys($ids))->orderBy('id')->get() as $row) {
            $data = (array) $row;
            unset($data['id']);
            $data[$foreign] = $ids[$data[$foreign]];
            DB::table($table)->insert($data);
        }
    }
}
