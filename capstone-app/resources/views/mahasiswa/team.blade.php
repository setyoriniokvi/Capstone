<x-layouts.app title="Registrasi Tim" section="Mahasiswa · Registrasi tim">
    @if (! $team)
        <x-page-head title="Registrasi tim" lede="Bentuk tim beranggotakan {{ config('capstone.team_size.min') }}–{{ config('capstone.team_size.max') }} orang. Ketua membuat tim lalu mengundang anggota dengan NIM atau email." />

        @if ($invitations->isNotEmpty())
            <section class="panel mb-6">
                <div class="panel-head"><h2 class="panel-title">Undangan untuk Anda</h2></div>
                <ul class="divide-y divide-rule">
                    @foreach ($invitations as $invite)
                        <li class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium">{{ $invite->team->name }} <span class="code text-muted">{{ $invite->team->code }}</span></p>
                                <p class="text-sm text-muted">Ketua: {{ $invite->team->leader?->name }}</p>
                            </div>
                            <form method="POST" action="{{ route('mahasiswa.team.respond', $invite) }}" class="flex gap-2">
                                @csrf
                                <button name="decision" value="decline" class="btn btn-ghost">Tolak</button>
                                <button name="decision" value="accept" class="btn btn-primary">Gabung</button>
                            </form>
                        </li>
                    @endforeach
                </ul>
            </section>
        @endif

        <div class="grid gap-6 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
            <section class="panel h-fit">
                <div class="panel-head"><h2 class="panel-title">Buat tim baru</h2></div>
                <form method="POST" action="{{ route('mahasiswa.team.store') }}" class="panel-body space-y-4">
                    @csrf
                    <x-field label="Nama tim" name="name" required hint="Anda otomatis menjadi ketua tim.">
                        <input id="name" name="name" class="input" value="{{ old('name') }}" required maxlength="150">
                    </x-field>
                    <button class="btn btn-primary" data-once>Buat tim</button>
                </form>
            </section>
            <section class="panel h-fit">
                <div class="panel-head"><h2 class="panel-title">Prasyarat Anda</h2></div>
                <ul class="panel-body space-y-2.5">
                    @foreach ($eligibility as $check)
                        <li class="flex gap-3">
                            @if ($check['passed'])<x-heroicon-o-check-circle class="h-5 w-5 shrink-0 text-ok-ink" />@else<x-heroicon-o-x-circle class="h-5 w-5 shrink-0 text-bad-ink" />@endif
                            <span>{{ $check['label'] }}<span class="block text-caption text-muted">{{ $check['detail'] }}</span></span>
                        </li>
                    @endforeach
                </ul>
            </section>
        </div>
    @else
        <x-page-head :title="$team->name" :lede="$team->code.' · prodi '.$team->prodi_code">
            <x-slot:actions>
                <x-badge :status="$team->status" :label="'Status: '.strtolower($team->statusLabel())" class="text-sm" />
            </x-slot:actions>
        </x-page-head>

        {{-- F24 — status registrasi beserta catatannya --}}
        @if ($team->status === 'menunggu')
            <p class="mb-6 rounded-lg border border-warn-line bg-warn-tint px-4 py-3 text-sm text-warn-ink">Registrasi diajukan {{ $team->submitted_at?->translatedFormat('j F Y H:i') }} dan sedang ditinjau admin.</p>
        @elseif ($team->status === 'ditolak')
            <div class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">
                <p class="font-medium">Registrasi ditolak — perbaiki lalu ajukan kembali.</p>
                @if ($team->review_notes)<p class="mt-1">Catatan admin: {{ $team->review_notes }}</p>@endif
            </div>
        @elseif ($team->status === 'diterima')
            <p class="mb-6 rounded-lg border border-ok-line bg-ok-tint px-4 py-3 text-sm text-ok-ink">Tim diterima {{ $team->reviewed_at?->translatedFormat('j F Y') }}. Lanjutkan ke <a class="font-medium underline underline-offset-4" href="{{ route('mahasiswa.topic.index') }}">pengajuan topik mandiri</a>.@if ($team->review_notes) Catatan: {{ $team->review_notes }}@endif</p>
        @endif

        <div class="grid gap-6 md:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <section class="panel">
                    <div class="panel-head">
                        <h2 class="panel-title">Anggota</h2>
                        <span class="text-sm text-muted">{{ $team->members->where('invite_status', 'diterima')->count() }} / {{ config('capstone.team_size.max') }}</span>
                    </div>
                    <ul class="divide-y divide-rule">
                        @foreach ($team->members as $m)
                            <li class="flex flex-wrap items-start gap-3 px-4 py-3 sm:px-6">
                                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-sunken text-sm font-semibold text-muted">{{ $m->user->initials() }}</span>
                                <div class="min-w-0 flex-1">
                                    <p class="font-medium">{{ $m->user->name }}@if ($m->is_leader) <span class="text-caption font-normal text-muted">ketua</span>@endif</p>
                                    <p class="text-sm text-muted"><span class="code">{{ $m->user->nim }}</span> · {{ $m->user->prodi_code }} · {{ $m->user->specialization ?? 'peminatan —' }} · {{ $m->user->origin_region ?? 'daerah asal —' }}</p>
                                    <p class="mt-1 text-caption {{ $m->khs_path && $m->domicile_path ? 'text-ok-ink' : 'text-bad-ink' }}">KHS {{ $m->khs_path ? '✓' : '—' }} · Domisili {{ $m->domicile_path ? '✓' : '—' }}</p>
                                </div>
                                @if ($m->invite_status !== 'diterima')<x-badge :status="$m->invite_status" />@endif
                                @if ($editable && ! $m->is_leader && ($isLeader || $m->user_id === auth()->id()))
                                    <form method="POST" action="{{ route('mahasiswa.team.members.destroy', $m) }}" data-confirm="{{ $m->user_id === auth()->id() ? 'Keluar dari tim ini?' : 'Keluarkan '.$m->user->name.' dari tim?' }}" data-confirm-label="{{ $m->user_id === auth()->id() ? 'Keluar' : 'Keluarkan' }}">@csrf @method('DELETE')
                                        <button class="btn btn-ghost btn-sm text-bad-ink">{{ $m->user_id === auth()->id() ? 'Keluar' : ($m->invite_status === 'diundang' ? 'Batalkan' : 'Keluarkan') }}</button>
                                    </form>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                    @if ($editable && $isLeader)
                        <form method="POST" action="{{ route('mahasiswa.team.invite') }}" class="flex flex-wrap items-end gap-2 border-t border-rule px-4 py-3 sm:px-6">
                            @csrf
                            <div class="min-w-0 flex-1"><label class="field-label" for="identifier">Undang anggota</label><input id="identifier" name="identifier" class="input" placeholder="NIM atau email" value="{{ old('identifier') }}" required>
                                @error('identifier')<p class="field-error">{{ $message }}</p>@enderror</div>
                            <button class="btn btn-secondary">Kirim undangan</button>
                        </form>
                    @endif
                </section>

                @if ($editable && $me)
                    <section class="panel">
                        <div class="panel-head"><h2 class="panel-title">Data registrasi Anda</h2></div>
                        <form method="POST" action="{{ route('mahasiswa.team.profile') }}" enctype="multipart/form-data" class="grid gap-4 panel-body sm:grid-cols-2">
                            @csrf @method('PUT')
                            <x-field label="Peminatan" name="specialization" required><input id="specialization" name="specialization" class="input" value="{{ old('specialization', auth()->user()->specialization) }}" required></x-field>
                            <x-field label="Daerah asal" name="origin_region" required hint="Kabupaten/kota atau provinsi asal."><input id="origin_region" name="origin_region" class="input" value="{{ old('origin_region', auth()->user()->origin_region) }}" required></x-field>
                            <x-field label="KHS terbaru" name="khs" :required="! $me->khs_path" :hint="$me->khs_path ? 'Sudah diunggah — unggah lagi untuk mengganti.' : 'PDF/JPG/PNG, maks. 5 MB'">
                                <input id="khs" type="file" name="khs" accept=".pdf,.jpg,.jpeg,.png" class="input py-2">
                                @if ($me->khs_path)<a class="link mt-1 inline-block text-sm" href="{{ route('attachments.download', [$team, $me, 'khs']) }}">Lihat KHS</a>@endif
                            </x-field>
                            <x-field label="Bukti domisili" name="domicile" :required="! $me->domicile_path" :hint="$me->domicile_path ? 'Sudah diunggah — unggah lagi untuk mengganti.' : 'PDF/JPG/PNG, maks. 5 MB'">
                                <input id="domicile" type="file" name="domicile" accept=".pdf,.jpg,.jpeg,.png" class="input py-2">
                                @if ($me->domicile_path)<a class="link mt-1 inline-block text-sm" href="{{ route('attachments.download', [$team, $me, 'domisili']) }}">Lihat domisili</a>@endif
                            </x-field>
                            <div class="sm:col-span-2"><button class="btn btn-secondary" data-once>Simpan data saya</button></div>
                        </form>
                    </section>
                @endif

                @if ($editable && $isLeader)
                    <form method="POST" action="{{ route('mahasiswa.team.update') }}" class="panel flex flex-wrap items-end gap-2 px-4 py-3 sm:px-6">
                        @csrf @method('PUT')
                        <div class="min-w-0 flex-1"><label class="field-label" for="team-name">Nama tim</label><input id="team-name" name="name" class="input" value="{{ $team->name }}" required></div>
                        <button class="btn btn-secondary">Ganti nama</button>
                    </form>
                @endif
            </div>

            <aside class="space-y-6">
                <section class="panel">
                    <div class="panel-head">
                        <h2 class="panel-title">Pemeriksaan syarat</h2>
                        <span class="tabular text-sm {{ $preview['passed'] ? 'text-ok-ink' : 'text-warn-ink' }}">{{ $preview['score'] }}/100</span>
                    </div>
                    <ul class="panel-body space-y-2 text-sm">
                        @foreach ($preview['checks'] as $check)
                            <li class="flex gap-2">
                                @if ($check['passed'])<x-heroicon-o-check-circle class="h-5 w-5 shrink-0 text-ok-ink" />@else<x-heroicon-o-flag class="h-5 w-5 shrink-0 text-bad-ink" />@endif
                                <span>{{ $check['label'] }}<span class="block text-caption text-muted">{{ $check['detail'] }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                    <p class="border-t border-rule px-4 py-3 text-caption text-muted sm:px-6">Syarat yang belum terpenuhi ditandai pada pengajuan dan menjadi bahan pertimbangan admin.</p>
                </section>

                @if ($editable && $isLeader)
                    <form method="POST" action="{{ route('mahasiswa.team.submit') }}" class="panel px-4 py-4 sm:px-6">
                        @csrf
                        <p class="text-sm text-muted">Pastikan semua anggota sudah bergabung dan melengkapi data serta lampiran.</p>
                        <button class="btn btn-primary mt-3 w-full" data-once>{{ $team->status === 'ditolak' ? 'Ajukan ulang registrasi' : 'Ajukan registrasi' }}</button>
                    </form>
                @endif
            </aside>
        </div>
    @endif
</x-layouts.app>
