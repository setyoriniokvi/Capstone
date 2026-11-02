<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrerequisiteCourse extends Model
{
    protected $fillable = ['registration_rule_id', 'code', 'name'];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RegistrationRule::class, 'registration_rule_id');
    }
}
