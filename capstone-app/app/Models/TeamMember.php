<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TeamMember extends Model
{
    protected $fillable = ['team_id', 'user_id', 'is_leader', 'account_created', 'invite_status', 'khs_path', 'domicile_path', 'responded_at'];

    /** Anggota tim otomatis terdaftar di periode tim (daftar Mahasiswa Dashboard Periode). */
    protected static function booted(): void
    {
        static::created(function (self $member) {
            $period = Team::query()->whereKey($member->team_id)->value('period_id');
            User::query()->find($member->user_id)?->joinPeriod($period);
        });
    }

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
