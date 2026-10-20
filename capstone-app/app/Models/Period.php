<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Period extends Model
{
    protected $fillable = ['name', 'academic_year', 'semester', 'prodi_name', 'starts_on', 'ends_on', 'is_active', 'drive_root_folder_id'];

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

    /** Kunci sesi periode yang sedang dibuka admin ("Dashboard Periode"). */
    public const WORKSPACE_KEY = 'admin_period_id';

    /**
     * Periode yang sedang dikelola admin (dipilih lewat dropdown periode di sidebar). Berbeda dengan active():
     * periode aktif berlaku untuk mahasiswa & dosen, sedangkan ruang kerja ini hanya untuk sesi admin.
     */
    public static function workspace(): ?self
    {
        $id = session(self::WORKSPACE_KEY);

        return $id ? static::query()->find($id) : null;
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

    /** F5 — aturan registrasi tim periode ini (satu per periode, diatur di halaman periode). */
    public function registrationRule(): HasOne
    {
        return $this->hasOne(RegistrationRule::class)->oldestOfMany();
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

    /** Jumlah minggu pada timeline: minimal 16 (PRD §10.3). */
    public function displayWeeks(): int
    {
        return max(16, $this->totalWeeks());
    }

    /** Minggu berjalan, atau null bila hari ini di luar rentang periode. */
    public function currentWeek(): ?int
    {
        return now()->between($this->starts_on, $this->ends_on->copy()->endOfDay()) ? $this->weekOf(now()) : null;
    }

    /** Minggu milestone: week_no isian admin, atau dihitung dari tenggatnya. */
    public function weekOfMilestone(Milestone $milestone): int
    {
        return $milestone->week_no ?: min($this->displayWeeks(), $this->weekOf($milestone->due_at));
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
