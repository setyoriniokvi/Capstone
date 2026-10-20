<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Topic extends Model
{
    protected $fillable = [
        'period_id', 'source', 'title', 'description', 'field', 'object', 'competencies', 'prodi_code', 'team_id', 'lecturer_id', 'quota',
        'status', 'review_notes', 'keyword_hits', 'reviewed_by', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'keyword_hits' => 'array',
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

    public function studyProgram(): BelongsTo
    {
        return $this->belongsTo(StudyProgram::class, 'prodi_code', 'code');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(TopicApplication::class);
    }

    /**
     * Saring daftar topik menurut kata kunci (judul, deskripsi, objek, peminatan, nama dosen) dan peminatan
     * (= kompetensi yang diharapkan), lalu urutkan menurut nama dosen pengusul, kemudian judul.
     */
    public static function filterBank(Collection $topics, string $term, string $peminatan): Collection
    {
        $term = Str::lower(trim($term));

        return $topics
            ->filter(fn (Topic $t) => $peminatan === '' || $t->competencies === $peminatan)
            ->filter(fn (Topic $t) => $term === '' || Str::contains(Str::lower($t->title.' '.$t->description.' '.$t->object.' '.$t->competencies.' '.$t->lecturer->name), $term))
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
