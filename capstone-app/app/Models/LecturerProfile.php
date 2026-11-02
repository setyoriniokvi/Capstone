<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * F3 — hak akses dosen: setiap dosen berhak akses "Dosen"; Pembimbing / Penguji tercentang otomatis saat dosen diplot
 * (atau menerima tim di topiknya) dan dapat diubah admin lewat centang di Data & akun dosen.
 */
class LecturerProfile extends Model
{
    public const ROLES = ['pembimbing' => 'can_supervise', 'penguji' => 'can_examine'];

    protected $fillable = [
        'user_id', 'lecturer_code', 'nip', 'specialization', 'can_supervise', 'can_examine',
    ];

    protected function casts(): array
    {
        return [
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
            'Pembimbing' => $this->can_supervise,
            'Penguji' => $this->can_examine,
        ]));
    }

    /** Diplot / ditetapkan sebagai pembimbing atau penguji → hak akses tersebut tercentang otomatis. */
    public static function grant(int $lecturerId, string $role): void
    {
        if (isset(self::ROLES[$role])) {
            static::query()->updateOrCreate(['user_id' => $lecturerId], [self::ROLES[$role] => true]);
        }
    }
}
