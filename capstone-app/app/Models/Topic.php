<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Topic extends Model
{
    protected $fillable = [
        'period_id', 'source', 'title', 'description', 'field', 'object', 'competencies', 'specializations', 'prodi_codes', 'team_id', 'lecturer_id', 'quota',
        'status', 'review_notes', 'keyword_hits', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'keyword_hits' => 'array',
            'prodi_codes' => 'array',
            'specializations' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function lecturer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'lecturer_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** Dosen pengusul topik dosen; pengusul utama (pembuat, lecturer_id) tercatat pertama. */
    public function proposers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'topic_proposers', 'topic_id', 'lecturer_id')
            ->withPivot('invited_by')->withTimestamps()->orderBy('topic_proposers.id');
    }

    /** @return list<string> nama program studi topik (boleh lebih dari satu) */
    public function programNames(): array
    {
        $names = once(fn () => StudyProgram::query()->pluck('name', 'code'));

        return collect($this->prodi_codes ?? [])->map(fn ($code) => $names[$code] ?? $code)->all();
    }

    /** Pengusul utama maupun pengusul bersama memiliki akses penuh ke topik (ubah, hapus, putuskan pelamar). */
    public function isProposer(User $user): bool
    {
        return $this->lecturer_id === $user->id || $this->proposers->contains('id', $user->id);
    }

    /** Topik yang diusulkan dosen (sebagai pengusul utama atau pengusul bersama). */
    public function scopeProposedBy(Builder $q, int $lecturerId): Builder
    {
        return $q->where(fn ($q) => $q->where('lecturer_id', $lecturerId)->orWhereHas('proposers', fn ($p) => $p->whereKey($lecturerId)));
    }

    public function applications(): HasMany
    {
        return $this->hasMany(TopicApplication::class);
    }

    /**
     * Saring daftar topik menurut kata kunci (judul, deskripsi, kompetensi, objek, peminatan, nama dosen pengusul) dan
     * peminatan, lalu urutkan menurut nama dosen pengusul utama, kemudian judul. Butuh relasi lecturer & proposers.
     */
    public static function filterBank(Collection $topics, string $term, string $peminatan): Collection
    {
        $term = Str::lower(trim($term));

        return $topics
            ->filter(fn (Topic $t) => $peminatan === '' || in_array($peminatan, $t->specializations ?? [], true))
            ->filter(fn (Topic $t) => $term === '' || Str::contains(Str::lower(implode(' ', [
                $t->title, $t->description, $t->object, $t->competencies, implode(' ', $t->specializations ?? []), $t->lecturer->name, $t->proposers->pluck('name')->implode(' '),
            ])), $term))
            ->sortBy([fn (Topic $a, Topic $b) => strcasecmp($a->lecturer->name, $b->lecturer->name), fn (Topic $a, Topic $b) => strcasecmp($a->title, $b->title)])
            ->values();
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('source', 'dosen')->where('status', 'diterima');
    }

    public function acceptedCount(): int
    {
        return $this->applications()->where('status', 'diterima')->count();
    }

    public function remainingQuota(): int
    {
        return max(0, $this->quota - $this->acceptedCount());
    }
}
