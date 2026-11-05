{{-- Sel bersama baris Registrasi Tim (menunggu & riwayat): nama tim, anggota (ketua ditandai; tim ditolak dari log archived_members),
     dan screening — rincian lengkap tiap syarat bila $fullScreening (tabel menunggu), ringkasan saja di riwayat.
     Butuh $team, $leaderChip. --}}
@php $failed = collect($team->screening ?? [])->where('passed', false)->count(); @endphp
<td class="font-semibold text-ink">{{ $team->name }}</td>
<td class="text-sm">
    <ul class="space-y-1 text-ink">
        @foreach ($team->members as $m)
            <li class="{{ $m->invite_status !== 'diterima' ? 'text-muted' : '' }}">{{ $m->user->name }} @if ($m->is_leader)<span class="{{ $leaderChip }}">Ketua</span>@endif @if ($m->invite_status === 'diundang') <span class="text-caption">· diundang</span>@endif</li>
        @endforeach
        {{-- Tim ditolak sudah dibubarkan: tampilkan pendaftar dari log --}}
        @if ($team->members->isEmpty())
            @foreach ($team->archived_members ?? [] as $m)
                <li class="text-muted">{{ $m['name'] }} @if ($m['is_leader'])<span class="{{ $leaderChip }}">Ketua</span>@endif</li>
            @endforeach
        @endif
    </ul>
</td>
<td class="text-sm">
    @if ($team->screening === null)
        <span class="text-muted">—</span>
    @elseif ($fullScreening ?? false)
        <p class="mb-2 font-medium {{ $failed ? 'text-bad-ink' : 'text-ok-ink' }}">{{ $failed ? "{$failed} syarat belum terpenuhi" : 'Semua syarat terpenuhi' }}</p>
        @include('admin.teams._screening-list')
    @elseif ($failed)
        <span class="font-medium text-bad-ink">{{ $failed }} syarat belum terpenuhi</span>
    @else
        <span class="font-medium text-ok-ink">Syarat terpenuhi</span>
    @endif
</td>
