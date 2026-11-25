<?php

namespace App\Models;

use App\Support\PeriodContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'google_id', 'avatar_url', 'role', 'phone', 'is_active',
        'nim', 'class_name', 'sks_passed', 'passed_courses', 'course_grades', 'origin_region', 'specialization', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token', 'google_id'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'passed_courses' => 'array',
            'course_grades' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * Simpan indeks nilai mata kuliah prasyarat ({kode: nilai}) dan selaraskan passed_courses: kode yang dinilai
     * lulus (config capstone.passing_grades) ditambahkan, yang dinilai tidak lulus dihapus. Kode lain tetap.
     */
    public function setCourseGrades(array $grades): static
    {
        $grades = collect($grades)->filter(fn ($g) => filled($g))->mapWithKeys(fn ($g, $code) => [mb_strtoupper((string) $code) => $g]);
        $passing = $grades->filter(fn ($g) => in_array($g, config('capstone.passing_grades'), true))->keys();
        $others = collect($this->passed_courses ?? [])->reject(fn ($c) => $grades->has(mb_strtoupper((string) $c)));

        $this->course_grades = array_merge($this->course_grades ?? [], $grades->all());
        $this->passed_courses = $others->merge($passing)->unique()->values()->all();

        return $this;
    }

    /** Akun dibuat lewat registrasi ketua dan belum mengatur password (belum dapat masuk). */
    public function awaitingActivation(): bool
    {
        return blank($this->password);
    }

    /** Admin fakultas (CRUD periode) atau admin prodi (Dashboard Periode miliknya). */
    public function isAdmin(): bool
    {
        return in_array($this->role, ['admin', 'admin_prodi'], true);
    }

    public function isFacultyAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isProdiAdmin(): bool
    {
        return $this->role === 'admin_prodi';
    }

    /** Periode yang dikelola akun admin prodi ini (satu akun boleh dipakai beberapa periode). */
    public function adminPeriods(): HasMany
    {
        return $this->hasMany(Period::class, 'admin_user_id')->latest('starts_on')->orderByDesc('id');
    }

    /** Periode yang dapat dibuka admin ini sebagai Dashboard Periode. */
    public function workspacePeriods(): \Illuminate\Support\Collection
    {
        return $this->isFacultyAdmin()
            ? Period::query()->latest('starts_on')->orderByDesc('id')->get()
            : ($this->isProdiAdmin() ? $this->adminPeriods()->get() : collect());
    }

    public function canOpenPeriod(Period|int $period): bool
    {
        $id = $period instanceof Period ? $period->id : $period;

        return $this->isFacultyAdmin() || ($this->isProdiAdmin() && $this->adminPeriods()->whereKey($id)->exists());
    }

    public function isStudent(): bool
    {
        return $this->role === 'mahasiswa';
    }

    public function isLecturer(): bool
    {
        return $this->role === 'dosen';
    }

    public function homeRoute(): string
    {
        return match ($this->role) {
            // Admin di dalam Dashboard Periode kembali ke dashboard periode itu, bukan ke Kelola Periode.
            'admin' => Period::workspace() ? 'admin.dashboard' : 'admin.periods.index',
            // Admin prodi langsung bekerja di Dashboard Periode miliknya (tanpa Kelola Periode).
            'admin_prodi' => 'admin.dashboard',
            'dosen' => 'dosen.dashboard',
            default => 'mahasiswa.dashboard',
        };
    }

    /**
     * Peran dosen di periode konteks: pembimbing / penguji — dari hak akses profil (tercentang otomatis saat diplot)
     * atau penugasan tim di periode itu. Menu sidebar & tab penilaian dosen mengikuti peran ini.
     *
     * @return list<string>
     */
    public function lecturerRoles(): array
    {
        if (! $this->isLecturer()) {
            return [];
        }
        $period = PeriodContext::id();
        $assigned = $period ? $this->lecturerAssignments()->whereHas('team', fn ($q) => $q->where('period_id', $period))
            ->distinct()->pluck('role')->all() : [];
        $profile = $this->lecturerProfile;

        return array_values(array_filter(['pembimbing', 'penguji'], fn ($role) => in_array($role, $assigned, true)
            || (bool) $profile?->{LecturerProfile::ROLES[$role]}));
    }

    /** Nomor WhatsApp: dosen memakai nomor di profil periode konteks, selain itu nomor akun. */
    public function waNumber(): ?string
    {
        return ($this->isLecturer() ? $this->lecturerProfile?->phone : null) ?: $this->phone;
    }

    /** Profil dosen di periode konteks (profil dosen per periode). */
    public function lecturerProfile(): HasOne
    {
        return $this->hasOne(LecturerProfile::class);
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(TeamMember::class);
    }

    public function teams(): BelongsToMany
    {
        return $this->belongsToMany(Team::class, 'team_members')
            ->withPivot(['is_leader', 'invite_status'])
            ->withTimestamps();
    }

    /** Tim aktif (undangan diterima) pada periode tertentu. */
    public function teamFor(?Period $period): ?Team
    {
        if (! $period) {
            return null;
        }

        return $this->teams()
            ->where('teams.period_id', $period->id)
            ->wherePivot('invite_status', 'diterima')
            ->first();
    }

    /** Keanggotaan dosen / mahasiswa per periode (daftar Dosen & Mahasiswa di Dashboard Periode). */
    public function periods(): BelongsToMany
    {
        return $this->belongsToMany(Period::class, 'period_users')->withPivot('is_active')->withTimestamps();
    }

    /** Daftarkan pengguna ke periode (default: periode konteks); status aktif keanggotaan diperbarui bila diberikan. */
    public function joinPeriod(Period|int|null $period = null, ?bool $active = null): void
    {
        $id = $period instanceof Period ? $period->id : ($period ?? PeriodContext::id());
        if (! $id) {
            return;
        }
        $exists = $this->periods()->wherePivot('period_id', $id)->exists();
        if (! $exists) {
            $this->periods()->attach($id, ['is_active' => $active ?? true]);
        } elseif ($active !== null) {
            $this->periods()->updateExistingPivot($id, ['is_active' => $active]);
        }
        if ($this->isLecturer()) {
            LecturerProfile::ensureFor($this->id, $id);
        }
    }

    /** Status aktif di periode tertentu (default: periode konteks). */
    public function activeInPeriod(Period|int|null $period = null): bool
    {
        $id = $period instanceof Period ? $period->id : ($period ?? PeriodContext::id());

        return (bool) $this->periods()->wherePivot('period_id', $id)->first()?->pivot->is_active;
    }

    /**
     * Akun dosen dapat masuk selama aktif di minimal satu periode (atau belum terdaftar di periode mana pun);
     * dinonaktifkan di satu periode hanya berlaku untuk periode tersebut.
     */
    public function syncActiveFromPeriods(): void
    {
        $memberships = $this->periods()->get();
        if ($memberships->isNotEmpty()) {
            $this->update(['is_active' => $memberships->contains(fn ($p) => $p->pivot->is_active)]);
        }
    }

    public function lecturerAssignments(): HasMany
    {
        return $this->hasMany(TeamLecturer::class, 'lecturer_id');
    }

    public function scopeStudents(Builder $q): Builder
    {
        return $q->where('role', 'mahasiswa');
    }

    public function scopeLecturers(Builder $q): Builder
    {
        return $q->where('role', 'dosen');
    }

    /** Aturan validasi: id dosen yang terdaftar (default: dan aktif) di periode (default: periode konteks). */
    public static function lecturerRule(Period|int|null $period = null, bool $active = true): \Illuminate\Validation\Rules\Exists
    {
        $id = $period instanceof Period ? $period->id : ($period ?? PeriodContext::id());
        $rule = \Illuminate\Validation\Rule::exists('period_users', 'user_id')->where('period_id', $id)
            ->where(fn ($q) => $q->whereIn('user_id', fn ($s) => $s->select('id')->from('users')->where('role', 'dosen')));

        return $active ? $rule->where('is_active', true) : $rule;
    }

    /** Tambah kolom period_active: status aktif keanggotaan di periode konteks (daftar Dosen / Mahasiswa). */
    public function scopeWithPeriodStatus(Builder $q): Builder
    {
        if (! $q->getQuery()->columns) {
            $q->select('users.*');
        }

        return $q->addSelect(['period_active' => \Illuminate\Support\Facades\DB::table('period_users')->select('is_active')
            ->whereColumn('period_users.user_id', 'users.id')->where('period_users.period_id', PeriodContext::id())->limit(1)]);
    }

    /** Status aktif di Dashboard Periode (keanggotaan periode; akun lama tanpa keanggotaan memakai status akun). */
    public function periodActive(): bool
    {
        return (bool) ($this->period_active ?? $this->is_active);
    }

    /** Terdaftar di periode (default: periode konteks); $active = hanya keanggotaan aktif. */
    public function scopeInPeriod(Builder $q, Period|int|null $period = null, bool $active = false): Builder
    {
        $id = $period instanceof Period ? $period->id : ($period ?? PeriodContext::id());
        if (! $id) {
            return PeriodContext::strict() ? $q->whereRaw('1 = 0') : $q;
        }

        return $q->whereHas('periods', fn ($p) => $p->where('periods.id', $id)->when($active, fn ($p) => $p->where('period_users.is_active', true)));
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'Admin Fakultas',
            'admin_prodi' => 'Admin Prodi',
            'dosen' => 'Dosen',
            default => 'Mahasiswa',
        };
    }
}
