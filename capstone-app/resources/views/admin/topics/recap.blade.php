<x-layouts.app title="Rekap Topik Final" section="Admin · Rekap topik final">
    <x-page-head title="Rekap topik final" lede="Disusun otomatis. Bila tim diterima di topik dosen, topik mandiri tim tersebut tergantikan." />
    <div class="panel">
        <div class="panel-head">
            <x-filter-bar :action="route('admin.topics.recap')">
                <div><label class="field-label" for="prodi">Prodi</label>
                    <select id="prodi" name="prodi" class="input"><option value="">Semua</option>@foreach ($prodis as $p)<option value="{{ $p->code }}" @selected(request('prodi') === $p->code)>{{ $p->code }}</option>@endforeach</select></div>
            </x-filter-bar>
            <p class="text-sm text-muted"><span class="tabular">{{ $teams->whereNotNull('final_topic_id')->count() }}</span> dari {{ $teams->count() }} tim memiliki topik final</p>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Tim</th><th>Topik final</th><th>Jalur</th><th>Topik mandiri</th><th>Pembimbing</th><th>Penguji</th></tr></thead>
                <tbody>
                    @forelse ($teams as $team)
                        <tr>
                            <td><a class="link code" href="{{ route('admin.teams.show', $team) }}">{{ $team->code }}</a><p class="text-sm">{{ $team->name }}</p></td>
                            <td class="max-w-[22rem]">{{ $team->finalTopic?->title ?? '—' }}</td>
                            <td>@if ($team->finalTopic)<span class="rounded bg-sunken px-1.5 py-0.5 text-caption">{{ $team->finalTopic->source === 'dosen' ? 'Topik dosen' : 'Mandiri' }}</span>@endif</td>
                            <td class="max-w-[18rem] text-sm {{ $team->finalTopic?->source === 'dosen' ? 'text-muted line-through' : '' }}">{{ $team->independentTopic?->title ?? '—' }}</td>
                            <td>{{ $team->supervisor?->lecturer->name ?? '—' }}</td>
                            <td>{{ $team->examiner?->lecturer->name ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6"><x-empty title="Belum ada tim diterima" icon="user-group" /></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
