<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * F5 — ganti daftar peminatan lama (contoh awal) dengan peminatan resmi, dan petakan semua data yang memakainya:
 * daftar peminatan periode, peminatan mahasiswa & dosen, peminatan topik, arsip anggota tim ditolak, serta
 * keterangan syarat "berbeda peminatan" pada hasil screening tim (lulus/tidaknya dan skor dihitung ulang).
 */
return new class extends Migration
{
    private const MAP = [
        'Enterprise Systems' => 'Enterprise Intelligence System Development',
        'Supply Chain Analytics' => 'Enterprise Intelligence System Development',
        'Data Engineering' => 'Enterprise Data Management',
        'Engineering Management' => 'System Architecture and Governance',
        'Quality Engineering' => 'System Architecture and Governance',
        'Ergonomics' => 'System Architecture and Governance',
        'Supply Chain' => 'Enterprise Resource Planning',
        'Logistics' => 'Enterprise Resource Planning',
        'Project Management' => 'Enterprise Resource Planning',
        'Production Systems' => 'Enterprise Infrastructure Management',
    ];

    private const NEW = [
        'Enterprise Intelligence System Development',
        'Enterprise Data Management',
        'System Architecture and Governance',
        'Enterprise Resource Planning',
        'Enterprise Infrastructure Management',
    ];

    public function up(): void
    {
        DB::transaction(function () {
            $old = array_keys(self::MAP);

            // Daftar peminatan periode: aturan yang memakai nama lama diganti seluruhnya dengan daftar baru.
            $rules = DB::table('rule_specializations')->whereIn('name', $old)->distinct()->pluck('registration_rule_id');
            foreach ($rules as $ruleId) {
                DB::table('rule_specializations')->where('registration_rule_id', $ruleId)->whereIn('name', $old)->delete();
                $existing = DB::table('rule_specializations')->where('registration_rule_id', $ruleId)->pluck('name')->all();
                foreach (array_diff(self::NEW, $existing) as $name) {
                    DB::table('rule_specializations')->insert(['registration_rule_id' => $ruleId, 'name' => $name, 'created_at' => now(), 'updated_at' => now()]);
                }
            }

            foreach (self::MAP as $from => $to) {
                DB::table('users')->where('specialization', $from)->update(['specialization' => $to]);
                DB::table('lecturer_profiles')->where('specialization', $from)->update(['specialization' => $to]);
            }

            foreach (DB::table('topics')->whereNotNull('specializations')->get(['id', 'specializations']) as $topic) {
                $mapped = collect(json_decode($topic->specializations, true) ?: [])->map(fn ($s) => self::MAP[$s] ?? $s)->unique()->values()->all();
                DB::table('topics')->where('id', $topic->id)->update(['specializations' => json_encode($mapped)]);
            }

            $lower = collect(self::MAP)->mapWithKeys(fn ($to, $from) => [mb_strtolower($from) => mb_strtolower($to)]);
            foreach (DB::table('teams')->get(['id', 'screening', 'archived_members']) as $team) {
                $changes = [];

                if ($team->archived_members) {
                    $members = collect(json_decode($team->archived_members, true) ?: [])
                        ->map(fn ($m) => ['specialization' => self::MAP[$m['specialization'] ?? ''] ?? ($m['specialization'] ?? null)] + $m)->all();
                    $changes['archived_members'] = json_encode($members);
                }

                if ($team->screening) {
                    $checks = collect(json_decode($team->screening, true) ?: [])->map(function (array $check) use ($lower) {
                        if ($check['key'] !== 'peminatan' || $check['detail'] === 'Peminatan belum diisi') {
                            return $check;
                        }
                        $specs = collect(explode(', ', $check['detail']))->map(fn ($s) => $lower[$s] ?? $s)->unique()->values();

                        return ['passed' => $specs->count() >= 2, 'detail' => $specs->implode(', ')] + $check;
                    });
                    $changes['screening'] = json_encode($checks->values()->all());
                    $changes['screening_score'] = $checks->isEmpty() ? 0 : (int) round($checks->where('passed', true)->count() / $checks->count() * 100);
                }

                if ($changes) {
                    DB::table('teams')->where('id', $team->id)->update($changes);
                }
            }
        });
    }

    /** Pemetaan bersifat banyak-ke-satu sehingga tidak dapat dibalik. */
    public function down(): void {}
};
