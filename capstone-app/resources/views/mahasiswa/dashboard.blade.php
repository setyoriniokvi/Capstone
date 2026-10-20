{{-- F19 — dashboard mahasiswa. Data disusun oleh App\Services\StudentDashboard; view ini hanya menampilkan.
     Urutan: sambutan → ringkasan (log, dokumen, tenggat) → perlu dilakukan → timeline | tim → log bimbingan terbaru.
     Setiap blok ada di partial mahasiswa/_*.blade.php agar dapat diubah tanpa menyentuh blok lain.
     Hallmark · pre-emit critique: P4 H5 E4 S4 R5 V4 — tanpa eyebrow, ubin ikon, badge pojok, ornamen, atau kartu-dalam-kartu. --}}
@php $firstName = \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first(); @endphp
<x-layouts.app title="Dashboard Tim" section="Mahasiswa · Dashboard">
    @if (! $period)
        <x-page-head title="Halo, {{ $firstName }}" />
        <div class="panel"><x-empty title="Belum ada periode Capstone yang aktif" icon="calendar-days">Informasi timeline akan muncul setelah admin membuka periode.</x-empty></div>
    @else
        {{-- Sambutan + minggu berjalan --}}
        <section class="hero-panel mb-6 flex flex-col gap-5 sm:flex-row sm:items-end sm:justify-between" aria-labelledby="sambutan">
            <div class="min-w-0">
                <p class="text-sm text-on-brand-muted"><time datetime="{{ now()->toDateString() }}">{{ now()->translatedFormat('l, j F Y') }}</time></p>
                <h1 id="sambutan" class="mt-1 text-h1 font-semibold">Selamat datang, {{ $firstName }}.</h1>
                <p class="mt-2 max-w-[60ch] text-[0.9375rem] text-on-brand-muted">Pantau progres tim dan selesaikan tahapan Capstone tepat waktu.</p>
            </div>
            {{-- Minggu berjalan: angka tipografis dipisah garis tipis, bukan kotak di dalam panel --}}
            <p class="shrink-0 border-t border-on-brand-line pt-4 sm:border-l sm:border-t-0 sm:pl-6 sm:pt-0 sm:text-right">
                @if ($week['current'])
                    <span class="block text-sm text-on-brand-muted">Minggu ke</span>
                    <span class="tabular text-[2rem] font-semibold leading-none tracking-tight">{{ $week['current'] }}</span><span class="tabular text-on-brand-muted"> / {{ $week['total'] }}</span>
                @else
                    <span class="block text-sm text-on-brand-muted">Periode</span>
                    <span class="font-semibold">{{ $period->name }}</span>
                @endif
            </p>
        </section>

        @if ($team)
            @include('mahasiswa._summary-cards')
        @endif

        <x-todo-card class="mb-6" :actions="$actions" :waiting="$waiting" />

        <div class="mb-6 grid gap-6 {{ $team ? 'lg:grid-cols-[minmax(0,1.75fr)_minmax(0,1fr)]' : '' }}">
            <x-capstone-timeline id="timeline" :period="$period" />

            @if ($team)
                @include('mahasiswa._team-card')
            @endif
        </div>

        @if ($team)
            @include('mahasiswa._recent-logbooks')
        @endif
    @endif
</x-layouts.app>
