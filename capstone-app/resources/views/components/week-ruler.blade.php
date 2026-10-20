{{-- Penggaris 16 minggu (PRD §10.3: progress tracker horizontal 16 minggu) + daftar milestone.
     submitted   : ID milestone dokumen yang sudah dikumpulkan tim (null = tanpa status pribadi)
     collapsible : daftar milestone dilipat di balik tombol "Lihat semua tahap" --}}
@props(['period', 'milestones' => null, 'submitted' => null, 'list' => true, 'collapsible' => false])
@php
    $milestones = ($milestones ?? $period->milestones)->sortBy('due_at')->values();
    $weeks = $period->displayWeeks();
    $current = $period->currentWeek();
    $weekOf = fn ($m) => $period->weekOfMilestone($m);
    $byWeek = $milestones->groupBy($weekOf);
    $state = function ($m) use ($submitted) {
        $done = $submitted !== null && $m->kind === 'dokumen' ? collect($submitted)->contains($m->id) : null;

        return match (true) {
            $done === true => 'done',
            $done === false && $m->isPast() => 'missed',
            $m->isOpen() => 'open',
            $m->isPast() => 'past',
            default => 'upcoming',
        };
    };
    // Bentuk penanda dibedakan isian/tepi, bukan hanya warna (aksesibilitas buta warna).
    $dot = ['done' => 'bg-brand border-brand', 'open' => 'bg-surface border-2 border-brand', 'missed' => 'bg-bad border-bad', 'past' => 'bg-rule-strong border-rule-strong', 'upcoming' => 'bg-surface border-rule-strong'];
    $labels = ['done' => 'Selesai', 'open' => 'Sedang berjalan', 'missed' => 'Terlewat', 'past' => 'Sudah lewat', 'upcoming' => 'Mendatang'];
    $present = $milestones->map($state)->unique();
@endphp
<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    {{-- 16 kolom selalu muat lebar layar (tanpa scroll samping); angka minggu mengecil di layar sempit. --}}
    <div class="pb-1">
        <ol class="grid" style="grid-template-columns: repeat({{ $weeks }}, minmax(0, 1fr))" aria-label="Timeline {{ $weeks }} minggu">
            @for ($w = 1; $w <= $weeks; $w++)
                @php $items = $byWeek->get($w, collect()); $isNow = $current === $w; @endphp
                <li class="flex flex-col items-center gap-1.5" @if ($isNow) aria-current="step" @endif>
                    <span class="tabular text-[0.6875rem] sm:text-caption {{ $isNow ? 'font-semibold text-brand-deep' : 'text-faint' }}">{{ $w }}</span>
                    <span class="h-2 w-full {{ $isNow ? 'bg-brand' : ($current && $w < $current ? 'bg-accent-tint' : 'bg-sunken') }} {{ $w === 1 ? 'rounded-l' : '' }} {{ $w === $weeks ? 'rounded-r' : '' }}"></span>
                    <span class="flex min-h-[10px] flex-wrap justify-center gap-0.5">
                        @foreach ($items as $m)
                            <span class="h-2.5 w-2.5 rotate-45 rounded-[2px] border {{ $dot[$state($m)] }}" title="{{ $m->name }} — {{ $labels[$state($m)] }}"></span>
                        @endforeach
                    </span>
                </li>
            @endfor
        </ol>
    </div>

    <div class="mt-2 flex flex-wrap items-center justify-between gap-x-6 gap-y-2 text-caption text-muted">
        @if ($current)
            <p>Minggu ke-<span class="tabular">{{ $current }}</span> dari {{ $weeks }}</p>
        @endif
        @if ($milestones->isNotEmpty())
            <ul class="flex flex-wrap gap-x-4 gap-y-1" aria-label="Keterangan penanda">
                @foreach ($labels as $key => $label)
                    @continue(! $present->contains($key))
                    <li class="flex items-center gap-1.5"><span class="h-2 w-2 rotate-45 rounded-[2px] border {{ $dot[$key] }}" aria-hidden="true"></span>{{ $label }}</li>
                @endforeach
            </ul>
        @endif
    </div>

    @if ($list && $milestones->isNotEmpty())
        <div @if ($collapsible) x-data="disclosure" @endif class="mt-4 border-t border-rule pt-3">
            @if ($collapsible)
                <button type="button" class="link inline-flex min-h-[44px] items-center gap-1.5 text-sm" @click="toggle" :aria-expanded="expanded" aria-controls="tahap-{{ $period->id }}">
                    <span x-show="collapsed" class="inline-flex items-center gap-1.5">Lihat semua tahap ({{ $milestones->count() }}) <x-heroicon-o-chevron-down class="h-4 w-4" /></span>
                    <span x-show="expanded" x-cloak class="inline-flex items-center gap-1.5">Sembunyikan tahap <x-heroicon-o-chevron-up class="h-4 w-4" /></span>
                </button>
            @endif
            <ol id="tahap-{{ $period->id }}" @if ($collapsible) x-show="expanded" x-cloak @endif
                class="mt-1 gap-x-8 text-sm sm:columns-2 lg:columns-3 [&>li]:mb-2 [&>li]:break-inside-avoid">
                @foreach ($milestones as $m)
                    @php $s = $state($m); @endphp
                    <li class="flex min-w-0 items-baseline gap-2.5">
                        <span class="h-2 w-2 shrink-0 translate-y-[-1px] rotate-45 rounded-[2px] border {{ $dot[$s] }}" aria-hidden="true"></span>
                        <span class="w-12 shrink-0 whitespace-nowrap tabular text-caption text-muted">Mg {{ $weekOf($m) }}</span>
                        <span class="min-w-0 flex-1 truncate {{ $s === 'past' || $s === 'done' ? 'text-muted' : 'text-ink' }} {{ $s === 'open' ? 'font-medium' : '' }}" title="{{ $m->name }}">{{ $m->name }}<span class="sr-only"> — {{ $labels[$s] }}</span></span>
                        <span class="shrink-0 text-caption {{ $s === 'missed' ? 'text-bad-ink' : 'text-muted' }}">{{ $m->due_at->translatedFormat('j M') }}</span>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</div>
