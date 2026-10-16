<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DocumentVersion extends Model
{
    protected $fillable = [
        'document_id', 'revision_no', 'original_name', 'stored_name', 'mime', 'size', 'local_path', 'checksum',
        'idempotency_key', 'sync_action', 'sync_status', 'sync_attempts', 'sync_error', 'synced_at', 'uploaded_by',
    ];

    protected function casts(): array
    {
        return ['synced_at' => 'datetime'];
    }

    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
