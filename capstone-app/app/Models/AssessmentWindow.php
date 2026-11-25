<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentWindow extends Model
{
    protected $fillable = ['period_id', 'component', 'opens_at', 'due_at'];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    /**
     * Penilaian dapat diisi sejak jendela dibuka dan tetap terbuka setelah tenggat — jadwal hanya berfungsi sebagai
     * pengingat (reminder), bukan pengunci.
     */
    public function isOpen(): bool
    {
        return now()->greaterThanOrEqualTo($this->opens_at);
    }

    /** Tenggat penilaian sudah lewat (penilaian tetap dapat diisi). */
    public function isOverdue(): bool
    {
        return now()->greaterThan($this->due_at);
    }

    /** Status singkat untuk dosen: Belum dibuka / Dibuka / Lewat tenggat. */
    public function statusLabel(): string
    {
        return ! $this->isOpen() ? 'Belum dibuka' : ($this->isOverdue() ? 'Lewat tenggat' : 'Dibuka');
    }

    public function label(): string
    {
        return config("capstone.components.{$this->component}.label", $this->component);
    }
}
