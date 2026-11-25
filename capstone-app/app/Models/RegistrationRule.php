<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationRule extends Model
{
    protected $fillable = [
        'period_id', 'min_sks', 'min_members', 'max_members',
        'require_distinct_region', 'require_distinct_specialization', 'classes',
    ];

    protected function casts(): array
    {
        return [
            'require_distinct_region' => 'boolean',
            'require_distinct_specialization' => 'boolean',
            // Kelas mahasiswa pada periode ini (mis. SI-47-01), dipilih saat registrasi akun & tim
            'classes' => 'array',
        ];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(PrerequisiteCourse::class);
    }

    /** Peminatan pada periode ini — pilihan "Kompetensi yang diharapkan" pada topik dosen. */
    public function specializations(): HasMany
    {
        return $this->hasMany(RuleSpecialization::class)->orderBy('name');
    }
}
