<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssessmentScore extends Model
{
    public $timestamps = false;

    protected $fillable = ['assessment_id', 'indicator_id', 'score', 'oral_level', 'factor', 'note'];

    protected function casts(): array
    {
        return ['factor' => 'float'];
    }

    public function indicator(): BelongsTo
    {
        return $this->belongsTo(Indicator::class);
    }
}
