<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Period extends Model
{
    protected $fillable = ['name', 'academic_year', 'semester', 'starts_on', 'ends_on', 'is_active', 'drive_root_folder_id'];

    protected function casts(): array
    {
        return [
            'starts_on' => 'date',
            'ends_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public static function active(): ?self
    {
        return static::query()->where('is_active', true)->latest('starts_on')->first();
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('sort_order')->orderBy('starts_at');
    }

    public function assessmentWindows(): HasMany
    {
        return $this->hasMany(AssessmentWindow::class);
    }

    public function driveFolders(): HasMany
    {
        return $this->hasMany(PeriodDriveFolder::class);
    }

    public function registrationRules(): HasMany
    {
        return $this->hasMany(RegistrationRule::class);
    }

    public function teams(): HasMany
    {
        return $this->hasMany(Team::class);
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function loadQuotas(): HasMany
    {
        return $this->hasMany(LoadQuota::class);
    }

    /** Nomor minggu (1–16) relatif terhadap tanggal mulai periode. */
    public function weekOf(\DateTimeInterface $date): int
    {
        $days = $this->starts_on->startOfDay()->diffInDays($date, false);

        return max(1, (int) floor($days / 7) + 1);
    }

    public function totalWeeks(): int
    {
        return max(1, (int) ceil(($this->starts_on->diffInDays($this->ends_on) + 1) / 7));
    }

    public function milestoneOfKind(string $kind): ?Milestone
    {
        return $this->milestones->firstWhere('kind', $kind);
    }

    public function documentMilestone(string $documentType): ?Milestone
    {
        return $this->milestones->first(fn (Milestone $m) => $m->kind === 'dokumen' && $m->document_type === $documentType);
    }
}
