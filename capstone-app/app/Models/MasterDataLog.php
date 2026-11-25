<?php

namespace App\Models;

use App\Models\Concerns\BelongsToPeriod;
use Illuminate\Database\Eloquent\Model;

/** Satu baris log Master Data (lihat App\Services\MasterData\MasterDataLogger). */
class MasterDataLog extends Model
{
    use BelongsToPeriod;

    public const UPDATED_AT = null;

    public const EVENTS = ['created' => 'Dibuat', 'updated' => 'Diubah', 'deleted' => 'Dihapus'];

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['changes' => 'array', 'created_at' => 'datetime'];
    }

    public function eventLabel(): string
    {
        return self::EVENTS[$this->event] ?? $this->event;
    }
}
