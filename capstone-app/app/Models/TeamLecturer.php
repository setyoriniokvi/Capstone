<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamLecturer extends Model
{
    protected $fillable = ['team_id', 'lecturer_id', 'role', 'source', 'assigned_by'];

    /** Pembimbing / penguji otomatis terdaftar di periode tim (daftar Dosen Dashboard Periode). */
    protected static function booted(): void
    {
        static::saved(function (self $assignment) {
            $period = Team::query()->whereKey($assignment->team_id)->value('period_id');
            User::query()->find($assignment->lecturer_id)?->joinPeriod($period);
        });
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }
}
