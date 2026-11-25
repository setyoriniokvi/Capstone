{{-- Modal detail tim (Registrasi Tim admin, tombol "Detail tim"): nama tim, keputusan registrasi (Terima / Tolak dengan
     konfirmasi + catatan opsional untuk mahasiswa; tim yang sudah diputuskan hanya informasi), kartu Anggota (KHS),
     lalu hasil screening otomatis. Peminatan anggota dapat diisi/diubah langsung di kartu Anggota.
     Bagian "Ubah tim" & tombol "Hapus tim" = CRUD manual admin (mahasiswa tidak dapat mengubah timnya).
     Bagian keputusan + Ubah/Hapus tim = admin/teams/_manage, kartu Anggota = admin/teams/_members (dipakai juga di Detail tim
     Monitoring progres). Memakai state registrationForm dari elemen induk. Butuh $team (members.user), $courses, $specializations,
     $students, $teamSize. --}}
@php
    $card = 'rounded-lg border border-rule';
    $cardHead = 'border-b border-rule px-4 py-3 text-base font-semibold text-ink sm:px-5';
    $checks = collect($team->screening ?? []);
    $failed = $checks->where('passed', false)->count();
@endphp
<x-modal width="sm:max-w-3xl" class="text-left font-normal">
    <x-slot:heading>{{ $team->name }}</x-slot:heading>
    <div class="space-y-5 text-left text-body">
        @include('admin.teams._manage')

        {{-- Tim ditolak sudah dibubarkan (akun registrasi & KHS dihapus): hanya log data pendaftar --}}
        @if ($team->members->isEmpty() && $team->archived_members)
            <section class="{{ $card }}" aria-label="Pendaftar {{ $team->name }}">
                <h3 class="{{ $cardHead }}">Pendaftar <span class="text-sm font-normal text-muted">· log registrasi, tim sudah dibubarkan</span></h3>
                <ul class="divide-y divide-rule">
                    @foreach ($team->archived_members as $m)
                        <li class="px-4 py-4 sm:px-5">
                            <p class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-ink">{{ $m['name'] }}</span>
                                <span class="inline-block whitespace-nowrap rounded-md px-1.5 py-0.5 text-caption font-medium {{ $m['is_leader'] ? 'bg-brand-tint text-brand-deep' : 'bg-sunken text-muted' }}">{{ $m['is_leader'] ? 'Ketua' : 'Anggota' }}</span>
                            </p>
                            <dl class="mt-2 grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm">
                                <dt class="text-muted">NIM</dt><dd class="code text-ink">{{ $m['nim'] ?? '—' }}</dd>
                                <dt class="text-muted">Email</dt><dd class="min-w-0 break-all text-ink">{{ $m['email'] }}</dd>
                                <dt class="text-muted">SKS lulus</dt><dd class="tabular text-ink">{{ $m['sks_passed'] ?? '—' }}</dd>
                                @foreach ($m['grades'] ?? [] as $code => $grade)
                                    <dt class="text-muted">Nilai {{ $code }}</dt><dd class="text-ink">{{ $grade }}</dd>
                                @endforeach
                                <dt class="text-muted">Peminatan</dt><dd class="text-ink">{{ $m['specialization'] ?? '—' }}</dd>
                                <dt class="text-muted">Asal daerah</dt><dd class="text-ink">{{ $m['origin_region'] ?? '-' }}@if (filled($m['origin_region']) && ! \App\Support\Regions::isListed($m['origin_region']))<span class="ml-1.5 inline-block rounded-md border border-warn-line bg-warn-tint px-1.5 py-0.5 text-caption font-medium text-warn-ink" title="Tidak ada di daftar kabupaten/kota; periksa ejaannya">diisi manual</span>@endif</dd>
                            </dl>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        @include('admin.teams._members')

        {{-- Hasil screening otomatis (dihitung dari persyaratan periode saat tim diajukan) --}}
        <section class="{{ $card }}" aria-label="Hasil screening {{ $team->name }}">
            <h3 class="{{ $cardHead }}">Hasil Screening</h3>
            <div class="px-4 py-4 sm:px-5">
                @if ($checks->isEmpty())
                    <p class="text-sm text-muted">Screening dijalankan saat tim mengajukan registrasi.</p>
                @else
                    <p class="text-sm font-medium {{ $failed ? 'text-bad-ink' : 'text-ok-ink' }}">{{ $failed ? "{$failed} syarat belum terpenuhi" : 'Semua syarat terpenuhi' }}</p>
                    <div class="mt-3">@include('admin.teams._screening-list')</div>
                @endif
            </div>
        </section>
    </div>
</x-modal>
