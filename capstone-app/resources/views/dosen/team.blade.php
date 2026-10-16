@php $approved = $team->logbooks->where('status', 'disetujui')->count(); @endphp
<x-layouts.app :title="$team->code" section="Dosen · Detail tim">
    <x-page-head :title="$team->code.' · '.$team->name" :lede="'Anda sebagai '.$role.' · '.($team->finalTopic?->title ?? 'topik final belum ditetapkan')">
        <x-slot:actions>
            <a href="{{ route('dosen.assessments.index') }}" class="btn btn-secondary">Penilaian</a>
            @if ($role === 'pembimbing')<a href="{{ route('dosen.logbooks.index', ['tim' => $team->id]) }}" class="btn btn-secondary">Log bimbingan</a>@endif
        </x-slot:actions>
    </x-page-head>

    <div class="mb-6 grid gap-6 md:grid-cols-[minmax(0,1.2fr)_minmax(0,1fr)]">
        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Anggota</h2></div>
            <ul class="divide-y divide-rule">
                @foreach ($team->members->where('invite_status', 'diterima') as $m)
                    <li class="px-4 py-3 sm:px-5">
                        <p class="font-medium">{{ $m->user->name }}@if ($m->is_leader) <span class="text-caption font-normal text-muted">ketua</span>@endif</p>
                        <p class="text-sm text-muted"><span class="code">{{ $m->user->nim }}</span> · {{ $m->user->prodi_code }} · {{ $m->user->specialization ?? '—' }} · <a class="link font-normal" href="mailto:{{ $m->user->email }}">{{ $m->user->email }}</a></p>
                    </li>
                @endforeach
            </ul>
        </section>
        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Ringkasan</h2></div>
            <div class="panel-body space-y-4">
                <dl class="kv">
                    <dt>Topik final</dt><dd>{{ $team->finalTopic?->title ?? '—' }}</dd>
                    <dt>Pembimbing</dt><dd>{{ $team->supervisor?->lecturer->name ?? '—' }}</dd>
                    <dt>Penguji</dt><dd>{{ $team->examiner?->lecturer->name ?? '—' }}</dd>
                </dl>
                <x-gate-cells :approved="$approved" :min="$minLogbooks" :pending="$team->logbooks->where('status', 'menunggu')->count()" />
            </div>
        </section>
    </div>

    <h2 class="mb-3 text-h2">Dokumen milestone</h2>
    <div class="space-y-4">
        @forelse ($docMilestones as $milestone)
            @php $docs = $team->documents->where('milestone_id', $milestone->id); @endphp
            <section class="panel">
                <div class="panel-head">
                    <h3 class="panel-title">{{ $milestone->name }}</h3>
                    <span class="text-sm text-muted">tenggat {{ $milestone->due_at->translatedFormat('j M Y H:i') }}</span>
                </div>
                @if ($docs->isEmpty())
                    <p class="panel-body text-sm text-muted">Belum ada dokumen.</p>
                @else
                    <ul class="divide-y divide-rule">
                        @foreach ($docs as $doc)
                            @php $file = $doc->currentVersion ?? $doc->versions->first(); @endphp
                            <li x-data="disclosure" class="px-4 py-3 sm:px-5">
                                <div class="flex flex-wrap items-center gap-3">
                                    <div class="min-w-0 flex-1">
                                        @if ($file)<a href="{{ route('documents.download', $file) }}" class="link break-all">{{ $file->stored_name }}</a>@endif
                                        <p class="text-sm text-muted">{{ $doc->owner->name }} · revisi <span class="font-mono">{{ $file?->revision_no }}</span> · {{ $file?->created_at->translatedFormat('j M Y H:i') }}</p>
                                    </div>
                                    @if ($doc->is_late)<x-badge status="terlambat" />@endif
                                    <x-badge :status="$doc->review_status" />
                                    <button type="button" class="btn btn-ghost btn-sm" @click="toggle">Beri feedback</button>
                                </div>
                                @if ($doc->feedback)<p class="mt-2 text-sm"><span class="text-muted">Feedback:</span> {{ $doc->feedback }}</p>@endif
                                <form x-cloak x-show="expanded" method="POST" action="{{ route('dosen.documents.feedback', $doc) }}" class="mt-3 grid gap-3 rounded-lg border border-rule bg-sunken p-4 sm:grid-cols-[12rem_minmax(0,1fr)_auto] sm:items-end">
                                    @csrf
                                    <div><label class="field-label" for="rs-{{ $doc->id }}">Status</label>
                                        <select id="rs-{{ $doc->id }}" name="review_status" class="input">@foreach (['ditinjau' => 'Ditinjau', 'revisi' => 'Perlu revisi', 'disetujui' => 'Disetujui'] as $k => $l)<option value="{{ $k }}" @selected($doc->review_status === $k)>{{ $l }}</option>@endforeach</select></div>
                                    <div><label class="field-label" for="fb-{{ $doc->id }}">Feedback</label><textarea id="fb-{{ $doc->id }}" name="feedback" rows="2" class="input">{{ $doc->feedback }}</textarea></div>
                                    <button class="btn btn-primary">Simpan</button>
                                </form>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        @empty
            <div class="panel"><x-empty title="Belum ada milestone dokumen" icon="document-text" /></div>
        @endforelse
    </div>
</x-layouts.app>
