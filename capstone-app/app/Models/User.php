<?php

namespace App\Models;

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
        'name', 'email', 'password', 'google_id', 'avatar_url', 'role', 'prodi_code', 'phone', 'is_active',
        'nim', 'sks_passed', 'passed_courses', 'origin_region', 'specialization', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token', 'google_id'];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'passed_courses' => 'array',
            'last_login_at' => 'datetime',
        ];
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
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
            'admin' => 'admin.dashboard',
            'dosen' => 'dosen.dashboard',
            default => 'mahasiswa.dashboard',
        };
    }

    public function lecturerProfile(): HasOne
    {
        return $this->hasOne(LecturerProfile::class);
    }

    public function studyProgram()
    {
        return $this->belongsTo(StudyProgram::class, 'prodi_code', 'code');
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

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->name)) ?: [];

        return mb_strtoupper(mb_substr($parts[0] ?? '', 0, 1).mb_substr($parts[1] ?? '', 0, 1));
    }

    public function roleLabel(): string
    {
        return match ($this->role) {
            'admin' => 'Admin',
            'dosen' => 'Dosen',
            default => 'Mahasiswa',
        };
    }
}
