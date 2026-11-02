{{-- Kartu "Timeline Capstone" (dashboard admin, dosen, mahasiswa): bulatan per minggu yang terisi
     sampai minggu berjalan, dan logo Telkom University di bulatan minggu berjalan.
     Minggu yang memiliki milestone diberi ikon dokumen (deadline penilaian dosen: ikon pensil); daftar milestone dilipat di bawah timeline.
     Rentang tanggal diambil dari tanggal mulai/selesai periode — diubah admin di halaman Kelola Periode.
     withAssessments : (dashboard dosen & admin) tambahkan deadline pengisian penilaian dosen dari jendela penilaian
                       periode (admin → Periode → Jendela penilaian), berikon pensil, di bulatan, tooltip, dan daftar.
     list            : false = tanpa daftar milestone yang dilipat (halaman kelola periode sudah punya daftarnya).
     boldAssessments : false = deadline penilaian di daftar memakai huruf biasa seperti milestone lain (dashboard admin). --}}
@props(['period', 'withAssessments' => false, 'boldAssessments' => true, 'list' => true])
@php
    $weeks = $period->displayWeeks();
    $current = $period->currentWeek();
    // Di luar rentang periode: sebelum mulai semua kosong, setelah selesai semua terisi (tanpa logo).
    $reached = $current ?? (now()->greaterThan($period->ends_on->copy()->endOfDay()) ? $weeks + 1 : 0);
    // Milestone isian admin (halaman Kelola Periode), ditambah deadline penilaian dosen bila withAssessments
    // (tanpa peer review — itu tugas mahasiswa); urut minggu 1 → 16 lalu deadline.
    $milestones = $period->milestones
        ->map(fn ($m) => ['name' => $m->name, 'label' => $m->kindLabel(), 'due' => $m->due_at, 'week' => $period->weekOfMilestone($m), 'assessment' => false])
        ->when($withAssessments, fn ($items) => $items->concat(
            $period->assessmentWindows
                ->filter(fn ($w) => ! in_array('mahasiswa', \App\Models\AssessmentComponent::rolesOf($w->component), true))
                ->map(fn ($w) => ['name' => 'Deadline Penilaian '.$w->label(), 'label' => 'Penilaian dosen', 'due' => $w->due_at,
                    'week' => min($weeks, $period->weekOf($w->due_at)), 'assessment' => true])
        ))
        ->sortBy([['week', 'asc'], fn ($a, $b) => $a['due'] <=> $b['due']])
        ->values();
    $milestonesByWeek = $milestones->groupBy('week');
    $sameYear = $period->starts_on->year === $period->ends_on->year;
    $range = $period->starts_on->translatedFormat($sameYear ? 'j M' : 'j M Y').' – '.$period->ends_on->translatedFormat('j M Y');
