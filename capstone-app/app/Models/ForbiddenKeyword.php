<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** F7 — kata kunci terlarang per jenis topik ("mandiri" atau "dosen"). */
class ForbiddenKeyword extends Model
{
    public const SOURCES = ['mandiri' => 'topik mandiri', 'dosen' => 'topik dosen'];

    protected $fillable = ['source', 'keyword'];
}
