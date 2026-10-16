<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class OralRubricLevel extends Model
{
    protected $fillable = ['level', 'factor', 'label', 'behavior'];

    protected function casts(): array
    {
        return ['factor' => 'float'];
    }
}
