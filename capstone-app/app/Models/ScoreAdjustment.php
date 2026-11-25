<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Penyesuaian Nilai (dosen pembimbing): nilai akhir spesifik untuk satu mahasiswa, menggantikan nilai akhir sistem
 * (mis. anggota yang jarang hadir). Berlaku pada nilai akhir & indeks di Rekap Penilaian; pada ekspor iGadis seluruh
 * kolom diskalakan proporsional (× nilai disesuaikan ÷ nilai sistem). Tidak terlihat mahasiswa.
 */
class ScoreAdjustment extends Model
{
    protected $fillable = ['team_id', 'student_id', 'lecturer_id', 'score', 'reason'];

    protected function casts(): array
    {
        return ['score' => 'float'];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }
}
