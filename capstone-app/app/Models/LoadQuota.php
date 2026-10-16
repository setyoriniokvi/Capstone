<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadQuota extends Model
{
    protected $fillable = [
        'period_id', 'scope', 'category', 'lecturer_id',
        'supervise_default', 'supervise_min', 'supervise_max',
        'examine_default', 'examine_min', 'examine_max',
    ];

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }
}
