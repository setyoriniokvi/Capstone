{{-- Hasil screening terstruktur per syarat (tabel "Menunggu Approval" & modal Detail tim). Tiap syarat: ikon lulus/gagal
     + nama syarat, di bawahnya nilai setiap anggota berurutan (ketua paling kiri) dipisah "|":
     Jumlah anggota → nama · SKS lulus → SKS · tiap mata kuliah prasyarat → indeks nilai · Lampiran KHS → tombol KHS ·
     daerah asal / peminatan → isian anggota. Nilai anggota yang tidak memenuhi berwarna merah.
     Butuh $team (members.user dimuat; screening tersimpan). --}}
@php
    $checks = collect($team->screening ?? [])->keyBy('key');
    $members = $team->members->where('invite_status', 'diterima')->sortByDesc('is_leader')->values();
    $memberCheck = fn ($member, string $suffix) => $checks->get($member->user->nim.'_'.$suffix);
    // Syarat per anggota disusun dari kunci screening anggota pertama: sks, mk_<kode>, lampiran.
    $firstNim = $members->first()?->user->nim;
    $memberKeys = $checks->keys()->filter(fn ($k) => $firstNim && str_starts_with($k, $firstNim.'_'))
        ->map(fn ($k) => substr($k, strlen($firstNim) + 1))->values();
    $rows = collect();
    if ($checks->has('jumlah_anggota')) {
        $rows->push(['check' => $checks['jumlah_anggota'], 'label' => $checks['jumlah_anggota']['label'],
            'values' => $members->map(fn ($m) => ['text' => $m->user->name, 'passed' => true])]);
    }
    foreach ($memberKeys as $suffix) {
        $perMember = $members->map(fn ($m) => $memberCheck($m, $suffix));
        $label = \Illuminate\Support\Str::after($perMember->filter()->first()['label'] ?? $suffix, ' · ');
        $rows->push([
            'check' => ['passed' => $perMember->every(fn ($c) => $c && $c['passed'])],
            'label' => $label,
            'kind' => $suffix === 'lampiran' ? 'khs' : 'text',
            'values' => $members->map(fn ($m, $i) => [
                'member' => $m,
                'text' => match (true) {
                    ! $perMember[$i] => '—',
                    str_starts_with($suffix, 'mk_') => \Illuminate\Support\Str::after($perMember[$i]['detail'], 'Indeks nilai '),
                    default => $perMember[$i]['detail'],
                },
                'passed' => (bool) ($perMember[$i]['passed'] ?? false),
            ]),
        ]);
    }
    foreach (['daerah_asal' => 'origin_region', 'peminatan' => 'specialization'] as $key => $field) {
        if ($checks->has($key)) {
            $rows->push(['check' => $checks[$key], 'label' => $checks[$key]['label'],
                'values' => $members->map(fn ($m) => ['text' => $m->user->{$field} ?: '—', 'passed' => true])]);
        }
    }
@endphp
<ul class="space-y-3 text-sm">
    @foreach ($rows as $row)
        <li class="flex gap-2">
            @if ($row['check']['passed'])<x-heroicon-o-check-circle class="h-5 w-5 shrink-0 text-ok-ink" aria-label="Terpenuhi" />@else<x-heroicon-o-x-circle class="h-5 w-5 shrink-0 text-bad-ink" aria-label="Belum terpenuhi" />@endif
            <div class="min-w-0">
                <p class="{{ $row['check']['passed'] ? 'text-ink' : 'font-medium text-bad-ink' }}">{{ $row['label'] }}</p>
                <p class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-muted">
                    @foreach ($row['values'] as $value)
                        @if (! $loop->first)<span class="text-faint" aria-hidden="true">|</span>@endif
                        @if (($row['kind'] ?? 'text') === 'khs')
                            @if ($value['member']->khs_path)
                                <a href="{{ route('attachments.download', [$team, $value['member'], 'khs']) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm min-h-0 px-2.5 py-1">KHS {{ \Illuminate\Support\Str::before($value['member']->user->name, ' ') }}</a>
                            @else
                                <span class="font-medium text-bad-ink">{{ \Illuminate\Support\Str::before($value['member']->user->name, ' ') }}: belum ada</span>
                            @endif
                        @else
                            <span class="{{ $value['passed'] ? 'text-ink' : 'font-medium text-bad-ink' }}">{{ $value['text'] }}</span>
                        @endif
                    @endforeach
                </p>
            </div>
        </li>
    @endforeach
</ul>
