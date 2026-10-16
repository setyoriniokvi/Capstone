<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RegistrationRule extends Model
{
    protected $fillable = [
        'period_id', 'prodi_code', 'min_sks', 'min_members', 'max_members',
        'require_distinct_region', 'require_distinct_specialization',
    ];

    protected function casts(): array
    {
        return [
            'require_distinct_region' => 'boolean',
            'require_distinct_specialization' => 'boolean',
        ];
    }

    public function courses(): HasMany
    {
        return $this->hasMany(PrerequisiteCourse::class);
    }
}
