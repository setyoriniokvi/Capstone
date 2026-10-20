{{-- F21 / F24 — Tim Saya. Belum bertim: tombol "Registrasi Tim" membuka formulir di atas halaman.
     Sudah bertim: ringkasan tim → tabel anggota → hasil screening otomatis (aturan registrasi periode).
     Formulir ada di mahasiswa/_team-form.blade.php. --}}
<x-layouts.app title="Tim Saya" section="Mahasiswa · Tim saya">
    <div x-data="registrationForm" data-open="{{ $errors->any() && (! $team || $editable) ? 'true' : 'false' }}">
        <x-page-head title="Tim Saya" :lede="$team ? null : 'Anda belum tergabung dalam tim. Ketua mendaftarkan tim beserta seluruh anggotanya ('.$teamSize['min'].'–'.$teamSize['max'].' orang).'">
            <x-slot:actions>
                {{-- Tetap tampil setelah tim terdaftar, tetapi nonaktif --}}
                <button type="button" class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50" @click="toggle" aria-controls="form-registrasi"
                        @disabled($team) @if ($team) title="Tim Anda sudah terdaftar" @endif>
                    <x-heroicon-o-user-plus class="h-5 w-5" /> Registrasi Tim
                </button>
            </x-slot:actions>
        </x-page-head>

        @foreach (['team', 'window'] as $key)
            @error($key)<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror
        @endforeach

        @if (! $team || $editable)
            @include('mahasiswa._team-form')
        @endif

        @if (! $team)
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Prasyarat Anda</h2></div>
                <ul class="panel-body space-y-3">
                    @foreach ($eligibility as $check)
                        <li class="flex gap-3">
                            @if ($check['passed'])<x-heroicon-s-check-circle class="h-5 w-5 shrink-0 text-ok-ink" />@else<x-heroicon-s-x-circle class="h-5 w-5 shrink-0 text-bad-ink" />@endif
                            <span class="text-sm">{{ $check['label'] }}<span class="block text-caption text-muted">{{ $check['detail'] }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </section>
        @else
            @if ($team->status === 'ditolak')
                <div class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">
                    <p class="font-medium">Registrasi ditolak — perbaiki lalu ajukan kembali.</p>
                    @if ($team->review_notes)<p class="mt-1">Catatan admin: {{ $team->review_notes }}</p>@endif
                </div>
            @endif

            {{-- Ringkasan tim --}}
            @php
                $distinct = fn ($values) => $values->filter()->map(fn ($v) => mb_strtolower(trim($v)))->unique()->count();
                $stats = [
                    ['value' => $members->count(), 'suffix' => '/ '.$teamSize['max'], 'label' => 'Anggota'],
                    ['value' => $distinct($members->pluck('user.origin_region')), 'suffix' => null, 'label' => 'Daerah asal berbeda'],
                    ['value' => $distinct($members->pluck('user.specialization')), 'suffix' => null, 'label' => 'Peminatan berbeda'],
                ];
            @endphp
            <section class="panel mb-6 grid divide-y divide-rule sm:grid-cols-3 sm:divide-x sm:divide-y-0" aria-label="Ringkasan tim">
                @foreach ($stats as $stat)
                    <div class="px-5 py-5 sm:px-8 sm:py-6">
                        <p class="leading-none"><span class="tabular text-[2rem] font-bold tracking-tight text-brand">{{ $stat['value'] }}</span>@if ($stat['suffix'])<span class="tabular text-muted"> {{ $stat['suffix'] }}</span>@endif</p>
                        <p class="mt-2 text-sm font-medium text-ink">{{ $stat['label'] }}</p>
                    </div>
                @endforeach
            </section>

            {{-- Anggota tim --}}
            <section class="panel mb-6 overflow-hidden" aria-labelledby="anggota-judul">
                {{-- Nama tim (kiri) · status registrasi (tengah) · Edit (kanan; aktif bagi ketua selama menunggu/ditolak) --}}
                <div class="grid gap-3 border-b border-rule px-5 py-4 sm:grid-cols-[1fr_auto_1fr] sm:items-center sm:px-6">
                    <h2 id="anggota-judul" class="min-w-0 truncate text-lg font-semibold text-ink">{{ $team->name }}</h2>
                    <p class="flex items-center gap-2 text-sm text-muted">Status <x-badge :status="$team->status" :label="$team->statusLabel()" /></p>
                    <div class="sm:justify-self-end">
                        <button type="button" class="btn btn-secondary btn-sm disabled:cursor-not-allowed disabled:opacity-50" @click="toggle" aria-controls="form-registrasi"
                                @disabled(! $editable)
                                @unless ($editable) title="{{ $team->leader_id !== auth()->id() ? 'Hanya ketua tim yang dapat mengubah registrasi' : 'Registrasi yang sudah diterima tidak dapat diubah' }}" @endunless>
                            <x-heroicon-o-pencil-square class="h-4 w-4" /> Edit
                        </button>
                    </div>
                </div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead class="bg-brand-tint"><tr class="[&>th]:border-0 [&>th]:py-3.5 [&>th]:text-center [&>th]:text-sm [&>th]:font-semibold [&>th]:text-brand-deep"><th>Anggota</th><th>Peminatan</th><th>Daerah asal</th><th>Lampiran</th></tr></thead>
                        <tbody>
                            @foreach ($members as $m)
                                <tr>
                                    <td>
                                        <div class="flex items-center gap-3">
                                            <span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-brand-tint text-sm font-semibold text-brand-deep" aria-hidden="true">{{ $m->user->initials() }}</span>
                                            <div class="min-w-0">
                                                <p class="font-medium text-ink">{{ $m->user->name }}@if ($m->is_leader) <span class="ml-1.5 rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted">Ketua</span>@endif</p>
                                                <p class="text-sm text-muted"><span class="tabular">{{ $m->user->nim }}</span> · {{ $m->user->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="text-center text-sm">{{ $m->user->specialization ?? '—' }}</td>
                                    <td class="text-center text-sm">{{ $m->user->origin_region ?? '—' }}</td>
                                    <td class="text-center align-middle">
                                        @if ($m->khs_path)
                                            <a href="{{ route('attachments.download', [$team, $m, 'khs']) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">
                                                <x-heroicon-o-eye class="h-4 w-4" /> Lihat<span class="sr-only"> KHS {{ $m->user->name }}</span>
                                            </a>
                                        @else
                                            <span class="text-sm text-muted">Belum ada KHS</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>

            {{-- Hasil screening otomatis — aturan registrasi yang diatur admin pada periode --}}
            <section class="panel p-5 sm:p-6" aria-labelledby="screening-judul">
                <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                    <h2 id="screening-judul" class="text-lg font-semibold text-ink">Hasil screening otomatis</h2>
                    <p class="text-sm text-muted">Aturan periode {{ $period->academic_year }} {{ ucfirst($period->semester) }}</p>
                </div>
                <ul class="mt-4 space-y-4">
                    @foreach ($screening['checks'] as $check)
                        <li class="flex gap-3">
                            <span class="grid h-7 w-7 shrink-0 place-items-center rounded-full {{ $check['passed'] ? 'bg-ok-tint text-ok-ink' : 'bg-bad-tint text-bad-ink' }}">
                                @if ($check['passed'])<x-heroicon-s-check-circle class="h-4 w-4" />@else<x-heroicon-s-x-circle class="h-4 w-4" />@endif
                            </span>
                            <div class="min-w-0">
                                <p class="font-medium text-ink">{{ $check['label'] }}<span class="sr-only"> — {{ $check['passed'] ? 'terpenuhi' : 'belum terpenuhi' }}</span></p>
                                <p class="text-sm text-muted">{{ $check['detail'] }}</p>
                            </div>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif
    </div>
</x-layouts.app>
