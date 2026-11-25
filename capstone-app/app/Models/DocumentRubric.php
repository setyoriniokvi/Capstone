<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRubric extends Model
{
    protected $fillable = [
        'indicator_id', 'assessment_points', 'level_5', 'level_4', 'level_3', 'level_2', 'level_1', 'level_0',
    ];

    /** Rubrik mengikuti periode indikatornya (indikator dibatasi ke periode konteks). */
    protected static function booted(): void
    {
        static::addGlobalScope('period', fn ($query) => $query->whereIn($query->qualifyColumn('indicator_id'), Indicator::query()->select('id')));
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    public function describe(int $level): string
    {
        return (string) $this->getAttribute("level_{$level}");
    }
}
