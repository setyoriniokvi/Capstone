{{-- Registrasi Tim admin: cari + filter status (dropdown), tombol Impor & Tambah Tim (modal), lalu satu kartu berisi
     tim yang menunggu approval dan, dipisah garis, riwayat keputusan (diterima/ditolak) — seperti Approval Pengajuan Topik
     dosen. Draf (belum diajukan) tidak ditampilkan. Tabel menunggu menampilkan rincian screening langsung, kotak centang
     per tim + "pilih semua" untuk "Setujui pilihan" (Alpine "bulkApproval"). Aksi per tim: Tolak (dengan catatan) & Terima,
     lalu "Detail tim" yang membuka modal admin/teams/_detail (waktu diajukan, peminatan, ubah tim, dll.). --}}
@php
    $statuses = ['menunggu' => 'Menunggu', 'diterima' => 'Diterima', 'ditolak' => 'Ditolak'];
    // Judul kolom hijau & sel selebar kartu (sama dengan dosen/applications)
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $cells = '[&_td]:break-words [&_td]:px-3 [&_th]:px-3 [&_td:first-child]:pl-5 [&_th:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th:last-child]:pr-5 sm:[&_td:first-child]:pl-6 sm:[&_th:first-child]:pl-6 sm:[&_td:last-child]:pr-6 sm:[&_th:last-child]:pr-6';
    $leaderChip = 'ml-1.5 rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted';
    $star = '<span class="text-bad-ink" aria-hidden="true"> *</span>';
    $creating = $errors->any() && old('_team') === 'baru';
