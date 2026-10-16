<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PrerequisiteCourse extends Model
{
    protected $fillable = ['registration_rule_id', 'code', 'name'];
}
