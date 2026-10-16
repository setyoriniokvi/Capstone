<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriveOperation extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['document_id', 'document_version_id', 'action', 'status', 'error', 'actor_id'];
}
