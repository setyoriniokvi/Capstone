<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** F7 — instruksi admin per jenis topik ("mandiri" / "dosen"); tidak ada baris = tanpa instruksi. */
class TopicInstruction extends Model
{
    protected $fillable = ['source', 'body'];

    /** Teks instruksi jenis topik ini, atau null bila belum diisi. */
    public static function for(string $source): ?string
    {
        return static::query()->where('source', $source)->value('body');
    }
}
