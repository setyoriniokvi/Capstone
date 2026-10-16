<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentRubric extends Model
{
    protected $fillable = [
        'indicator_id', 'assessment_points', 'level_5', 'level_4', 'level_3', 'level_2', 'level_1', 'level_0', 'example_level_5',
    ];

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }

    public function describe(int $level): string
    {
        return (string) $this->getAttribute("level_{$level}");
    }
}
