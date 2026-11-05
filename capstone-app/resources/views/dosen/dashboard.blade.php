<x-layouts.app title="Dashboard Dosen">
    {{-- Sambutan (sama dengan dashboard mahasiswa) + minggu berjalan --}}
    @php $week = $period?->currentWeek(); @endphp
    {{-- Kartu hijau dengan "tab" menjorok di kiri bawah berisi kuota --}}
    <div class="mb-6">
    <section class="hero-panel flex flex-col gap-5 {{ $quota ? 'rounded-bl-none' : '' }} sm:flex-row sm:items-end sm:justify-between" aria-labelledby="sambutan">
        <div class="min-w-0">
            <p class="text-sm text-on-brand-muted"><time datetime="{{ now()->toDateString() }}">{{ now()->translatedFormat('l, j F Y') }}</time></p>
            <h1 id="sambutan" class="mt-1 text-h1 font-semibold">Selamat datang, Bapak/Ibu {{ $profile?->lecturer_code ?? auth()->user()->name }}.</h1>
            {{-- Peran dosen sebagai kotak-kotak kecil --}}
            <ul class="mt-3 flex flex-wrap gap-2" aria-label="Peran Anda">
                @foreach ($profile?->accessLabels() ?? ['Dosen'] as $role)
                    <li class="rounded-md border border-on-brand-line bg-white/10 px-2.5 py-1 text-sm font-medium text-on-brand">{{ $role }}</li>
                @endforeach
            </ul>
        </div>
        <p class="shrink-0 border-t border-on-brand-line pt-4 sm:border-l sm:border-t-0 sm:pl-6 sm:pt-0 sm:text-right">
            @if ($week)
                <span class="block text-sm text-on-brand-muted">Minggu ke</span>
                <span class="tabular text-[2rem] font-semibold leading-none tracking-tight">{{ $week }}</span><span class="tabular text-on-brand-muted"> / {{ $period->displayWeeks() }}</span>
            @else
                <span class="block text-sm text-on-brand-muted">Periode</span>
                <span class="font-semibold">{{ $period?->name ?? 'Belum ada periode aktif' }}</span>
            @endif
        </p>
    </section>
    @if ($quota)
        <div class="relative inline-flex">
            <p class="rounded-bl-lg bg-brand-deep pb-3 pl-5 pr-1 pt-1 text-sm text-on-brand-muted sm:pl-8">
                Kuota: <span class="tabular font-semibold text-on-brand">{{ $quota['pembimbing']['max'] }}</span> tim bimbingan
                <span class="mx-1.5 text-on-brand-line" aria-hidden="true">|</span>
                <span class="tabular font-semibold text-on-brand">{{ $quota['penguji']['max'] }}</span> tim uji
            </p>
            {{-- Sisi kanan tab melengkung miring seperti "/" (kurva S) ke sisi bawah kartu --}}
            <svg class="pointer-events-none absolute left-full top-0 h-full w-12 fill-brand-deep" viewBox="0 0 48 40" preserveAspectRatio="none" aria-hidden="true">
                <path d="M0 0 H48 C30 0 26 40 6 40 H0 Z" />
            </svg>
        </div>
    @endif
    </div>

    {{-- Ringkasan (bukan tautan) --}}
    <x-stat-row class="mb-6" :stats="[
        ['value' => $supervisedCount, 'label' => 'Tim bimbingan'],
        ['value' => $examinedCount, 'label' => 'Tim uji'],
        ['value' => $counts['logbooks'], 'label' => 'Log menunggu'],
        ['value' => $counts['assessments'], 'label' => 'Nilai belum diisi'],
    ]" />

    <x-todo-card class="mb-6" :actions="$todo" />

    @if ($period)
        <x-capstone-timeline class="mb-6" :period="$period" with-assessments />
    @endif

    {{-- Pengingat penilaian: per bentuk penilaian (judul + jendela penilaian), tim yang belum lengkap dinilai beserta peran &
         statusnya; tombol ">" di ujung kanan membuka formulir penilaian tim itu. Daftar lengkap tampil di sini (tanpa "Lihat semua"). --}}
    <section class="panel mb-6 overflow-hidden" aria-labelledby="judul-pengingat">
        <div class="flex items-center justify-between gap-3 border-b border-rule px-5 py-4 sm:px-6">
            <h2 id="judul-pengingat" class="text-lg font-semibold text-ink">Pengingat Penilaian</h2>
        </div>
        @if ($reminderGroups->isEmpty())
            <x-empty title="Tidak ada penilaian yang tertunda" icon="check-circle">Semua tim bimbingan dan uji Anda sudah lengkap dinilai.</x-empty>
        @else
            <div class="divide-y divide-rule">
                @foreach ($reminderGroups as $component => $rows)
                    @php $w = $windows->get($component); @endphp
                    <section aria-labelledby="pengingat-{{ $component }}">
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 bg-sunken px-5 py-2.5 sm:px-6">
                            <h3 id="pengingat-{{ $component }}" class="font-semibold text-ink">{{ config("capstone.components.{$component}.label") }}
                                <span class="ml-1 text-sm font-normal text-muted">· <span class="tabular">{{ $rows->count() }}</span> tim belum dinilai</span></h3>
                            @if ($w)
                                <p class="text-caption {{ $w->isOpen() ? 'text-ok-ink' : 'text-muted' }}">
                                    {{ $w->isOpen() ? 'Dibuka' : (now()->lt($w->opens_at) ? 'Belum dibuka' : 'Ditutup') }} · {{ $w->opens_at->translatedFormat('j M') }} – {{ $w->due_at->translatedFormat('j M Y, H:i') }}
                                </p>
                            @endif
                        </div>
                        <ul class="divide-y divide-rule">
                            @foreach ($rows as $row)
                                @php $isPartial = $partial->has($row['team']->id.':'.$component); @endphp
                                <li>
                                    <a href="{{ route('dosen.assessments.edit', [$row['team'], $component]) }}" class="group flex items-center gap-4 px-5 py-3 hover:bg-sunken focus-visible:bg-sunken sm:px-6">
                                        <span class="min-w-0 flex-1">
                                            <span class="block font-medium text-ink">{{ $row['team']->name }}</span>
                                            <span class="block text-caption capitalize text-muted">Sebagai {{ $row['role'] }}</span>
                                        </span>
                                        <x-badge :status="$isPartial ? 'draft' : 'belum'" :label="$isPartial ? 'Sebagian dinilai' : 'Belum dinilai'" />
                                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full text-faint group-hover:bg-surface group-hover:text-ink" aria-hidden="true"><x-heroicon-o-chevron-right class="h-5 w-5" /></span>
                                        <span class="sr-only">Nilai {{ config("capstone.components.{$component}.label") }} {{ $row['team']->name }}</span>
                                    </a>
                                </li>
                            @endforeach
                        </ul>
                    </section>
                @endforeach
            </div>
        @endif
    </section>
</x-layouts.app>
