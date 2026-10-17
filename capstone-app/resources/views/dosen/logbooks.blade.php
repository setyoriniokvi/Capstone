<x-layouts.app title="Approval Bimbingan" section="Dosen · Approval bimbingan">
    <x-page-head title="Approval log bimbingan" lede="Setujui log atau minta revisi dengan feedback. Setiap tim perlu minimal {{ $minLogbooks }} log disetujui sebelum presentasi akhir.">
        <x-slot:actions><span class="text-sm text-muted"><span class="tabular font-medium text-ink">{{ $pendingTotal }}</span> log menunggu</span></x-slot:actions>
    </x-page-head>

    @forelse ($teams as $team)
        <section class="panel mb-6">
            <div class="panel-head">
                <h2 class="panel-title"><span class="code mr-2">{{ $team->code }}</span>{{ $team->name }}</h2>
                <x-gate-cells class="w-full sm:w-64" :approved="$team->logbooks->where('status', 'disetujui')->count()" :min="$minLogbooks" />
            </div>
            @if ($team->logbooks->isEmpty())
                <p class="panel-body text-sm text-muted">Tim belum mengisi log bimbingan.</p>
            @else
                <ol class="divide-y divide-rule">
                    @foreach ($team->logbooks as $log)
                        <li class="grid gap-3 px-4 py-4 sm:px-6 md:grid-cols-[3rem_minmax(0,1fr)_auto]">
                            <span class="tabular text-lg font-medium text-brand-deep">#{{ $log->session_no }}</span>
                            <div class="min-w-0">
                                <p class="text-sm text-muted">{{ $log->session_date->translatedFormat('l, j F Y') }} · PJ {{ $log->responsible_person }} · diisi {{ $log->author->name }}</p>
                                <p class="mt-1 whitespace-pre-line">{{ $log->activity }}</p>
                                <dl class="mt-2 grid gap-x-4 gap-y-1 text-sm sm:grid-cols-[7rem_minmax(0,1fr)]">
                                    @if ($log->instrument)<dt class="text-muted">Instrumen</dt><dd>{{ $log->instrument }}</dd>@endif
                                    @if ($log->data_collected)<dt class="text-muted">Data</dt><dd>{{ $log->data_collected }}</dd>@endif
                                    <dt class="text-muted">Luaran</dt><dd>{{ $log->output }}</dd>
                                    @if ($log->feedback)<dt class="text-muted">Feedback</dt><dd>{{ $log->feedback }}</dd>@endif
                                </dl>
                                @if ($log->histories->where('event', 'revisi')->count())
                                    <p class="mt-1 text-caption text-muted">Sudah {{ $log->histories->where('event', 'revisi')->count() }}× diminta revisi.</p>
                                @endif
                            </div>
                            <div class="flex items-start gap-2 md:flex-col md:items-end">
                                <x-badge :status="$log->status" />
                                @if ($log->status === 'menunggu')
                                    <x-decision :action="route('dosen.logbooks.review', $log)" title="Tinjau log sesi {{ $log->session_no }}" approve-label="Setujui" reject-label="Minta revisi" trigger="Tinjau">
                                        {{ $team->code }} · {{ \Illuminate\Support\Str::limit($log->activity, 120) }}
                                    </x-decision>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @empty
        <div class="panel"><x-empty title="Anda belum menjadi pembimbing tim mana pun" icon="clipboard-document-check" /></div>
    @endforelse
</x-layouts.app>
