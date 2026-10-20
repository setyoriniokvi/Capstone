<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Peminatan yang tersedia untuk satu prodi pada satu periode (F5). */
class RuleSpecialization extends Model
{
    protected $fillable = ['registration_rule_id', 'name'];

    public function rule(): BelongsTo
    {
        return $this->belongsTo(RegistrationRule::class, 'registration_rule_id');
    }
}
