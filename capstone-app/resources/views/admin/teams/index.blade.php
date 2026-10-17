<x-layouts.app title="Registrasi Tim" section="Admin · Registrasi tim">
    <x-page-head title="Registrasi tim" lede="Tinjau hasil screening otomatis, lampiran KHS dan domisili, lalu setujui atau tolak dengan catatan." />

    @include('admin._import', ['type' => 'tim', 'label' => 'tim', 'hint' => 'NIM harus sudah terdaftar. Tim hasil impor masuk status menunggu validasi, sama seperti tim yang dibuat mahasiswa.'])

    <nav class="mb-4 flex flex-wrap gap-2" aria-label="Filter status">
        @foreach (['' => 'Semua', 'menunggu' => 'Menunggu', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak', 'draft' => 'Draft'] as $k => $l)
            @php $active = (string) request('status') === $k; @endphp
            <a href="{{ route('admin.teams.index', array_filter(['status' => $k, 'prodi' => request('prodi')])) }}"
               class="btn btn-sm {{ $active ? 'btn-primary' : 'btn-secondary' }}" @if ($active) aria-current="page" @endif>
                {{ $l }} @if ($k !== '')<span class="tabular">{{ $counts[$k] ?? 0 }}</span>@endif
            </a>
        @endforeach
    </nav>

    <div class="panel">
        <div class="panel-head">
            <x-filter-bar :action="route('admin.teams.index')">
                <input type="hidden" name="status" value="{{ request('status') }}">
                <div><label class="field-label" for="q">Cari</label><input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Kode atau nama tim"></div>
                <div><label class="field-label" for="prodi">Prodi</label>
                    <select id="prodi" name="prodi" class="input"><option value="">Semua</option>@foreach ($prodis as $p)<option value="{{ $p->code }}" @selected(request('prodi') === $p->code)>{{ $p->code }}</option>@endforeach</select></div>
            </x-filter-bar>
        </div>
        @if ($teams->isEmpty())
            <x-empty title="Tidak ada tim" icon="user-group">Tim yang mendaftar akan muncul di sini.</x-empty>
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Tim</th><th>Anggota</th><th>Screening</th><th>Diajukan</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($teams as $team)
                            @php $flags = collect($team->screening ?? [])->where('passed', false); @endphp
                            <tr>
                                <td><p class="code font-medium">{{ $team->code }}</p><p class="text-sm">{{ $team->name }}</p></td>
                                <td class="text-sm">
                                    @foreach ($team->members as $m)
                                        <p class="{{ $m->invite_status !== 'diterima' ? 'text-muted' : '' }}">{{ $m->user->name }}@if ($m->is_leader) <span class="text-caption text-muted">(ketua)</span>@endif @if ($m->invite_status === 'diundang') <span class="text-caption">· diundang</span>@endif</p>
                                    @endforeach
                                </td>
                                <td>
                                    @if ($team->screening_score !== null)
                                        <p class="tabular {{ $team->screening_score === 100 ? 'text-ok-ink' : 'text-warn-ink' }}">{{ $team->screening_score }}/100</p>
                                        @if ($flags->isNotEmpty())<p class="text-caption text-bad-ink">{{ $flags->count() }} syarat belum terpenuhi</p>@endif
                                    @else
                                        <span class="text-muted">—</span>
                                    @endif
                                </td>
                                <td class="whitespace-nowrap text-sm">{{ $team->submitted_at?->translatedFormat('j M Y H:i') ?? '—' }}</td>
                                <td><x-badge :status="$team->status" /></td>
                                <td class="text-right"><a href="{{ route('admin.teams.show', $team) }}" class="btn btn-secondary btn-sm">{{ $team->status === 'menunggu' ? 'Tinjau' : 'Detail' }}</a></td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $teams->links() }}
        @endif
    </div>
</x-layouts.app>
