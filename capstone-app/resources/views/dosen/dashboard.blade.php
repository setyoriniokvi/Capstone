<x-layouts.app title="Dashboard Dosen" section="Dosen · Dashboard">
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
                @forelse ($profile?->accessLabels() ?? [] as $role)
                    <li class="rounded-md border border-on-brand-line bg-white/10 px-2.5 py-1 text-sm font-medium text-on-brand">{{ $role }}</li>
                @empty
                    <li class="text-sm text-on-brand-muted">Hak akses belum diatur</li>
                @endforelse
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
                Kuota: <span class="tabular font-semibold text-on-brand">{{ $quota['pembimbing']['default'] }}</span> tim bimbingan
                <span class="mx-1.5 text-on-brand-line" aria-hidden="true">|</span>
                <span class="tabular font-semibold text-on-brand">{{ $quota['penguji']['default'] }}</span> tim uji
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
        <x-capstone-timeline class="mb-6" :period="$period" />
    @endif

    {{-- Tim mahasiswa (bimbingan & uji): 3 tim dengan aktivitas terbaru; selengkapnya lewat "Lihat semua" --}}
    <section class="panel mb-6 overflow-hidden" aria-labelledby="judul-tim">
        <div class="flex items-center justify-between gap-3 px-5 py-4 sm:px-6">
            <h2 id="judul-tim" class="text-lg font-semibold text-ink">Tim Mahasiswa</h2>
            <a href="{{ route('dosen.teams.index') }}" class="link -my-2 inline-flex min-h-[44px] items-center gap-1.5 text-sm">Lihat semua <x-heroicon-o-arrow-right class="h-4 w-4" /></a>
        </div>
        @include('dosen._team-table', ['teams' => $recentTeams])
    </section>
</x-layouts.app>
