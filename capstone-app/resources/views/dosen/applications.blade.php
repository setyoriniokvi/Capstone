<x-layouts.app title="Tim Pelamar" section="Dosen · Tim pelamar">
    <x-page-head title="Tim pelamar topik" lede="Menerima tim menjadikan Anda dosen pembimbing tim tersebut secara otomatis. Kuota tidak dapat dilampaui." />

    <h2 class="mb-3 text-h2">Menunggu keputusan <span class="font-mono text-muted">{{ $pending->count() }}</span></h2>
    <div class="mb-8 space-y-4">
        @forelse ($pending as $app)
            @php $remaining = max(0, $app->topic->quota - $app->topic->accepted_count); @endphp
            <article class="panel">
                <div class="panel-head">
                    <div class="min-w-0">
                        <p class="text-sm text-muted">Melamar: {{ $app->topic->title }}</p>
                        <h3 class="font-semibold"><span class="code mr-1">{{ $app->team->code }}</span> {{ $app->team->name }}</h3>
                    </div>
                    <span class="font-mono text-sm {{ $remaining ? 'text-ok-ink' : 'text-bad-ink' }}">sisa kuota {{ $remaining }}</span>
                </div>
                <div class="grid gap-4 panel-body md:grid-cols-2">
                    <div>
                        <p class="text-sm text-muted">Anggota</p>
                        <ul class="mt-1 space-y-1 text-sm">
                            @foreach ($app->team->members->where('invite_status', 'diterima') as $m)
                                <li><span class="font-medium">{{ $m->user->name }}</span> <span class="text-muted">· {{ $m->user->prodi_code }} · {{ $m->user->specialization ?? '—' }} · {{ $m->user->origin_region ?? '—' }}</span></li>
                            @endforeach
                        </ul>
                    </div>
                    <div>
                        <p class="text-sm text-muted">Topik mandiri tim</p>
                        <p class="mt-1 text-sm">{{ $app->team->independentTopic?->title ?? '—' }}</p>
                        @if ($app->motivation)<p class="mt-3 text-sm text-muted">Alasan melamar</p><p class="text-sm">{{ $app->motivation }}</p>@endif
                    </div>
                </div>
                <div class="border-t border-rule px-4 py-3 sm:px-5">
                    <x-decision :action="route('dosen.applications.decide', $app)" title="Keputusan lamaran {{ $app->team->code }}" approve-label="Terima tim" reject-label="Tolak" trigger="Putuskan">
                        Menerima tim menetapkan Anda sebagai pembimbing {{ $app->team->code }}.
                    </x-decision>
                </div>
            </article>
        @empty
            <div class="panel"><x-empty title="Tidak ada lamaran menunggu" icon="inbox-arrow-down" /></div>
        @endforelse
    </div>

    @if ($decided->isNotEmpty())
        <h2 class="mb-3 text-h2">Riwayat</h2>
        <div class="panel">
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Tim</th><th>Topik</th><th>Keputusan</th><th>Waktu</th><th>Catatan</th></tr></thead>
                    <tbody>
                        @foreach ($decided as $app)
                            <tr><td class="code">{{ $app->team->code }}</td><td class="max-w-[18rem]">{{ $app->topic->title }}</td><td><x-badge :status="$app->status" /></td><td class="whitespace-nowrap text-sm">{{ $app->decided_at?->translatedFormat('j M Y H:i') }}</td><td class="text-sm text-muted">{{ $app->notes ?? '—' }}</td></tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</x-layouts.app>
