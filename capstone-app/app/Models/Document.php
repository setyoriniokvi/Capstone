<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Document extends Model
{
    protected $fillable = [
        'team_id', 'milestone_id', 'owner_id', 'owner_email', 'document_type', 'current_revision', 'current_version_id',
        'drive_file_id', 'drive_folder_id', 'review_status', 'feedback', 'feedback_by', 'feedback_at', 'is_late', 'state',
    ];

    protected function casts(): array
    {
        return [
            'is_late' => 'boolean',
            'feedback_at' => 'datetime',
        ];
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function feedbackBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'feedback_by');
    }

    /** Tautan file di Google Drive, bila sudah tersinkron. */
    public function driveUrl(): ?string
    {
        return $this->drive_file_id ? "https://drive.google.com/file/d/{$this->drive_file_id}/view" : null;
    }

    public function versions(): HasMany
    {
        return $this->hasMany(DocumentVersion::class)->orderByDesc('revision_no');
    }

    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(DocumentVersion::class, 'current_version_id');
    }

    public function latestVersion(): ?DocumentVersion
    {
        return $this->versions()->first();
    }

    public function typeLabel(): string
    {
        return config("capstone.document_types.{$this->document_type}", $this->document_type);
    }
}
