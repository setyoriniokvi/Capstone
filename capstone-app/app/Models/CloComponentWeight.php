<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CloComponentWeight extends Model
{
    public $timestamps = false;

    protected $fillable = ['clo_id', 'component', 'weight'];

    protected function casts(): array
    {
        return ['weight' => 'float'];
    }
}
