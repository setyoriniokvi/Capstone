<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

/** Log setiap operasi Google Drive (create / update / trash / delete / share) beserta hasilnya. */
class DriveOperation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['document_id', 'document_version_id', 'action', 'status', 'error', 'actor_id'];

    public static function record(Document $document, ?DocumentVersion $version, string $action, string $status, ?string $error = null, ?User $actor = null): self
    {
        return self::create([
            'document_id' => $document->id,
            'document_version_id' => $version?->id,
            'action' => $action,
            'status' => $status,
            'error' => $error ? Str::limit($error, 1000) : null,
            'actor_id' => $actor?->id,
        ]);
    }
}
