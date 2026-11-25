{{-- F12 — Monitoring penilaian (admin). Baris filter: Cari (langsung di browser) · Sort · Peran · Status.
     Tabel: centang | Dosen (+ kontak ringkas) | Tim Uji | Tim Bimbing | Komponen Penilaian (sudah dinilai / jumlah tim per
     bentuk penilaian, sebaris) | Reminder terakhir. Sel kosong = "-" di tengah. Bawah tabel: kanal & Kirim reminder ke dosen
     terpilih, lalu "Tampilkan [10] data" (10 / 25 / 50 / 100 / Semua) + ‹ 1 2 3 ›. Cari, urut, Peran, Status, pilih & halaman berjalan di
     browser (Alpine "bulkApproval" + <x-sort-by>); dosen yang penilaiannya lengkap tidak dapat dipilih (centang nonaktif). --}}
@php
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $teamList = fn ($teams) => $teams->map(fn ($t) => '<span class="block">'.e($t->name).'</span>')->implode('');
    // Sel kosong: "-" di tengah (horizontal & vertikal)
    $emptyCell = 'text-center !align-middle text-muted';
@endphp
<x-layouts.app title="Penilaian & Reminder">
    <x-page-head title="Monitoring penilaian" />

    @if (! $period)
        <div class="panel"><x-empty title="Belum ada periode aktif" icon="calendar-days" /></div>
    @else
        <section x-data="bulkApproval" data-page-size="10" data-sort-scope @sorted="apply" class="panel overflow-hidden" aria-label="Monitoring penilaian dosen">
            {{-- Cari, Sort, Peran & Status semuanya langsung di browser (tanpa memuat ulang) --}}
            <div class="flex flex-wrap items-end gap-3 px-5 py-4 sm:px-6">
                <div class="min-w-[12rem] flex-1">
                    <label class="field-label" for="cari-dosen">Cari</label>
                    <div class="flex gap-2">
                        <input id="cari-dosen" type="search" class="input min-w-0 flex-1" placeholder="Nama dosen, email, tim" autocomplete="off" @input="search">
                        <x-sort-by id="urut-monitoring-nilai" default="nama" :options="['nama' => 'Dosen', 'terbaru' => 'Reminder terakhir', 'kategori' => 'Kategori (Status penilaian)']" />
                    </div>
                </div>
                <div><label class="field-label" for="peran">Peran</label>
                    <select id="peran" name="peran" data-filter="peran" class="input" @change="filter"><option value="">Semua</option><option value="pembimbing" @selected(request('peran') === 'pembimbing')>Pembimbing</option><option value="penguji" @selected(request('peran') === 'penguji')>Penguji</option></select></div>
                <div><label class="field-label" for="status">Status</label>
                    <select id="status" name="status" data-filter="status" class="input" @change="filter"><option value="">Semua</option><option value="belum" @selected(request('status') === 'belum')>Belum dinilai</option><option value="lengkap" @selected(request('status') === 'lengkap')>Sudah dinilai</option></select></div>
            </div>

            @if ($rows->isEmpty())
                <x-empty title="Belum ada penugasan dosen" icon="bell-alert">Plotting pembimbing dan penguji terlebih dahulu.</x-empty>
            @else
                <form method="POST" action="{{ route('admin.monitoring.remind') }}">
                    @csrf
                    <x-scroll-pager>
                        <table class="data-table w-full min-w-[60rem] border-t border-rule [&_td]:align-top">
                            <thead class="{{ $greenHead }}">
                                <tr>
                                    <th class="w-12"><input type="checkbox" class="checkbox" data-select-all :checked="allSelected" @change="pick" aria-label="Pilih semua dosen yang tampil"></th>
                                    <th>Dosen</th><th>Tim Uji</th><th>Tim Bimbing</th><th>Komponen Penilaian</th><th>Reminder terakhir</th><th><span class="sr-only">Aksi</span></th>
                                </tr>
                            </thead>
                            <tbody data-sort-list>
                                @foreach ($rows as $row)
                                    @php
                                        $l = $row['lecturer'];
                                        $sent = isset($lastSent[$l->id]) ? \Illuminate\Support\Carbon::parse($lastSent[$l->id]) : null;
                                        $search = mb_strtolower(implode(' ', [$l->name, $l->email, $row['teams']->pluck('name')->implode(' ')]));
                                    @endphp
                                    <tr data-team="{{ $l->id }}" data-search="{{ $search }}" data-sort-item data-sort-nama="{{ $l->name }}" data-sort-terbaru="{{ $sent?->timestamp }}" data-sort-kategori="{{ $row['complete'] ? 'Sudah dinilai' : 'Belum dinilai' }}"
                                        data-filter-peran="{{ $row['roles']->implode('|') }}" data-filter-status="{{ $row['complete'] ? 'lengkap' : 'belum' }}">
                                        <td><input type="checkbox" class="checkbox disabled:cursor-not-allowed disabled:opacity-50" name="lecturer_ids[]" value="{{ $l->id }}" data-select-row @change="pick"
                                                   aria-label="Pilih {{ $l->name }}" @disabled($row['complete']) @if ($row['complete']) title="Semua penilaian dosen ini sudah lengkap" @endif></td>
                                        <td>
                                            <p class="font-medium text-ink">{{ $l->name }}</p>
                                            <p class="text-caption text-muted">{{ $l->email }}</p>
                                            <p class="code text-caption {{ $l->waNumber() ? 'text-muted' : 'text-bad-ink' }}">{{ $l->waNumber() ?: 'WA kosong' }}</p>
                                        </td>
                                        @foreach ([$row['examTeams'], $row['superviseTeams']] as $teams)
                                            @if ($teams->isEmpty())<td class="{{ $emptyCell }}">-</td>@else<td class="text-sm">{!! $teamList($teams) !!}</td>@endif
                                        @endforeach
                                        <td class="text-sm">
                                            @foreach ($row['components'] as $componentKey => $c)
                                                <p class="whitespace-nowrap {{ $c['done'] >= $c['total'] ? 'text-ok-ink' : 'text-ink' }}">{{ config('capstone.components.'.$componentKey.'.label') }} <span class="tabular {{ $c['done'] >= $c['total'] ? '' : 'text-muted' }}">({{ $c['done'] }}/{{ $c['total'] }} tim)</span></p>
                                            @endforeach
                                        </td>
                                        @if ($sent)<td class="whitespace-nowrap text-sm text-muted">{{ $sent->diffForHumans() }}</td>@else<td class="{{ $emptyCell }}">-</td>@endif
                                        {{-- Detail: modal di atas halaman — nama dosen | peran, filter Tim Uji / Tim Bimbing, lalu
                                             Nama tim | Tim uji/bimbing | Assessment (sudah / belum dinilai per bentuk penilaian) --}}
                                        <td class="text-right !align-middle" x-data="registrationForm">
                                            <button type="button" class="btn btn-secondary btn-sm" @click="open" :aria-expanded="expanded">Detail<span class="sr-only"> {{ $l->name }}</span></button>
                                            <x-modal width="sm:max-w-4xl">
                                                <x-slot:heading>{{ $l->name }} <span class="font-normal text-muted">| {{ $row['roles']->map(fn ($r) => ucfirst($r))->implode(' & ') }}</span></x-slot:heading>
                                                <div x-data="listFilter" data-filter-own class="text-left">
                                                    <div class="mb-4 sm:w-56">
                                                        <label class="field-label" for="detail-peran-{{ $l->id }}">Tim</label>
                                                        <select id="detail-peran-{{ $l->id }}" data-filter="peran" class="input" @change="filter">
                                                            <option value="">Semua tim</option>
                                                            <option value="penguji">Tim Uji</option>
                                                            <option value="pembimbing">Tim Bimbing</option>
                                                        </select>
                                                    </div>
                                                    <table class="data-table w-full min-w-0 table-fixed border border-rule [&_td]:align-top [&_td]:text-sm [&_td]:break-words [&_tbody_tr:hover_td]:bg-transparent">
                                                        <colgroup><col class="w-[25%]"><col class="w-[18%]"><col></colgroup>
                                                        <thead class="{{ $greenHead }}"><tr><th>Nama tim</th><th>Tim uji / bimbing</th><th>Assessment</th></tr></thead>
                                                        <tbody>
                                                            @foreach ($row['assignments'] as $assignment)
                                                                <tr data-filter-item data-filter-peran="{{ $assignment['role'] }}">
                                                                    <td class="font-medium text-ink">{{ $assignment['team']->name }}</td>
                                                                    <td>{{ $assignment['role'] === 'penguji' ? 'Tim Uji' : 'Tim Bimbing' }}</td>
                                                                    <td>
                                                                        <ul class="space-y-1.5">
                                                                            @foreach ($assignment['components'] as $c)
                                                                                <li class="flex items-center justify-between gap-3">
                                                                                    <span class="min-w-0 text-ink">{{ config('capstone.components.'.$c['component'].'.label', $c['component']) }}</span>
                                                                                    @if ($c['done'])<x-badge class="shrink-0" status="lengkap" label="Sudah dinilai" />@else<x-badge class="shrink-0" status="belum" label="Belum dinilai" />@endif
                                                                                </li>
                                                                            @endforeach
                                                                        </ul>
                                                                    </td>
                                                                </tr>
                                                            @endforeach
                                                        </tbody>
                                                    </table>
                                                    <p x-show="empty" x-cloak class="mt-3 text-sm text-muted">Tidak ada tim untuk pilihan ini.</p>
                                                </div>
                                            </x-modal>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </x-scroll-pager>
                    <p x-show="empty" x-cloak class="border-t border-rule px-5 py-5 text-sm text-muted sm:px-6">Tidak ada dosen yang cocok dengan pencarian atau filter.</p>
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-rule bg-sunken px-4 py-3 sm:px-6">
                        <fieldset class="flex flex-wrap items-center gap-4">
                            <legend class="sr-only">Kanal</legend>
                            <label class="flex min-h-[44px] items-center gap-2"><input type="checkbox" class="checkbox" name="channels[]" value="email" checked>Email</label>
                            <label class="flex min-h-[44px] items-center gap-2"><input type="checkbox" class="checkbox" name="channels[]" value="whatsapp" checked>WhatsApp</label>
                        </fieldset>
                        <button class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50" data-once :disabled="noneSelected" title="Pilih dosen terlebih dahulu"><x-heroicon-o-paper-airplane class="h-5 w-5" />Kirim reminder ke dosen terpilih</button>
                    </div>
                </form>
                <x-client-pager id="per-halaman-dosen" label="Halaman monitoring penilaian" />
            @endif
        </section>
    @endif
</x-layouts.app>
