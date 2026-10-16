<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamQrToken extends Model
{
    protected $fillable = ['team_id', 'token', 'revoked_at'];

    protected function casts(): array
    {
        return ['revoked_at' => 'datetime'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }
}