@endphp
<section {{ $attributes->merge(['class' => 'panel min-w-0 p-5 sm:p-6']) }} aria-labelledby="timeline-{{ $period->id }}">
    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
        <h2 id="timeline-{{ $period->id }}" class="text-lg font-semibold text-ink">Timeline Capstone</h2>
        <p class="tabular text-sm text-muted">{{ $range }}</p>
    </div>
    <p class="sr-only">{{ $current ? "Minggu ke-{$current} dari {$weeks}" : ($reached ? 'Periode telah selesai' : 'Periode belum dimulai') }}</p>

    {{-- Satu kolom per minggu: selalu muat lebar layar tanpa scroll samping. --}}
    <ol class="mt-6 grid" style="grid-template-columns: repeat({{ $weeks }}, minmax(0, 1fr))" aria-label="Timeline {{ $weeks }} minggu">
        @for ($w = 1; $w <= $weeks; $w++)
            @php
                $isNow = $current === $w; $done = $w < $reached;
                $weekItems = $milestonesByWeek->get($w, collect());
                $hasMilestone = $weekItems->isNotEmpty();
                $hasAssessment = $weekItems->contains('assessment', true);
                $weekStart = $period->starts_on->copy()->addDays(($w - 1) * 7);
                $weekEnd = $weekStart->copy()->addDays(6);
                // Tooltip di tepi kiri/kanan diratakan ke sisi kolom agar tidak keluar kartu.
                $tipAlign = $w <= 3 ? 'left-0' : ($w > $weeks - 3 ? 'right-0' : 'left-1/2 -translate-x-1/2');
            @endphp
            <li class="group relative flex flex-col items-center rounded-md outline-none focus-visible:ring-2 focus-visible:ring-brand" tabindex="0" aria-describedby="week-tip-{{ $period->id }}-{{ $w }}" @if ($isNow) aria-current="step" @endif>
                {{-- Tooltip hover/fokus: aktivitas & deadline minggu ini dari milestone isian admin. --}}
                <div id="week-tip-{{ $period->id }}-{{ $w }}" role="tooltip"
                     class="pointer-events-none invisible absolute bottom-full z-20 mb-2 w-56 rounded-md border border-rule bg-surface p-3 text-left opacity-0 shadow-panel transition-opacity duration-150 group-hover:visible group-hover:opacity-100 group-focus-visible:visible group-focus-visible:opacity-100 {{ $tipAlign }}">
                    <p class="text-sm font-semibold text-ink">Minggu {{ $w }}</p>
                    <p class="tabular text-caption text-muted">{{ $weekStart->translatedFormat('j M') }} – {{ $weekEnd->translatedFormat('j M Y') }}</p>
                    @if ($hasMilestone)
                        <ul class="mt-2 space-y-1.5">
                            @foreach ($weekItems as $item)
                                <li class="text-sm">
                                    <span class="flex items-start gap-1.5 font-medium text-ink">
                                        @if ($item['assessment'])<x-heroicon-s-clipboard-document-check class="mt-0.5 h-3.5 w-3.5 shrink-0" />@endif
                                        {{ $item['name'] }}
                                    </span>
                                    <span class="block tabular text-caption text-muted">{{ $item['label'] }} · deadline {{ $item['due']->translatedFormat('j M, H:i') }}</span>
                                </li>
                            @endforeach
                        </ul>
                    @else
                        <p class="mt-2 text-sm text-muted">Tidak ada aktivitas atau deadline.</p>
                    @endif
                </div>
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
                            @if ($hasAssessment)
                                <x-heroicon-s-clipboard-document-check class="h-2 w-2 sm:h-2.5 sm:w-2.5" />
                            @elseif ($hasMilestone)
                                <x-heroicon-s-document-text class="h-2 w-2 sm:h-2.5 sm:w-2.5" />
                            @endif
                        </span>
                    @endif
                </span>
                <span class="mt-1.5 tabular text-[0.6875rem] sm:text-caption {{ $isNow ? 'font-semibold text-ink' : 'text-muted' }}">
                    {{ $w }}<span class="sr-only"> — {{ $isNow ? 'minggu berjalan' : ($done ? 'sudah lewat' : 'mendatang') }}{{ $hasMilestone ? ', ada milestone' : '' }}{{ $hasAssessment ? ', ada deadline penilaian dosen' : '' }}</span>
                </span>
            </li>
        @endfor
    </ol>

    @if ($withAssessments)
        {{-- Keterangan ikon bulatan --}}
        <ul class="mt-4 flex flex-wrap gap-x-5 gap-y-1 text-caption text-muted" aria-label="Keterangan ikon timeline">
            <li class="inline-flex items-center gap-1.5"><x-heroicon-s-document-text class="h-3.5 w-3.5" aria-hidden="true" />Milestone</li>
            <li class="inline-flex items-center gap-1.5"><x-heroicon-s-clipboard-document-check class="h-3.5 w-3.5" aria-hidden="true" />Deadline penilaian dosen</li>
        </ul>
    @endif

    @if ($list && $milestones->isNotEmpty())
        <div x-data="disclosure" class="mt-5 border-t border-rule pt-2">
            <button type="button" class="link inline-flex min-h-[44px] items-center gap-1.5 text-sm" @click="toggle" :aria-expanded="expanded" aria-controls="milestone-{{ $period->id }}">
                <span x-show="collapsed" class="inline-flex items-center gap-1.5">Lihat semua {{ $withAssessments ? 'milestone & deadline penilaian' : 'milestone' }} ({{ $milestones->count() }}) <x-heroicon-o-chevron-down class="h-4 w-4" /></span>
                <span x-show="expanded" x-cloak class="inline-flex items-center gap-1.5">Sembunyikan {{ $withAssessments ? 'daftar' : 'milestone' }} <x-heroicon-o-chevron-up class="h-4 w-4" /></span>
            </button>
            <ol id="milestone-{{ $period->id }}" x-show="expanded" x-cloak class="divide-y divide-rule">
                @foreach ($milestones as $item)
                    <li class="flex min-w-0 items-center gap-3 py-2.5 text-sm">
                        <span class="w-20 shrink-0 tabular text-muted">Minggu {{ $item['week'] }}</span>
                        <span class="flex min-w-0 flex-1 items-center gap-1.5 text-ink {{ $item['assessment'] && $boldAssessments ? 'font-semibold' : '' }}">
                            @if ($item['assessment'])<x-heroicon-s-clipboard-document-check class="h-4 w-4 shrink-0" aria-hidden="true" /><span class="sr-only">Deadline penilaian dosen:</span>@endif
                            <span class="truncate" title="{{ $item['name'] }}">{{ $item['name'] }}</span>
                        </span>
                        <time datetime="{{ $item['due']->toDateString() }}" class="shrink-0 whitespace-nowrap tabular text-ink {{ $item['assessment'] && $boldAssessments ? 'font-semibold' : '' }}">{{ $item['due']->translatedFormat('j F') }}</time>
                    </li>
                @endforeach
            </ol>
        </div>
    @endif
</section>
