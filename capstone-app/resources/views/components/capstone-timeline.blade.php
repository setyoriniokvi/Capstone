{{-- Kartu "Timeline Capstone" (dashboard admin, dosen, mahasiswa): bulatan per minggu yang terisi
     sampai minggu berjalan, dan logo Telkom University di bulatan minggu berjalan.
     Minggu yang memiliki milestone diberi ikon dokumen; daftar milestone dilipat di bawah timeline.
     Rentang tanggal diambil dari tanggal mulai/selesai periode — diubah admin di halaman "Atur timeline".
     editHref : bila diisi (admin), tanggal di kanan atas menjadi tautan untuk mengubah periode. --}}
@props(['period', 'editHref' => null])
@php
    $weeks = $period->displayWeeks();
    $current = $period->currentWeek();
    // Di luar rentang periode: sebelum mulai semua kosong, setelah selesai semua terisi (tanpa logo).
    $reached = $current ?? (now()->greaterThan($period->ends_on->copy()->endOfDay()) ? $weeks + 1 : 0);
    // Milestone isian admin (halaman Atur timeline), urut minggu 1 → 16 lalu tenggat.
    $milestones = $period->milestones
        ->map(fn ($m) => ['milestone' => $m, 'week' => $period->weekOfMilestone($m)])
        ->sortBy([['week', 'asc'], fn ($a, $b) => $a['milestone']->due_at <=> $b['milestone']->due_at])
        ->values();
    $milestoneWeeks = $milestones->pluck('week')->unique();
    $sameYear = $period->starts_on->year === $period->ends_on->year;
    $range = $period->starts_on->translatedFormat($sameYear ? 'j M' : 'j M Y').' – '.$period->ends_on->translatedFormat('j M Y');
@endphp
<section {{ $attributes->merge(['class' => 'panel min-w-0 p-5 sm:p-6']) }} aria-labelledby="timeline-{{ $period->id }}">
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 id="timeline-{{ $period->id }}" class="text-lg font-semibold text-ink">Timeline Capstone</h2>
        @if ($editHref)
            <a href="{{ $editHref }}" class="group inline-flex items-center gap-1.5 text-sm text-muted hover:text-ink" title="Ubah tanggal timeline">
                <span class="tabular">{{ $range }}</span>
                <x-heroicon-o-pencil-square class="h-4 w-4 text-faint group-hover:text-brand" />
                <span class="sr-only">Ubah tanggal timeline</span>
            </a>
        @else
            <p class="tabular text-sm text-muted">{{ $range }}</p>
        @endif
    </div>
    <p class="sr-only">{{ $current ? "Minggu ke-{$current} dari {$weeks}" : ($reached ? 'Periode telah selesai' : 'Periode belum dimulai') }}</p>

    {{-- Satu kolom per minggu: selalu muat lebar layar tanpa scroll samping. --}}
    <ol class="mt-6 grid" style="grid-template-columns: repeat({{ $weeks }}, minmax(0, 1fr))" aria-label="Timeline {{ $weeks }} minggu">
        @for ($w = 1; $w <= $weeks; $w++)
            @php $isNow = $current === $w; $done = $w < $reached; $hasMilestone = $milestoneWeeks->contains($w); @endphp
            <li class="flex flex-col items-center" @if ($isNow) aria-current="step" @endif>
                <span class="relative flex h-8 w-full items-center justify-center sm:h-9" aria-hidden="true">
                    {{-- Garis penghubung: separuh kiri & kanan tiap kolom, hijau sampai minggu berjalan. --}}
                    @if ($w > 1)
                        <span class="absolute left-0 right-1/2 top-1/2 h-[3px] -translate-y-1/2 {{ $w <= $reached ? 'bg-brand' : 'bg-rule-strong' }}"></span>
                    @endif
                    @if ($w < $weeks)
                        <span class="absolute left-1/2 right-0 top-1/2 h-[3px] -translate-y-1/2 {{ $w < $reached ? 'bg-brand' : 'bg-rule-strong' }}"></span>
                    @endif

                    @if ($isNow)
                        <span class="relative grid h-7 w-7 shrink-0 place-items-center rounded-full bg-brand p-[3px] ring-4 ring-brand-tint sm:h-9 sm:w-9">
                            <span class="grid h-full w-full place-items-center rounded-full bg-white">
                                <img src="{{ Vite::asset('resources/images/telu-mark.png') }}" alt="" class="h-[70%] w-[70%] object-contain" width="64" height="64">
                            </span>
                        </span>
                    @else
                        <span class="relative grid h-3.5 w-3.5 shrink-0 place-items-center rounded-full sm:h-5 sm:w-5 {{ $done ? 'bg-brand text-white' : 'border-2 border-rule-strong bg-surface text-muted' }}">
                            @if ($hasMilestone)
                                <x-heroicon-s-document-text class="h-2 w-2 sm:h-2.5 sm:w-2.5" />
                            @endif
                        </span>
                    @endif
                </span>
                <span class="mt-1.5 tabular text-[0.6875rem] sm:text-caption {{ $isNow ? 'font-semibold text-ink' : 'text-muted' }}">
                    {{ $w }}<span class="sr-only"> — {{ $isNow ? 'minggu berjalan' : ($done ? 'sudah lewat' : 'mendatang') }}{{ $hasMilestone ? ', ada milestone' : '' }}</span>
                </span>
            </li>
        @endfor
    </ol>

    @if ($milestones->isNotEmpty())
        <div x-data="disclosure" class="mt-5 border-t border-rule pt-2">
            <button type="button" class="link inline-flex min-h-[44px] items-center gap-1.5 text-sm" @click="toggle" :aria-expanded="expanded" aria-controls="milestone-{{ $period->id }}">
                <span x-show="collapsed" class="inline-flex items-center gap-1.5">Lihat semua milestone ({{ $milestones->count() }}) <x-heroicon-o-chevron-down class="h-4 w-4" /></span>
                <span x-show="expanded" x-cloak class="inline-flex items-center gap-1.5">Sembunyikan milestone <x-heroicon-o-chevron-up class="h-4 w-4" /></span>
            </button>
            <ol id="milestone-{{ $period->id }}" x-show="expanded" x-cloak class="divide-y divide-rule">
                @foreach ($milestones as ['milestone' => $m, 'week' => $mWeek])
                    <li class="flex min-w-0 items-center gap-3 py-2.5 text-sm">
                        <span class="w-20 shrink-0 tabular text-muted">Minggu {{ $mWeek }}</span>
                        <span class="min-w-0 flex-1 truncate text-ink" title="{{ $m->name }}">{{ $m->name }}</span>
                        <time datetime="{{ $m->due_at->toDateString() }}" class="shrink-0 whitespace-nowrap tabular text-ink">{{ $m->due_at->translatedFormat('j F') }}</time>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</section>
