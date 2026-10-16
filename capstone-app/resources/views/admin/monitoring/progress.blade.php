<x-layouts.app title="Progres Tim" section="Admin · Monitoring progres">
    <x-page-head title="Monitoring progres" lede="Status setiap tahapan per tim, rekap approval log bimbingan, dan jumlah sesi bimbingan." />

    @if (! $period)
        <div class="panel"><x-empty title="Belum ada periode aktif" icon="calendar-days" /></div>
    @else
        <div class="panel">
            <div class="panel-head">
                <x-filter-bar :action="route('admin.monitoring.progress')">
                    <div><label class="field-label" for="prodi">Prodi</label>
                        <select id="prodi" name="prodi" class="input"><option value="">Semua</option>@foreach ($prodis as $p)<option value="{{ $p->code }}" @selected(request('prodi') === $p->code)>{{ $p->code }}</option>@endforeach</select></div>
                    <div><label class="field-label" for="status">Status tim</label>
                        <select id="status" name="status" class="input"><option value="">Semua</option>@foreach (['diterima', 'menunggu', 'ditolak', 'draft'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
                </x-filter-bar>
            </div>
            @if ($teams->isEmpty())
                <x-empty title="Belum ada tim" icon="user-group" />
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>Tim</th><th>Registrasi</th><th>Topik final</th><th>Dosen</th>
                                @foreach ($docMilestones as $m)<th title="{{ $m->name }}">{{ \Illuminate\Support\Str::limit(config('capstone.document_types.'.$m->document_type, $m->name), 14) }}</th>@endforeach
                                <th>Log bimbingan</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($teams as $team)
                                <tr>
                                    <td><a class="link code" href="{{ route('admin.teams.show', $team) }}">{{ $team->code }}</a><p class="text-sm">{{ $team->name }}</p></td>
                                    <td><x-badge :status="$team->status" /></td>
                                    <td class="max-w-[14rem] text-sm">{{ $team->finalTopic?->title ?? '—' }}</td>
                                    <td class="whitespace-nowrap text-sm"><p>B: {{ $team->supervisor?->lecturer->name ?? '—' }}</p><p class="text-muted">U: {{ $team->examiner?->lecturer->name ?? '—' }}</p></td>
                                    @foreach ($docMilestones as $m)
                                        @php $docs = $team->documents->where('milestone_id', $m->id); @endphp
                                        <td class="text-center">
                                            @if ($docs->isNotEmpty())
                                                <span class="{{ $docs->contains('is_late', true) ? 'text-warn-ink' : 'text-ok-ink' }}" title="{{ $docs->contains('is_late', true) ? 'Terlambat' : 'Terkumpul' }}">{{ $docs->contains('is_late', true) ? 'telat' : '✓' }}</span>
                                            @else
                                                <span class="{{ $m->isPast() ? 'text-bad-ink' : 'text-faint' }}">{{ $m->isPast() ? 'belum' : '—' }}</span>
                                            @endif
                                        </td>
                                    @endforeach
                                    <td class="min-w-[11rem]">
                                        <x-gate-cells :approved="$team->logbook_approved" :min="$minLogbooks" :pending="$team->logbook_pending" />
                                        @if ($team->logbook_revision)<p class="text-caption text-bad-ink">{{ $team->logbook_revision }} perlu revisi</p>@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                {{ $teams->links() }}
            @endif
        </div>
    @endif
</x-layouts.app>
