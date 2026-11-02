<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
{
    protected $fillable = ['team_id', 'user_id', 'is_leader', 'account_created', 'invite_status', 'khs_path', 'domicile_path', 'responded_at'];

    protected function casts(): array
    {
        return [
            'is_leader' => 'boolean',
            'account_created' => 'boolean',
            'responded_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
