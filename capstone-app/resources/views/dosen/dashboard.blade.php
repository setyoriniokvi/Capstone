<x-layouts.app title="Dashboard Dosen" section="Dosen · Dashboard">
    <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
        <div>
            <p class="text-sm text-muted">{{ $period?->name ?? 'Belum ada periode aktif' }}</p>
            <h1 class="mt-1 text-h1">{{ auth()->user()->name }}</h1>
            <p class="mt-1 text-muted"><span class="code">{{ $profile?->lecturer_code }}</span> · {{ implode(' · ', $profile?->accessLabels() ?? []) ?: 'Hak akses belum diatur' }}</p>
        </div>
    </div>

    <div class="panel mb-6 grid divide-y divide-rule sm:grid-cols-3 sm:divide-x sm:divide-y-0">
        <x-stat label="Tim pelamar menunggu" :value="$counts['applications']" :href="route('dosen.applications.index')" tone="alert" />
        <x-stat label="Log bimbingan menunggu" :value="$counts['logbooks']" :href="route('dosen.logbooks.index')" tone="alert" />
        <x-stat label="Komponen nilai belum diisi" :value="$counts['assessments']" :href="route('dosen.assessments.index')" tone="alert" />
    </div>

    @if ($period && $period->milestones->isNotEmpty())
        <section class="panel mb-6">
            <div class="panel-head"><h2 class="panel-title">Alur Capstone</h2></div>
            <div class="panel-body"><x-week-ruler :period="$period" /></div>
        </section>
    @endif

    @foreach (['Tim bimbingan' => $supervised, 'Tim uji' => $examined] as $heading => $teams)
        <section class="panel mb-6">
            <div class="panel-head"><h2 class="panel-title">{{ $heading }}</h2><span class="tabular text-sm text-muted">{{ $teams->count() }} tim</span></div>
            @if ($teams->isEmpty())
                <p class="panel-body text-sm text-muted">Belum ada tim.</p>
            @else
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Tim</th><th>Topik final</th><th>Anggota</th><th>Dokumen</th><th>Log bimbingan</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($teams as $team)
                                <tr>
                                    <td><span class="code font-medium">{{ $team->code }}</span><p class="text-sm">{{ $team->name }}</p></td>
                                    <td class="max-w-[18rem] text-sm">{{ $team->finalTopic?->title ?? '—' }}</td>
                                    <td class="text-sm">{{ $team->activeUsers->pluck('name')->implode(', ') }}</td>
                                    <td class="tabular text-sm">{{ $team->document_count }}<span class="text-muted">/{{ $docMilestones }} milestone</span></td>
                                    <td class="min-w-[10rem]"><x-gate-cells :approved="$team->logbook_approved" :min="$minLogbooks" :pending="$team->logbook_pending" /></td>
                                    <td class="text-right"><a href="{{ route('dosen.teams.show', $team) }}" class="btn btn-secondary btn-sm">Detail</a></td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>
    @endforeach
</x-layouts.app>
