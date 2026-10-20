{{-- Dashboard awal admin (tanpa sidebar): kelola periode, lalu buka "Dashboard Periode" untuk mengelola isinya.
     Periode aktif = periode yang dipakai mahasiswa & dosen; membuka dashboard periode tidak mengubahnya. --}}
<x-layouts.app title="Kelola Periode" section="Admin · Kelola periode">
    <x-page-head title="Kelola Periode" lede="Atur periode, timeline, dan aturan registrasinya di sini. Buka dashboard periode untuk mengelola tim, topik, penilaian, dan data lain pada periode tersebut.">
        <x-slot:actions><a href="{{ route('admin.periods.create') }}" class="btn btn-primary"><x-heroicon-o-plus class="h-5 w-5" />Periode baru</a></x-slot:actions>
    </x-page-head>

    @if ($periods->isEmpty())
        <div class="panel"><x-empty title="Belum ada periode" icon="calendar-days">Buat periode pertama untuk memulai siklus Capstone.</x-empty></div>
    @else
        <div class="space-y-4">
            @foreach ($periods as $period)
                <article class="panel p-5 sm:p-6 {{ $period->is_active ? 'ring-2 ring-brand/30' : '' }}">
                    <div class="space-y-5">
                        {{-- Identitas & angka --}}
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <p class="text-sm text-muted"><span class="code">{{ $period->academic_year }}</span> · {{ ucfirst($period->semester) }}@if ($period->prodi_name) · {{ $period->prodi_name }}@endif</p>
                                @if ($period->is_active)<x-badge status="aktif" label="Periode aktif" />@else<x-badge status="netral" label="Tidak aktif" />@endif
                            </div>
                            <h2 class="mt-1 text-xl font-semibold leading-snug text-ink">{{ $period->name }}</h2>
                            <dl class="mt-4 grid grid-cols-1 gap-4 text-sm sm:grid-cols-3">
                                <div><dt class="text-muted">Rentang</dt><dd class="mt-0.5 whitespace-nowrap text-ink">{{ $period->starts_on->translatedFormat('j M Y') }} – {{ $period->ends_on->translatedFormat('j M Y') }}</dd></div>
                                <div><dt class="text-muted">Mahasiswa terdaftar</dt><dd class="mt-0.5 tabular text-lg font-semibold text-ink">{{ $studentCounts[$period->id] ?? 0 }}</dd></div>
                                <div><dt class="text-muted">Dosen terdaftar</dt><dd class="mt-0.5 tabular text-lg font-semibold text-ink">{{ $lecturerCounts[$period->id] ?? 0 }}</dd></div>
                            </dl>
                        </div>

                        {{-- Aksi: selebar kartu --}}
                        <div class="space-y-2 border-t border-rule pt-5">
                            <form method="POST" action="{{ route('admin.workspace.enter', $period) }}">
                                @csrf
                                <button class="btn btn-primary w-full"><x-heroicon-o-arrow-right-on-rectangle class="h-5 w-5" /> Buka Dashboard Periode</button>
                            </form>
                            <a href="{{ route('admin.periods.edit', $period) }}" class="btn btn-secondary w-full"><x-heroicon-o-cog-6-tooth class="h-5 w-5" /> Ubah Pengaturan Periode</a>
                            <div class="grid grid-cols-2 gap-2">
                                <form method="POST" action="{{ route('admin.periods.activate', $period) }}" data-confirm="Aktifkan {{ $period->name }} untuk mahasiswa dan dosen? Periode aktif lain akan dinonaktifkan." data-confirm-label="Aktifkan" data-confirm-tone="positive">
                                    @csrf
                                    <button class="btn btn-secondary btn-sm w-full text-ok-ink disabled:cursor-not-allowed disabled:opacity-50" @disabled($period->is_active) @if ($period->is_active) title="Periode ini sudah aktif" @endif>Aktifkan Periode</button>
                                </form>
                                <form method="POST" action="{{ route('admin.periods.deactivate', $period) }}" data-confirm="Nonaktifkan {{ $period->name }}? Mahasiswa dan dosen tidak memiliki periode aktif sampai periode lain diaktifkan." data-confirm-label="Nonaktifkan">
                                    @csrf
                                    <button class="btn btn-secondary btn-sm w-full text-bad-ink disabled:cursor-not-allowed disabled:opacity-50" @disabled(! $period->is_active) @unless ($period->is_active) title="Periode ini tidak aktif" @endunless>Nonaktifkan Periode</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </article>
            @endforeach
        </div>
    @endif
</x-layouts.app>
