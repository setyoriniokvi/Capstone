<x-layouts.app title="Log Bimbingan" section="Mahasiswa · Log bimbingan">
    <x-page-head title="Log bimbingan" lede="Catat setiap sesi bimbingan. Minimal {{ $minLogbooks }} log harus disetujui dosen pembimbing sebelum presentasi akhir." />

    @if (! $team || $team->status !== 'diterima')
        <div class="panel"><x-empty title="Log bimbingan tersedia setelah tim diterima" icon="clipboard-document-check" /></div>
    @else
        <div class="mb-6 grid gap-6 md:grid-cols-[minmax(0,1fr)_minmax(0,1.2fr)]">
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Syarat presentasi akhir</h2></div>
                <div class="panel-body"><x-gate-cells :approved="$approved" :min="$minLogbooks" :pending="$team->logbooks->where('status', 'menunggu')->count()" /></div>
                <p class="border-t border-rule px-4 py-3 text-sm text-muted sm:px-6">Pembimbing: <span class="text-ink">{{ $team->supervisor?->lecturer->name ?? 'belum ditetapkan' }}</span></p>
            </section>
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Sesi <span class="tabular">{{ $nextSession }}</span></h2></div>
                @if ($team->supervisor)
                    <form method="POST" action="{{ route('mahasiswa.logbooks.store') }}" class="panel-body">
                        @csrf
                        @include('mahasiswa._logbook-fields', ['log' => null])
                        <button class="btn btn-primary mt-4" data-once>Kirim ke pembimbing</button>
                    </form>
                @else
                    <p class="panel-body text-sm text-muted">Dapat diisi setelah dosen pembimbing ditetapkan.</p>
                @endif
            </section>
        </div>

        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Riwayat sesi</h2><span class="text-sm text-muted">{{ $team->logbooks->count() }} sesi</span></div>
            @if ($team->logbooks->isEmpty())
                <x-empty title="Belum ada log" icon="clipboard-document-check" />
            @else
                <ol class="divide-y divide-rule">
                    @foreach ($team->logbooks->sortByDesc('session_no') as $log)
                        <li x-data="disclosure" class="px-4 py-4 sm:px-6">
                            <div class="flex flex-wrap items-start gap-3">
                                <span class="w-12 shrink-0 tabular text-lg font-medium text-brand-deep">#{{ $log->session_no }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="text-sm text-muted">{{ $log->session_date->translatedFormat('l, j F Y') }} · {{ $log->responsible_person }}</p>
                                    <p class="mt-1 whitespace-pre-line">{{ $log->activity }}</p>
                                    <p class="mt-1 text-sm"><span class="text-muted">Luaran:</span> {{ $log->output }}</p>
                                    @if ($log->feedback)
                                        <p class="mt-2 rounded border px-3 py-2 text-sm {{ $log->status === 'revisi' ? 'border-bad-line bg-bad-tint text-bad-ink' : 'border-rule bg-sunken' }}"><span class="font-medium">Feedback dosen:</span> {{ $log->feedback }}</p>
                                    @endif
                                </div>
                                <x-badge :status="$log->status" />
                            </div>
                            @if ($log->status !== 'disetujui')
                                <div class="mt-3 flex gap-1 pl-[3.75rem]">
                                    <button type="button" class="btn btn-ghost btn-sm" @click="toggle">{{ $log->status === 'revisi' ? 'Revisi log' : 'Ubah' }}</button>
                                    <form method="POST" action="{{ route('mahasiswa.logbooks.destroy', $log) }}" data-confirm="Hapus log bimbingan ini?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-bad-ink">Hapus</button></form>
                                </div>
                                <form x-cloak x-show="expanded" method="POST" action="{{ route('mahasiswa.logbooks.update', $log) }}" class="mt-3 rounded-lg border border-rule bg-sunken p-4">
                                    @csrf @method('PUT')
                                    @include('mahasiswa._logbook-fields', ['log' => $log])
                                    <button class="btn btn-primary mt-3">Simpan & kirim ulang</button>
                                </form>
                            @endif
                            @if ($log->histories->count() > 1)
                                <details class="mt-2 pl-[3.75rem] text-sm">
                                    <summary class="cursor-pointer text-muted">Histori ({{ $log->histories->count() }})</summary>
                                    <ul class="mt-1 space-y-0.5 text-muted">
                                        @foreach ($log->histories as $h)<li>{{ $h->created_at->translatedFormat('j M H:i') }} · {{ $h->event }} · {{ $h->actor?->name }}</li>@endforeach
                                    </ul>
                                </details>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    @endif
</x-layouts.app>
