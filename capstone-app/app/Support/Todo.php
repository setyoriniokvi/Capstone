<?php

namespace App\Support;

use Carbon\CarbonInterface;

/** Item kartu "To Do" dashboard (<x-todo-card>): dipakai dashboard dosen & admin. */
class Todo
{
    public static function item(string $title, string $detail, string $url, string $tone, string $icon, ?CarbonInterface $due = null): array
    {
        $urgency = match (true) {
            ! $due => null,
            $due->isPast() => 'late',
            $due->lessThanOrEqualTo(now()->addDays(config('capstone.dashboard_soon_days'))) => 'soon',
            default => null,
        };

        return compact('title', 'detail', 'url', 'tone', 'icon', 'due', 'urgency');
    }
}
