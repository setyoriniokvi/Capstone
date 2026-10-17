<x-layouts.app title="Plotting Dosen" section="Admin · Plotting">
    <x-page-head title="Plotting pembimbing & penguji" lede="Flag OVER/UNDER bersifat informatif dan tidak memblokir plotting. Pilihan dosen ditandai hijau bila masih tersedia dan merah bila sudah mencapai batas maksimum." />

    @if (! $period)
        <div class="panel"><x-empty title="Belum ada periode aktif" icon="calendar-days" /></div>
    @else
        <section class="panel mb-6">
            <div class="panel-head">
                <h2 class="panel-title">Load balancing dosen</h2>
                <form method="GET" class="flex items-center gap-2">
                    <label for="prodi_dosen" class="text-sm text-muted">Prodi</label>
                    <select id="prodi_dosen" name="prodi_dosen" class="input w-auto">
                        <option value="">Semua</option>@foreach ($prodis as $p)<option value="{{ $p->code }}" @selected(request('prodi_dosen') === $p->code)>{{ $p->code }}</option>@endforeach
                    </select>
                    <button class="btn btn-secondary btn-sm">Terapkan</button>
                </form>
            </div>
            <div class="table-wrap max-h-[26rem] overflow-y-auto">
                <table class="data-table">
                    <thead class="sticky top-0"><tr><th>Dosen</th><th class="text-right">Dibimbing</th><th>Status bimbing</th><th class="text-right">Diuji</th><th>Status uji</th><th class="text-right">Total</th><th>Status total</th><th>Kuota</th></tr></thead>
                    <tbody>
                        @forelse ($rows as $r)
                            <tr>
                                <td><p class="font-medium">{{ $r['lecturer']->name }}</p><p class="text-caption text-muted"><span class="code">{{ $r['lecturer']->lecturerProfile->lecturer_code }}</span> · {{ $r['lecturer']->prodi_code ?? '—' }}</p></td>
                                <td class="text-right tabular">{{ $r['counts']['pembimbing'] }}</td><td><x-flag :value="$r['flags']['pembimbing']" /></td>
                                <td class="text-right tabular">{{ $r['counts']['penguji'] }}</td><td><x-flag :value="$r['flags']['penguji']" /></td>
                                <td class="text-right tabular font-medium">{{ $r['counts']['total'] }}</td><td><x-flag :value="$r['flags']['total']" /></td>
                                <td class="whitespace-nowrap text-caption text-muted">B {{ $r['quota']['pembimbing']['min'] }}–{{ $r['quota']['pembimbing']['max'] }} · U {{ $r['quota']['penguji']['min'] }}–{{ $r['quota']['penguji']['max'] }}<br>{{ $r['quota']['source'] }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8"><x-empty title="Belum ada dosen pembimbing / penguji" icon="academic-cap" /></td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>

        <section class="panel">
            <div class="panel-head">
                <x-filter-bar :action="route('admin.plotting.index')">
                    <div><label class="field-label" for="prodi">Prodi tim</label>
                        <select id="prodi" name="prodi" class="input"><option value="">Semua</option>@foreach ($prodis as $p)<option value="{{ $p->code }}" @selected(request('prodi') === $p->code)>{{ $p->code }}</option>@endforeach</select></div>
                    <div><label class="field-label" for="filter">Tampilkan</label>
                        <select id="filter" name="filter" class="input"><option value="">Semua tim</option><option value="tanpa_pembimbing" @selected(request('filter') === 'tanpa_pembimbing')>Belum ada pembimbing</option><option value="tanpa_penguji" @selected(request('filter') === 'tanpa_penguji')>Belum ada penguji</option></select></div>
                </x-filter-bar>
            </div>
            @if ($teams->isEmpty())
                <x-empty title="Tidak ada tim pada filter ini" icon="user-group" />
            @else
                <ul class="divide-y divide-rule">
                    @foreach ($teams as $team)
                        <li class="grid gap-4 px-4 py-4 sm:px-6 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]">
                            <div class="min-w-0">
                                <p class="code font-medium">{{ $team->code }}</p>
                                <p class="text-sm">{{ $team->name }}</p>
                                <p class="mt-1 text-caption text-muted">{{ $team->finalTopic?->title ?? 'Topik final belum ada' }}</p>
                            </div>
                            @foreach (['pembimbing' => [$team->supervisor, $supervisors], 'penguji' => [$team->examiner, $examiners]] as $role => [$current, $options])
                                <form method="POST" action="{{ route('admin.plotting.store', $team) }}" class="min-w-0">
                                    @csrf
                                    <input type="hidden" name="role" value="{{ $role }}">
                                    <label class="field-label capitalize" for="{{ $role }}-{{ $team->id }}">{{ $role }}@if ($current?->source === 'sistem') <span class="text-caption font-normal text-muted">· otomatis dari topik</span>@endif</label>
                                    @if ($current)
                                        @php $cur = $options->first(fn ($r) => $r['lecturer']->id === $current->lecturer_id); @endphp
                                        @if ($cur)
                                            <p class="mb-1.5 flex items-center gap-2 text-sm"><span class="h-2 w-2 rounded-full {{ $balance->isAvailable($cur['counts'][$role] - 1, $cur['quota'][$role]) ? 'bg-ok' : 'bg-bad' }}" aria-hidden="true"></span>{{ $current->lecturer->name }}</p>
                                        @endif
                                    @endif
                                    <div class="flex gap-2">
                                        <select id="{{ $role }}-{{ $team->id }}" name="lecturer_id" class="input">
                                            <option value="">— pilih dosen —</option>
                                            @foreach ($options as $r)
                                                @php $available = $balance->isAvailable($r['counts'][$role], $r['quota'][$role]); @endphp
                                                <option value="{{ $r['lecturer']->id }}" @selected($current?->lecturer_id === $r['lecturer']->id)>{{ $r['lecturer']->name }} — {{ $r['counts'][$role] }}/{{ $r['quota'][$role]['max'] ?: '∞' }} {{ $available ? '· tersedia' : '· PENUH' }}</option>
                                            @endforeach
                                        </select>
                                        <button class="btn btn-secondary">Simpan</button>
                                    </div>
                                </form>
                            @endforeach
                        </li>
                    @endforeach
                </ul>
                {{ $teams->links() }}
            @endif
        </section>
    @endif
</x-layouts.app>
