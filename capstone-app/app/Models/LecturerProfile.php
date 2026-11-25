<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPeriod;
use App\Support\PeriodContext;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F3 — hak akses dosen: setiap dosen berhak akses "Dosen"; Pembimbing / Penguji tercentang otomatis saat dosen diplot
 * (atau menerima tim di topiknya) dan dapat diubah admin lewat centang di Data & akun dosen.
 * Profil dosen per periode: kode dosen, NIP, peminatan, hak Pembimbing/Penguji, dan nomor WhatsApp dapat berbeda di
 * setiap periode (email, nama, password tetap satu akun). Relasi User::lecturerProfile mengikuti periode konteks.
 */
class LecturerProfile extends Model
{
    use BelongsToPeriod;

    /** Peran plotting tim → kolom hak aksesnya (tercentang otomatis saat diplot). */
    public const ROLES = ['pembimbing' => 'can_supervise', 'penguji' => 'can_examine'];

    /** Hak akses selain "Dosen" (dimiliki semua dosen): kolom → label. Dosen Kelas dipakai cakupan kuota dosen kelas. */
    public const ACCESS = ['is_class_lecturer' => 'Dosen Kelas', 'can_supervise' => 'Pembimbing', 'can_examine' => 'Penguji'];

    /** Kata kunci kolom hak_akses impor dosen → kolom. */
    public const IMPORT_ACCESS = ['dosen_kelas' => 'is_class_lecturer', 'pembimbing' => 'can_supervise', 'penguji' => 'can_examine'];

    protected $fillable = [
        'user_id', 'lecturer_code', 'nip', 'specialization', 'phone', 'is_class_lecturer', 'can_supervise', 'can_examine',
    ];

    protected function casts(): array
    {
        return [
            'is_class_lecturer' => 'boolean',
            'can_supervise' => 'boolean',
            'can_examine' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return list<string> mis. ["Dosen", "Pembimbing"] */
    public function accessLabels(): array
    {
        return array_keys(array_filter([
            'Dosen' => true,
            'Dosen Kelas' => $this->is_class_lecturer,
            'Pembimbing' => $this->can_supervise,
            'Penguji' => $this->can_examine,
        ]));
    }

    /** Diplot / ditetapkan sebagai pembimbing atau penguji → hak akses tersebut tercentang otomatis. */
    public static function grant(int $lecturerId, string $role): void
    {
        if (isset(self::ROLES[$role])) {
            static::ensureFor($lecturerId)?->update([self::ROLES[$role] => true]);
        }
    }

    /**
     * Profil dosen di periode (default: periode konteks). Belum ada → dibuat dari profil periode terakhirnya
     * (kode, NIP, peminatan, WhatsApp; hak Pembimbing/Penguji dimulai kosong).
     */
    public static function ensureFor(int $userId, ?int $periodId = null): ?self
    {
        $periodId ??= PeriodContext::id();
        if (! $periodId) {
            return null;
        }
        $profile = static::inPeriod($periodId)->where('user_id', $userId)->first();
        if ($profile) {
            return $profile;
        }
        $latest = static::query()->withoutGlobalScope('period')->where('user_id', $userId)->whereNotNull('period_id')->latest('id')->first();
        $profile = new static(['user_id' => $userId] + ($latest ? $latest->only(['lecturer_code', 'nip', 'specialization', 'phone']) : []));
        $profile->period_id = $periodId;
        $taken = fn (string $column) => $profile->{$column} && static::inPeriod($periodId)->where($column, $profile->{$column})->exists();
        foreach (['lecturer_code', 'nip'] as $column) {
            if ($taken($column)) {
                $profile->{$column} = null; // dipakai dosen lain di periode ini; diisi ulang admin
            }
        }
        $profile->save();

        return $profile;
    }
}