@endphp
<x-layouts.app title="Approval Registrasi Tim">
    <x-page-head title="Approval Registrasi Tim" lede="Tinjau hasil screening otomatis, lampiran KHS, lalu terima atau tolak registrasinya.">
        <x-slot:actions>
            @include('admin._import', ['type' => 'tim', 'label' => 'tim', 'button' => true, 'hint' => 'NIM harus sudah terdaftar. Tim hasil impor masuk status menunggu validasi, sama seperti tim yang dibuat mahasiswa.'])
            {{-- Tambah tim: modal di atas halaman, semua isian wajib; terbuka lagi bila simpan gagal validasi. --}}
            <div x-data="registrationForm" data-open="{{ $creating ? 'true' : 'false' }}">
                <button type="button" class="btn btn-primary" @click="open" :aria-expanded="expanded" @disabled(! $period)><x-heroicon-o-plus class="h-5 w-5" />Tambah tim</button>
                @if ($period)
                    <x-modal width="sm:max-w-2xl">
                        <x-slot:heading>Tambah tim</x-slot:heading>
                        <form method="POST" action="{{ route('admin.teams.store') }}">
                            @csrf
                            <input type="hidden" name="_team" value="baru">
                            <div class="space-y-4">
                                <div>
                                    <label class="field-label" for="team-name">Nama tim{!! $star !!}</label>
                                    <input id="team-name" name="name" class="input" value="{{ $creating ? old('name') : '' }}" required maxlength="100">
                                    @if ($creating)@error('name')<p class="field-error">{{ $message }}</p>@enderror @endif
                                </div>
                                @for ($i = 0; $i < $teamSize['max']; $i++)
                                    @php $required = $i < $teamSize['min']; $selected = $creating ? old("members.$i") : null; @endphp
                                    <div>
                                        <label class="field-label" for="team-member-{{ $i }}">{{ $i === 0 ? 'Ketua' : 'Anggota '.($i + 1) }}{!! $required ? $star : ' <span class="font-normal text-muted">(bila ada)</span>' !!}</label>
                                        <select id="team-member-{{ $i }}" name="members[{{ $i }}]" class="input" @if ($required) required @endif>
                                            <option value="">Pilih mahasiswa</option>
                                            @foreach ($students as $student)
                                                <option value="{{ $student->id }}" @selected((string) $selected === (string) $student->id)>{{ $student->nim }} — {{ $student->name }}</option>
                                            @endforeach
                                        </select>
                                        @if ($creating)@error("members.$i")<p class="field-error">{{ $message }}</p>@enderror @endif
                                    </div>
                                @endfor
                                @if ($creating)@error('members')<p class="field-error">{{ $message }}</p>@enderror @endif
                                <p class="field-hint">Hanya mahasiswa aktif yang belum tergabung di tim pada periode ini. Tim baru masuk status Menunggu dan langsung di-screening.</p>
                            </div>
                            <div class="mt-6 flex justify-end gap-2">
                                <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                <button class="btn btn-primary" data-once>Tambah tim</button>
                            </div>
                        </form>
                    </x-modal>
                @endif
            </div>
        </x-slot:actions>
    </x-page-head>

    <div class="panel mb-6">
        <div class="panel-head">
            <x-filter-bar :action="route('admin.teams.index')">
                <div class="min-w-[12rem] flex-1"><label class="field-label" for="q">Cari</label><input id="q" name="q" value="{{ request('q') }}" class="input" placeholder="Nama tim, anggota, NIM"></div>
                <div><label class="field-label" for="status">Status</label>
                    <select id="status" name="status" class="input">
                        <option value="">Semua ({{ $counts->sum() }})</option>
                        @foreach ($statuses as $k => $l)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $l }} ({{ $counts[$k] ?? 0 }})</option>@endforeach
                    </select></div>
            </x-filter-bar>
        </div>
    </div>

    {{-- Menunggu approval, lalu riwayat keputusan (satu kartu, dipisah garis) --}}
    <section x-data="bulkApproval" class="panel overflow-hidden" aria-labelledby="registrasi-judul">
        <div class="flex items-center justify-between gap-3 px-5 py-4 sm:px-6">
            <h2 id="registrasi-judul" class="text-lg font-semibold text-ink">Menunggu Approval</h2>
            <span class="text-sm text-muted">Menunggu keputusan: <span class="tabular font-medium text-ink">{{ $pending->count() }}</span></span>
        </div>

        @if ($pending->isEmpty())
            <p class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada registrasi tim yang menunggu.</p>
        @else
            {{-- Setujui sekaligus: kotak centang baris terhubung ke formulir ini lewat atribut form --}}
            <form id="terima-pilihan" method="POST" action="{{ route('admin.teams.approve-many') }}" data-confirm="Terima semua registrasi tim yang dipilih? Email keputusan dikirim ke setiap anggota." data-confirm-label="Setujui" data-confirm-tone="positive"
                  class="flex flex-wrap items-center justify-between gap-3 border-t border-rule bg-sunken px-5 py-3 sm:px-6">
                @csrf
                <p class="text-sm text-muted"><span class="tabular font-medium text-ink" x-text="selected">0</span> tim dipilih</p>
                <button class="btn btn-primary btn-sm disabled:cursor-not-allowed disabled:opacity-50" :disabled="noneSelected" disabled title="Pilih tim terlebih dahulu">
                    <x-heroicon-o-check class="h-4 w-4" /> Setujui pilihan
                </button>
            </form>
            <table class="data-table w-full table-fixed border-t border-rule {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent" @change="pick">
                <colgroup><col class="w-[5%]"><col class="w-[14%]"><col class="w-[20%]"><col class="w-[45%]"><col class="w-[16%]"></colgroup>
                <thead class="{{ $greenHead }}"><tr>
                    <th><input type="checkbox" class="checkbox" data-select-all :checked="allSelected" aria-label="Pilih semua tim yang menunggu"></th>
                    <th>Tim</th><th>Anggota</th><th>Screening</th><th class="text-center">Aksi</th>
                </tr></thead>
                <tbody>
                    @foreach ($pending as $team)
                        <tr data-team="{{ $team->id }}" class="[&>td]:py-4 [&>td]:align-top">
                            <td><input type="checkbox" class="checkbox" form="terima-pilihan" name="teams[]" value="{{ $team->id }}" data-select-row aria-label="Pilih {{ $team->name }}"></td>
                            @include('admin.teams._row', ['fullScreening' => true])
                            <td class="!align-middle">
                                <div class="mx-auto flex max-w-[9rem] flex-col gap-2">
                                    <div class="grid grid-cols-2 gap-2">
                                        <form method="POST" action="{{ route('admin.teams.decide', $team) }}"
                                              data-confirm="Tolak registrasi {{ $team->name }}? Catatan di bawah akan dikirim ke anggota tim." data-confirm-label="Tolak" data-confirm-notes="notes">
                                            @csrf<input type="hidden" name="decision" value="reject">
                                            <button class="btn btn-secondary btn-sm w-full px-2 text-bad-ink">Tolak</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.teams.decide', $team) }}" data-confirm="Terima registrasi {{ $team->name }}? Email keputusan dikirim ke setiap anggota." data-confirm-label="Terima" data-confirm-tone="positive">
                                            @csrf<input type="hidden" name="decision" value="approve">
                                            <button class="btn btn-primary btn-sm w-full px-2">Terima</button>
                                        </form>
                                    </div>
                                    @include('admin.teams._detail-button', ['buttonClass' => 'w-full'])
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        {{-- Riwayat keputusan: 3 terbaru per halaman --}}
        <div class="border-t-2 border-rule">
            <h3 class="px-5 pb-2 pt-5 text-base font-semibold text-ink sm:px-6">Riwayat</h3>
            @if ($decided->isEmpty())
                <p class="px-5 pb-5 text-sm text-muted sm:px-6">Belum ada keputusan.</p>
            @else
                <table class="data-table w-full table-fixed {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent">
                    <colgroup><col class="w-[16%]"><col class="w-[22%]"><col class="w-[15%]"><col class="w-[11%]"><col class="w-[12%]"><col class="w-[12%]"><col class="w-[12%]"></colgroup>
                    <thead class="{{ $greenHead }}"><tr><th>Tim</th><th>Anggota</th><th>Screening</th><th>Keputusan</th><th>Waktu</th><th>Catatan</th><th class="text-center">Aksi</th></tr></thead>
                    <tbody>
                        @foreach ($decided as $team)
                            <tr class="[&>td]:align-top">
                                @include('admin.teams._row')
                                <td><x-badge class="!whitespace-normal" :status="$team->status" /></td>
                                <td class="text-sm text-ink">{{ $team->reviewed_at?->translatedFormat('j M Y, H:i') ?? '—' }}</td>
                                {{-- Catatan hanya ada pada penolakan (opsional); menerima tim tidak disertai catatan --}}
                                <td class="text-sm text-muted">{{ $team->status === 'ditolak' && filled($team->review_notes) ? $team->review_notes : '—' }}</td>
                                <td class="!align-middle text-center">@include('admin.teams._detail-button')</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <x-history-pager :paginator="$decided" />
            @endif
        </div>
    </section>

    @if ($extraTeam)
        {{-- Detail tim dari tautan ?tim= yang tidak ada di halaman/filter ini --}}
        <div x-data="registrationForm" data-open="true">
            @include('admin.teams._detail', ['team' => $extraTeam])
        </div>
    @endif
</x-layouts.app>
