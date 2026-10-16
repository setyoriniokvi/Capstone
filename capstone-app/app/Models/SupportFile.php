<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportFile extends Model
{
    protected $fillable = ['title', 'description', 'category', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
