<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AssessmentWindow extends Model
{
    protected $fillable = ['period_id', 'component', 'opens_at', 'due_at'];

    protected function casts(): array
    {
        return [
            'opens_at' => 'datetime',
            'due_at' => 'datetime',
        ];
    }

    public function isOpen(): bool
    {
        return now()->between($this->opens_at, $this->due_at);
    }

    public function label(): string
    {
        return config("capstone.components.{$this->component}.label", $this->component);
    }
}
