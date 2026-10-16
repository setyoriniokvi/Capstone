<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamLecturer extends Model
{
    protected $fillable = ['team_id', 'lecturer_id', 'role', 'source', 'assigned_by'];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }
}
