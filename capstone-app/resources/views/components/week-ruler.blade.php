{{-- Penggaris 16 minggu (PRD §10.3: progress tracker horizontal 16 minggu) + daftar milestone. --}}
@props(['period', 'milestones' => null, 'highlight' => null, 'list' => true])
@php
    $milestones = ($milestones ?? $period->milestones)->sortBy('due_at')->values();
    $weeks = max(16, $period->totalWeeks());
    $current = now()->between($period->starts_on, $period->ends_on->endOfDay()) ? $period->weekOf(now()) : null;
    $weekOf = fn ($m) => $m->week_no ?: min($weeks, $period->weekOf($m->due_at));
    $byWeek = $milestones->groupBy($weekOf);
    $state = function ($m) use ($highlight) {
        $done = $highlight ? $highlight($m) : null;
        return match (true) {
            $done === true => 'done',
            $done === false && $m->isPast() => 'missed',
            $m->isOpen() => 'open',
            $m->isPast() => 'past',
            default => 'upcoming',
        };
    };
    $dot = ['done' => 'bg-brand border-brand', 'missed' => 'bg-bad border-bad', 'open' => 'bg-accent border-brand', 'past' => 'bg-rule-strong border-rule-strong', 'upcoming' => 'bg-surface border-rule-strong'];
@endphp
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <div class="overflow-x-auto pb-1">
        <ol class="grid min-w-[560px]" style="grid-template-columns: repeat({{ $weeks }}, minmax(0, 1fr))" aria-label="Timeline {{ $weeks }} minggu">
            @for ($w = 1; $w <= $weeks; $w++)
                @php $items = $byWeek->get($w, collect()); $isNow = $current === $w; @endphp
                <li class="flex flex-col items-center gap-1.5" @if ($isNow) aria-current="step" @endif>
                    <span class="font-mono text-[0.6875rem] {{ $isNow ? 'font-semibold text-brand-deep' : 'text-faint' }}">{{ $w }}</span>
                    <span class="h-2 w-full {{ $isNow ? 'bg-brand' : ($current && $w < $current ? 'bg-accent-tint' : 'bg-sunken') }} {{ $w === 1 ? 'rounded-l' : '' }} {{ $w === $weeks ? 'rounded-r' : '' }}"></span>
                    <span class="flex min-h-[10px] flex-wrap justify-center gap-0.5">
                        @foreach ($items as $m)
                            <span class="h-2.5 w-2.5 rotate-45 rounded-[2px] border {{ $dot[$state($m)] }}" title="{{ $m->name }}"></span>
                        @endforeach
                    </span>
                </li>
            @endfor
        </ol>
    </div>
    @if ($current)
        <p class="mt-1 text-caption text-muted">Minggu ke-<span class="font-mono">{{ $current }}</span> dari {{ $weeks }}.</p>
    @endif

    @if ($list && $milestones->isNotEmpty())
        <ol class="mt-4 gap-x-6 border-t border-rule pt-4 text-sm sm:columns-2 lg:columns-3 [&>li]:mb-1.5 [&>li]:break-inside-avoid">
            @foreach ($milestones as $m)
                @php $s = $state($m); @endphp
                <li class="flex min-w-0 items-baseline gap-2.5">
                    <span class="h-2 w-2 shrink-0 translate-y-[-1px] rotate-45 rounded-[2px] border {{ $dot[$s] }}" aria-hidden="true"></span>
                    <span class="w-9 shrink-0 font-mono text-caption text-muted">Mg {{ $weekOf($m) }}</span>
                    <span class="min-w-0 flex-1 truncate {{ $s === 'past' || $s === 'done' ? 'text-muted' : 'text-ink' }} {{ $s === 'open' ? 'font-medium' : '' }}" title="{{ $m->name }}">{{ $m->name }}</span>
                    <span class="shrink-0 text-caption {{ $s === 'missed' ? 'text-bad-ink' : 'text-muted' }}">{{ $m->due_at->translatedFormat('j M') }}</span>
                </li>
            @endforeach
        </ol>
    @endif
</div>
