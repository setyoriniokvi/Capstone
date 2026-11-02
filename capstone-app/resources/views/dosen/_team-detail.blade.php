{{-- Overlay detail tim (dibuka tombol "Lihat detail" di Tim Bimbingan / Tim Uji). Memakai state registrationForm di <tr>.
     Isi: Anggota (KHS), Topik & Objek Final, Sesi Bimbingan (→ Approval Bimbingan), Submission, Hasil Penilaian.
     Butuh $team (members.user, finalTopic, documents.currentVersion, approved_logbooks, assessment_summary),
     $role, $docMilestones, $minLogbooks, $jenisLabel. --}}
@php
    $card = 'rounded-lg border border-rule';
    $cardHead = 'border-b border-rule px-4 py-3 text-base font-semibold text-ink sm:px-5';
    $approved = $team->approved_logbooks;
    $statusLabel = ['final' => 'Final', 'draft' => 'Draft', 'belum' => 'Belum dinilai'];
@endphp
<x-modal width="sm:max-w-4xl" class="text-left font-normal">
    <x-slot:heading>{{ $team->name }}</x-slot:heading>
    <div class="space-y-5 text-left">
        {{-- Anggota --}}
        <section class="{{ $card }}" aria-label="Anggota {{ $team->name }}">
            <h3 class="{{ $cardHead }}">Anggota</h3>
            <ul class="divide-y divide-rule">
                @foreach ($team->members as $member)
                    @php $user = $member->user; @endphp
                    <li class="flex flex-col gap-3 px-4 py-4 sm:flex-row sm:items-start sm:px-5">
                        <div class="min-w-0 flex-1">
                            <p class="flex flex-wrap items-center gap-2">
                                <span class="font-semibold text-ink">{{ $user->name }}</span>
                                <span class="rounded-md px-1.5 py-0.5 text-caption font-medium {{ $member->is_leader ? 'bg-brand-tint text-brand-deep' : 'bg-sunken text-muted' }}">{{ $member->is_leader ? 'Ketua' : 'Anggota' }}</span>
                            </p>
                            <dl class="mt-2 grid grid-cols-[7rem_minmax(0,1fr)] gap-x-3 gap-y-1 text-sm">
                                <dt class="text-muted">NIM</dt><dd class="code text-ink">{{ $user->nim ?? '—' }}</dd>
                                <dt class="text-muted">Email</dt><dd class="min-w-0 break-all"><a class="link font-normal" href="mailto:{{ $user->email }}">{{ $user->email }}</a></dd>
                                <dt class="text-muted">Peminatan</dt><dd class="text-ink">{{ $user->specialization ?? '—' }}</dd>
                                <dt class="text-muted">Asal daerah</dt><dd class="text-ink">{{ $user->origin_region ?? '—' }}</dd>
                            </dl>
                        </div>
                        @if ($member->khs_path)
                            <a href="{{ route('attachments.download', [$team, $member, 'khs']) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm shrink-0"><x-heroicon-o-eye class="h-4 w-4" />Lihat KHS<span class="sr-only"> {{ $user->name }}</span></a>
                        @else
                            <button type="button" class="btn btn-secondary btn-sm shrink-0 disabled:cursor-not-allowed disabled:opacity-50" disabled title="KHS belum diunggah"><x-heroicon-o-eye class="h-4 w-4" />Lihat KHS</button>
                        @endif
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Topik & objek final --}}
        <section class="{{ $card }}" aria-label="Topik dan objek final">
            <h3 class="{{ $cardHead }}">Topik dan Objek Final</h3>
            @if ($team->finalTopic)
                <dl class="grid gap-x-4 gap-y-2 px-4 py-4 text-sm sm:grid-cols-[8rem_minmax(0,1fr)] sm:px-5">
                    <dt class="text-muted">Topik</dt><dd class="font-medium text-ink">{{ $team->finalTopic->title }}</dd>
                    <dt class="text-muted">Objek</dt><dd class="text-ink">{{ $team->finalTopic->object ?? '—' }}</dd>
                    <dt class="text-muted">Jenis topik</dt><dd class="text-ink">{{ $jenisLabel[$team->finalTopic->source] ?? ucfirst($team->finalTopic->source) }}</dd>
                </dl>
            @else
                <p class="px-4 py-4 text-sm text-muted sm:px-5">Topik final belum ditetapkan.</p>
            @endif
        </section>

        {{-- Sesi bimbingan --}}
        <section class="{{ $card }} px-4 py-4 sm:px-5" aria-label="Sesi bimbingan">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div class="min-w-0 flex-1">
                    <h3 class="text-base font-semibold text-ink">Sesi Bimbingan</h3>
                    <p class="mt-2 text-lg text-muted"><span class="tabular text-2xl font-semibold text-ink">{{ $approved }}</span> / <span class="tabular">{{ $minLogbooks }}</span> sesi minimal</p>
                </div>
                @if ($role === 'pembimbing')
                    <a href="{{ route('dosen.logbooks.index', ['tab' => 'log', 'tim' => $team->id]) }}" class="btn btn-secondary btn-sm shrink-0">Lihat detail<span class="sr-only"> log bimbingan {{ $team->name }}</span></a>
                @else
                    <button type="button" class="btn btn-secondary btn-sm shrink-0 disabled:cursor-not-allowed disabled:opacity-50" disabled title="Log bimbingan hanya dapat dibuka dosen pembimbing">Lihat detail</button>
                @endif
            </div>
            <x-progress-bar class="mt-3" :value="$approved" :max="$minLogbooks" label="Sesi bimbingan disetujui {{ $team->name }}" />
            @if ($approved >= $minLogbooks)
                <p class="mt-3 text-sm font-medium text-ok-ink">Syarat minimal terpenuhi</p>
            @else
                <p class="mt-3 text-sm font-medium text-bad-ink">Syarat minimal belum terpenuhi</p>
            @endif
        </section>

        {{-- Submission dokumen milestone --}}
        <section class="{{ $card }}" aria-label="Submission">
            <h3 class="{{ $cardHead }}">Submission</h3>
            @if ($docMilestones->isEmpty())
                <p class="px-4 py-4 text-sm text-muted sm:px-5">Belum ada milestone dokumen.</p>
            @else
                <ul class="divide-y divide-rule">
                    @foreach ($docMilestones as $milestone)
                        @php
                            $doc = $team->documents->firstWhere('milestone_id', $milestone->id);
                            $file = $doc?->currentVersion;
                        @endphp
                        <li class="flex flex-col gap-3 px-4 py-3 sm:flex-row sm:items-center sm:px-5">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-ink">{{ $milestone->name }}</p>
                                <p class="text-caption text-muted">Deadline {{ $milestone->due_at->translatedFormat('j M Y, H:i') }}</p>
                            </div>
                            <div class="flex flex-wrap items-center gap-2">
                                <x-badge :status="$doc ? 'diterima' : 'belum'" :label="$doc ? 'Sudah dikumpulkan' : 'Belum dikumpulkan'" />
                                @if ($doc?->is_late)<x-badge status="terlambat" />@endif
                                @if ($file)
                                    <a href="{{ route('documents.download', $file) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-heroicon-o-eye class="h-4 w-4" />Lihat<span class="sr-only"> {{ $milestone->name }}</span></a>
                                    <a download href="{{ route('documents.download', $file) }}" class="btn btn-secondary btn-sm"><x-heroicon-o-arrow-down-tray class="h-4 w-4" />Unduh<span class="sr-only"> {{ $milestone->name }}</span></a>
                                @else
                                    <button type="button" class="btn btn-secondary btn-sm disabled:cursor-not-allowed disabled:opacity-50" disabled title="Belum ada file"><x-heroicon-o-eye class="h-4 w-4" />Lihat</button>
                                    <button type="button" class="btn btn-secondary btn-sm disabled:cursor-not-allowed disabled:opacity-50" disabled title="Belum ada file"><x-heroicon-o-arrow-down-tray class="h-4 w-4" />Unduh</button>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </section>

        {{-- Hasil penilaian per bentuk penilaian --}}
        <section class="{{ $card }}" aria-label="Hasil penilaian">
            <h3 class="{{ $cardHead }}">Hasil Penilaian</h3>
            @if ($team->assessment_summary->isEmpty())
                <p class="px-4 py-4 text-sm text-muted sm:px-5">Belum ada komponen penilaian.</p>
            @else
                <ul class="divide-y divide-rule">
                    @foreach ($team->assessment_summary as $row)
                        <li class="flex flex-wrap items-center gap-x-4 gap-y-1 px-4 py-3 sm:px-5">
                            <div class="min-w-0 flex-1">
                                <p class="font-medium text-ink">{{ $row['label'] }}</p>
                                <p class="text-caption text-muted">Dinilai {{ $row['role'] }}</p>
                            </div>
                            <x-badge :status="$row['status'] === 'belum' ? 'belum' : $row['status']" :label="$statusLabel[$row['status']]" />
                            <p class="w-28 text-right text-sm text-muted"><span class="tabular text-base font-semibold {{ $row['status'] === 'final' ? 'text-ink' : 'text-muted' }}">{{ $row['status'] === 'final' ? number_format($row['points'], 2, ',', '.') : '—' }}</span> / <span class="tabular">{{ number_format($row['max'], 2, ',', '.') }}</span></p>
                        </li>
                    @endforeach
                    {{-- Peer review (diisi mahasiswa): jumlah anggota yang sudah menilai semua rekannya --}}
                    @php
                        ['done' => $peerDone, 'total' => $peerTotal] = $team->peer_summary;
                        [$peerStatus, $peerLabel] = match (true) {
                            $peerTotal > 0 && $peerDone >= $peerTotal => ['final', 'Sudah lengkap'],
                            $peerDone > 0 => ['draft', 'Sebagian'],
                            default => ['belum', 'Belum diisi'],
                        };
                    @endphp
                    <li x-data="disclosure" class="px-4 py-3 sm:px-5">
                        {{-- Tiga kolom: judul | tombol "Lihat detail" di tengah | status & jumlah --}}
                        <div class="grid grid-cols-1 items-center gap-x-4 gap-y-2 sm:grid-cols-[minmax(0,1fr)_auto_minmax(0,1fr)]">
                            <div class="min-w-0">
                                <p class="font-medium text-ink">Peer Review</p>
                                <p class="text-caption text-muted">Diisi mahasiswa (antar-anggota tim)</p>
                            </div>
                            <button type="button" class="link inline-flex min-h-[44px] items-center justify-self-start gap-1.5 text-sm sm:justify-self-center" @click="toggle" :aria-expanded="expanded" aria-controls="peer-{{ $team->id }}">
                                <span x-show="collapsed" class="inline-flex items-center gap-1.5">Lihat detail <x-heroicon-o-chevron-down class="h-4 w-4" /></span>
                                <span x-show="expanded" x-cloak class="inline-flex items-center gap-1.5">Sembunyikan detail <x-heroicon-o-chevron-up class="h-4 w-4" /></span>
                            </button>
                            <div class="flex items-center gap-4 sm:justify-end">
                                <x-badge :status="$peerStatus" :label="$peerLabel" />
                                <p class="w-28 text-right text-sm text-muted"><span class="tabular text-base font-semibold text-ink">{{ $peerDone }}</span> / <span class="tabular">{{ $peerTotal }}</span> anggota</p>
                            </div>
                        </div>

                        {{-- Rata-rata nilai yang DITERIMA tiap mahasiswa per aspek (skala 1–5) dari rekan satu timnya.
                             Penilai yang belum mengisi dihitung 0: pembagi = jumlah rekan (dan × jumlah aspek untuk rata-rata total). --}}
                        @php
                            $received = $team->peerReviews->groupBy('reviewee_id');
                            $raters = max(1, $team->members->count() - 1);
                            $firstName = fn ($m) => \Illuminate\Support\Str::of($m->user->name)->explode(' ')->first();
                        @endphp
                        <div id="peer-{{ $team->id }}" x-show="expanded" x-cloak class="mt-3">
                            {{-- Grid, bukan <table>: overlay ini berada di dalam sel .data-table sehingga gaya td/th tabel induk ikut terbawa --}}
                            @php $cols = 'grid-template-columns: minmax(0, 2fr) repeat('.max(1, $team->members->count()).', minmax(0, 1fr))'; @endphp
                            <div role="table" aria-label="Detail nilai peer review {{ $team->name }}" class="border-t border-rule text-sm">
                                <div role="row" class="grid items-end gap-x-2 border-b border-rule py-2.5" style="{{ $cols }}">
                                    <span role="columnheader" class="font-medium text-muted">Aspek</span>
                                    @foreach ($team->members as $member)
                                        @php $filled = $team->peer_summary['filled']->contains($member->user_id); @endphp
                                        <span role="columnheader" class="text-center">
                                            <span class="block break-words font-medium text-ink">{{ $member->user->name }}</span>
                                            <span class="mt-0.5 block text-caption {{ $filled ? 'text-ok-ink' : 'text-muted' }}">{{ $filled ? 'Sudah mengisi' : 'Belum mengisi' }}</span>
                                        </span>
                                    @endforeach
                                </div>
                                @foreach (config('capstone.peer_aspects') as $aspect => $aspectLabel)
                                    <div role="row" class="grid items-center gap-x-2 border-b border-rule py-2" style="{{ $cols }}">
                                        <span role="rowheader" class="text-muted">{{ $aspectLabel }}</span>
                                        @foreach ($team->members as $member)
                                            @php $scores = $received->get($member->user_id, collect())->where('aspect', $aspect); @endphp
                                            <span role="cell" class="text-center">
                                                <span class="block tabular font-semibold text-ink">{{ number_format($scores->sum('score') / $raters, 1, ',', '.') }}</span>
                                                {{-- Rincian per penilai (rekan satu tim); 0 = penilai belum mengisi --}}
                                                <span class="mt-0.5 block text-caption leading-snug text-muted">
                                                    @foreach ($team->members->where('user_id', '!=', $member->user_id) as $reviewer)
                                                        <span class="whitespace-nowrap">{{ $firstName($reviewer) }} <span class="tabular">{{ $scores->firstWhere('reviewer_id', $reviewer->user_id)?->score ?? 0 }}</span></span>@unless ($loop->last) <span class="text-faint">|</span> @endunless
                                                    @endforeach
                                                </span>
                                            </span>
                                        @endforeach
                                    </div>
                                @endforeach
                                <div role="row" class="grid items-center gap-x-2 py-2.5 font-semibold" style="{{ $cols }}">
                                    <span role="rowheader" class="text-ink">Rata-rata</span>
                                    @foreach ($team->members as $member)
                                        @php $avg = $received->get($member->user_id, collect())->sum('score') / ($raters * count(config('capstone.peer_aspects'))); @endphp
                                        <span role="cell" class="text-center tabular text-ink">{{ number_format($avg, 2, ',', '.') }}</span>
                                    @endforeach
                                </div>
                            </div>
                            <p class="mt-2 text-caption text-muted">Angka tebal = rata-rata skor (1–5) yang diterima mahasiswa dari semua rekan satu timnya; di bawahnya nilai dari tiap penilai (0 = belum mengisi, tetap ikut dihitung dalam rata-rata). "Sudah mengisi" = sudah menilai semua rekannya.</p>
                        </div>
                    </li>
                </ul>
            @endif
        </section>
    </div>
</x-modal>
