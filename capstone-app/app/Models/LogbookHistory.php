<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LogbookHistory extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['logbook_id', 'event', 'snapshot', 'actor_id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
