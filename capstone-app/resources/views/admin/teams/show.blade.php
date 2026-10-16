<x-layouts.app :title="$team->code" section="Admin · Registrasi tim">
    <x-page-head :title="$team->code.' · '.$team->name" :lede="'Prodi '.$team->prodi_code.' · diajukan '.($team->submitted_at?->translatedFormat('j F Y H:i') ?? 'belum diajukan')">
        <x-slot:actions>
            <x-badge :status="$team->status" class="text-sm" />
            <a href="{{ route('admin.teams.index') }}" class="btn btn-ghost">Kembali</a>
            @if ($team->status === 'menunggu')
                <x-decision :action="route('admin.teams.decide', $team)" title="Keputusan registrasi {{ $team->code }}" approve-label="Terima tim" reject-label="Tolak" trigger="Putuskan registrasi">
                    Skor screening {{ $team->screening_score ?? '—' }}/100. Catatan wajib diisi bila menolak.
                </x-decision>
            @endif
        </x-slot:actions>
    </x-page-head>

    @if ($team->review_notes)
        <p class="mb-6 rounded-lg border border-rule bg-sunken px-4 py-3 text-sm"><span class="font-medium">Catatan admin:</span> {{ $team->review_notes }}</p>
    @endif

    <div class="grid gap-6 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
        <div class="space-y-6">
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Anggota & lampiran</h2></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Mahasiswa</th><th>SKS</th><th>Peminatan</th><th>Daerah asal</th><th>Lampiran</th><th></th></tr></thead>
                        <tbody>
                            @foreach ($team->members as $m)
                                <tr>
                                    <td><p class="font-medium">{{ $m->user->name }}@if ($m->is_leader) <span class="text-caption text-muted">ketua</span>@endif</p><p class="code text-muted">{{ $m->user->nim }} · {{ $m->user->prodi_code }}</p>@if ($m->invite_status !== 'diterima')<x-badge :status="$m->invite_status" />@endif</td>
                                    <td class="font-mono">{{ $m->user->sks_passed ?? '—' }}</td>
                                    <td>{{ $m->user->specialization ?? '—' }}</td>
                                    <td>{{ $m->user->origin_region ?? '—' }}</td>
                                    <td class="whitespace-nowrap text-sm">
                                        @if ($m->khs_path)<a class="link" href="{{ route('attachments.download', [$team, $m, 'khs']) }}">KHS</a>@else<span class="text-bad-ink">KHS —</span>@endif
                                        <span class="text-faint">·</span>
                                        @if ($m->domicile_path)<a class="link" href="{{ route('attachments.download', [$team, $m, 'domisili']) }}">Domisili</a>@else<span class="text-bad-ink">Domisili —</span>@endif
                                    </td>
                                    <td class="text-right">
                                        @unless ($m->is_leader)
                                            <form method="POST" action="{{ route('admin.teams.members.destroy', [$team, $m]) }}">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-bad-ink">Keluarkan</button></form>
                                        @endunless
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <form method="POST" action="{{ route('admin.teams.members.store', $team) }}" class="flex flex-wrap items-end gap-2 border-t border-rule px-4 py-3 sm:px-5">
                    @csrf
                    <div class="min-w-0 flex-1"><label class="field-label" for="nim">Tambah anggota (NIM)</label><input id="nim" name="nim" class="input font-mono" required></div>
                    <button class="btn btn-secondary">Tambahkan</button>
                </form>
            </section>

            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Topik & dosen</h2></div>
                <dl class="kv panel-body">
                    <dt>Topik mandiri</dt><dd>@if ($team->independentTopic){{ $team->independentTopic->title }} <x-badge :status="$team->independentTopic->status" />@else<span class="text-muted">Belum diajukan</span>@endif</dd>
                    <dt>Topik final</dt><dd>{{ $team->finalTopic?->title ?? '—' }}</dd>
                    <dt>Pembimbing</dt><dd>{{ $team->lecturers->firstWhere('role', 'pembimbing')?->lecturer->name ?? '—' }}</dd>
                    <dt>Penguji</dt><dd>{{ $team->lecturers->firstWhere('role', 'penguji')?->lecturer->name ?? '—' }}</dd>
                </dl>
            </section>
        </div>

        <div class="space-y-6">
            <section class="panel">
                <div class="panel-head">
                    <h2 class="panel-title">Screening otomatis</h2>
                    <form method="POST" action="{{ route('admin.teams.rescreen', $team) }}">@csrf<button class="btn btn-ghost btn-sm"><x-heroicon-o-arrow-path class="h-4 w-4" />Hitung ulang</button></form>
                </div>
                <div class="panel-body">
                    <p class="font-mono text-3xl font-medium {{ ($team->screening_score ?? 0) === 100 ? 'text-ok-ink' : 'text-warn-ink' }}">{{ $team->screening_score ?? '—' }}<span class="text-base text-muted">/100</span></p>
                    <ul class="mt-4 space-y-2 text-sm">
                        @forelse ($team->screening ?? [] as $check)
                            <li class="flex gap-2">
                                @if ($check['passed'])<x-heroicon-o-check-circle class="h-5 w-5 shrink-0 text-ok-ink" />@else<x-heroicon-o-flag class="h-5 w-5 shrink-0 text-bad-ink" />@endif
                                <span><span class="{{ $check['passed'] ? '' : 'font-medium text-bad-ink' }}">{{ $check['label'] }}</span><span class="block text-caption text-muted">{{ $check['detail'] }}</span></span>
                            </li>
                        @empty
                            <li class="text-muted">Screening dijalankan saat tim mengajukan registrasi.</li>
                        @endforelse
                    </ul>
                </div>
            </section>

            @if ($qrToken)
                <section class="panel">
                    <div class="panel-head"><h2 class="panel-title">QR Code tim</h2></div>
                    <div class="panel-body">
                        <div class="mx-auto w-44 [&_svg]:h-auto [&_svg]:w-full">{!! $qr->svg($qrToken) !!}</div>
                        <div class="mt-4 flex flex-wrap justify-center gap-2">
                            <a href="{{ route('admin.teams.qr.svg', $team) }}" class="btn btn-secondary btn-sm"><x-heroicon-o-arrow-down-tray class="h-4 w-4" />Unduh SVG</a>
                            <form method="POST" action="{{ route('admin.teams.qr', $team) }}">@csrf<button class="btn btn-ghost btn-sm">Buat ulang</button></form>
                        </div>
                        <p class="field-hint text-center">Membuat ulang membatalkan QR lama.</p>
                    </div>
                </section>
            @endif
        </div>
    </div>
</x-layouts.app>
