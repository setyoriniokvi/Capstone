<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ReminderRule extends Model
{
    protected $fillable = ['job_type', 'hours_before', 'channels', 'is_active'];

    protected function casts(): array
    {
        return [
            'channels' => 'array',
            'is_active' => 'boolean',
        ];
    }
}
