<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LoadQuota extends Model
{
    protected $fillable = [
        'period_id', 'scope', 'category', 'lecturer_id',
        'supervise_min', 'supervise_max',
        'examine_min', 'examine_max',
    ];

    /** Label cakupan: Global / Dosen kelas / Dosen tertentu. */
    public function coverageLabel(): string
    {
        return match (true) {
            $this->scope === 'global' => 'Global',
            $this->scope === 'kategori' && $this->category === 'dosen_kelas' => 'Dosen kelas',
            default => 'Dosen tertentu',
        };
    }

    /** "Berlaku untuk": semua dosen / semua dosen kelas / nama dosen. */
    public function targetLabel(): string
    {
        return match ($this->coverageLabel()) {
            'Global' => 'Semua dosen',
            'Dosen kelas' => 'Semua dosen kelas',
            default => $this->lecturer?->name ?? '—',
        };
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }
}
