<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Team extends Model
{
    protected $fillable = [
        'period_id', 'code', 'name', 'prodi_code', 'leader_id', 'status', 'screening', 'screening_score',
        'review_notes', 'reviewed_by', 'reviewed_at', 'submitted_at', 'final_topic_id',
    ];

    protected function casts(): array
    {
        return [
            'screening' => 'array',
            'reviewed_at' => 'datetime',
            'submitted_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function leader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'leader_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function members(): HasMany
    {
        return $this->hasMany(TeamMember::class)->orderByDesc('is_leader')->orderBy('id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'team_members')->withPivot(['is_leader', 'invite_status']);
    }

    /** Anggota yang sudah menerima undangan. */
    public function activeUsers(): BelongsToMany
    {
        return $this->users()->wherePivot('invite_status', 'diterima');
    }

    public function topics(): HasMany
    {
        return $this->hasMany(Topic::class);
    }

    public function independentTopic(): HasOne
    {
        return $this->hasOne(Topic::class)->where('source', 'mandiri')->latestOfMany();
    }

    public function finalTopic(): BelongsTo
    {
        return $this->belongsTo(Topic::class, 'final_topic_id');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(TopicApplication::class);
    }

    public function lecturers(): HasMany
    {
        return $this->hasMany(TeamLecturer::class);
    }

    public function supervisor(): HasOne
    {
        return $this->hasOne(TeamLecturer::class)->where('role', 'pembimbing');
    }

    public function examiner(): HasOne
    {
        return $this->hasOne(TeamLecturer::class)->where('role', 'penguji');
    }

    public function logbooks(): HasMany
    {
        return $this->hasMany(Logbook::class)->orderBy('session_no');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class);
    }

    public function peerReviews(): HasMany
    {
        return $this->hasMany(PeerReview::class);
    }

    public function qrTokens(): HasMany
    {
        return $this->hasMany(TeamQrToken::class);
    }

    public function activeQrToken(): ?TeamQrToken
    {
        return $this->qrTokens()->whereNull('revoked_at')->latest('id')->first();
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->where('user_id', $user->id)->where('invite_status', 'diterima')->exists();
    }

    public function lecturerRole(User $user): ?string
    {
        return $this->lecturers()->where('lecturer_id', $user->id)->value('role');
    }

    public function approvedLogbookCount(): int
    {
        return $this->logbooks()->where('status', 'disetujui')->count();
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'draft' => 'Draft',
            'menunggu' => 'Menunggu',
            'diterima' => 'Diterima',
            'ditolak' => 'Ditolak',
            default => $this->status,
        };
    }
}
