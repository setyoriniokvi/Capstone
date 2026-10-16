<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class IndicatorComponentWeight extends Model
{
    public $timestamps = false;

    protected $fillable = ['indicator_id', 'component', 'weight'];

    protected function casts(): array
    {
        return ['weight' => 'float'];
    }
}
