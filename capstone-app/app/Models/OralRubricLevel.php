<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPeriod;
use Illuminate\Database\Eloquent\Model;

class OralRubricLevel extends Model
{
    use BelongsToPeriod;

    protected $fillable = ['level', 'factor', 'label', 'behavior'];

    protected function casts(): array
    {
        return ['factor' => 'float'];
    }
}
