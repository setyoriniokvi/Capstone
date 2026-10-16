@php
    $docMilestones = $period?->milestones->where('kind', 'dokumen')->sortBy('due_at')->values() ?? collect();
    $submitted = fn ($m) => $team ? $team->documents->contains('milestone_id', $m->id) : false;
@endphp
<x-layouts.app title="Dashboard Tim" section="Mahasiswa · Dashboard">
    @if (! $period)
        <x-page-head title="Halo, {{ auth()->user()->name }}" />
        <div class="panel"><x-empty title="Belum ada periode Capstone yang aktif" icon="calendar-days">Informasi timeline akan muncul setelah admin membuka periode.</x-empty></div>
    @else
        <div class="mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div class="min-w-0">
                <p class="text-sm text-muted">{{ $period->name }}</p>
                @if ($team)
                    <h1 class="mt-1 text-h1"><span class="code mr-2 text-[1.25rem] text-brand-deep">{{ $team->code }}</span>{{ $team->name }}</h1>
                    <p class="mt-1.5 max-w-[70ch] text-muted">{{ $team->finalTopic?->title ?? 'Topik final belum ditetapkan.' }}</p>
                @else
                    <h1 class="mt-1 text-h1">Halo, {{ \Illuminate\Support\Str::of(auth()->user()->name)->explode(' ')->first() }}</h1>
                    <p class="mt-1.5 text-muted">Anda belum tergabung dalam tim Capstone.</p>
                @endif
            </div>
            <div class="flex flex-wrap gap-2">
                @if ($team)
                    <x-badge :status="$team->status" :label="'Registrasi '.strtolower($team->statusLabel())" class="text-sm" />
                @else
                    <a href="{{ route('mahasiswa.team.show') }}" class="btn btn-primary">Bentuk tim</a>
                @endif
            </div>
        </div>

        @if ($invitations->isNotEmpty())
            <div class="mb-6 rounded-lg border border-warn-line bg-warn-tint px-4 py-3 text-sm text-warn-ink">
                Anda memiliki {{ $invitations->count() }} undangan tim. <a href="{{ route('mahasiswa.team.show') }}" class="font-medium underline underline-offset-4">Lihat undangan</a>
            </div>
        @endif

        <section class="panel mb-6">
            <div class="panel-head"><h2 class="panel-title">Alur Capstone & tenggat</h2></div>
            <div class="panel-body">
                @if ($period->milestones->isEmpty())
                    <p class="text-sm text-muted">Admin belum menyusun timeline.</p>
                @else
                    <x-week-ruler :period="$period" :highlight="fn ($m) => $m->kind === 'dokumen' ? $submitted($m) : null" />
                @endif
            </div>
        </section>

        <div class="grid gap-6 md:grid-cols-[minmax(0,1.35fr)_minmax(0,1fr)]">
            <div class="space-y-6">
                <section class="panel">
                    <div class="panel-head"><h2 class="panel-title">Milestone dokumen</h2><a href="{{ route('mahasiswa.documents.index') }}" class="link text-sm">Kelola dokumen</a></div>
                    @if ($docMilestones->isEmpty())
                        <p class="panel-body text-sm text-muted">Belum ada milestone pengumpulan dokumen.</p>
                    @else
                        <ol class="divide-y divide-rule">
                            @foreach ($docMilestones as $m)
                                @php $done = $submitted($m); $doc = $team?->documents->firstWhere('milestone_id', $m->id); @endphp
                                <li class="flex items-center gap-4 px-4 py-3 sm:px-5">
                                    <span class="grid h-8 w-8 shrink-0 place-items-center rounded-full border {{ $done ? 'border-brand bg-brand text-white' : ($m->isPast() ? 'border-bad-line bg-bad-tint text-bad-ink' : 'border-rule-strong text-muted') }}">
                                        @if ($done)<x-heroicon-o-check class="h-4 w-4" />@else<span class="font-mono text-caption">{{ $m->week_no ?? '·' }}</span>@endif
                                    </span>
                                    <div class="min-w-0 flex-1">
                                        <p class="font-medium">{{ $m->name }}</p>
                                        <p class="text-sm text-muted">Tenggat {{ $m->due_at->translatedFormat('l, j M Y H:i') }}</p>
                                    </div>
                                    @if ($doc)
                                        <x-badge :status="$doc->is_late ? 'terlambat' : $doc->review_status" />
                                    @elseif ($m->isPast())
                                        <x-badge status="terlambat" label="Belum dikumpulkan" />
                                    @elseif ($m->isOpen())
                                        <span class="text-sm text-muted">{{ $m->due_at->diffForHumans(['parts' => 2]) }}</span>
                                    @endif
                                </li>
                            @endforeach
                        </ol>
                    @endif
                </section>

                <section class="panel">
                    <div class="panel-head"><h2 class="panel-title">Log bimbingan</h2><a href="{{ route('mahasiswa.logbooks.index') }}" class="link text-sm">Isi log</a></div>
                    <div class="panel-body">
                        @if ($logbook)
                            <x-gate-cells :approved="$logbook['approved']" :min="$minLogbooks" :pending="$logbook['pending']" />
                            @if ($logbook['revision'])<p class="mt-2 text-sm text-bad-ink">{{ $logbook['revision'] }} log perlu direvisi.</p>@endif
                        @else
                            <p class="text-sm text-muted">Tersedia setelah Anda tergabung dalam tim.</p>
                        @endif
                    </div>
                </section>
            </div>

            <div class="space-y-6">
                <section class="panel">
                    <div class="panel-head"><h2 class="panel-title">Kelayakan prasyarat</h2></div>
                    <ul class="panel-body space-y-2.5">
                        @foreach ($eligibility as $check)
                            <li class="flex gap-3">
                                @if ($check['passed'])<x-heroicon-o-check-circle class="h-5 w-5 shrink-0 text-ok-ink" />@else<x-heroicon-o-x-circle class="h-5 w-5 shrink-0 text-bad-ink" />@endif
                                <span class="min-w-0"><span class="block">{{ $check['label'] }}</span><span class="block text-caption text-muted">{{ $check['detail'] }}</span></span>
                            </li>
                        @endforeach
                    </ul>
                    @if (collect($eligibility)->contains('passed', false))
                        <p class="border-t border-rule px-4 py-3 text-sm text-muted sm:px-5">Bila data belum sesuai, hubungi admin program studi untuk pembaruan data akademik.</p>
                    @endif
                </section>

                @if ($team)
                    <section class="panel">
                        <div class="panel-head"><h2 class="panel-title">Tim & dosen</h2></div>
                        <dl class="kv panel-body">
                            <dt>Anggota</dt>
                            <dd>@foreach ($team->members->where('invite_status', 'diterima') as $m)<span class="block">{{ $m->user->name }}@if ($m->is_leader) <span class="text-caption text-muted">ketua</span>@endif</span>@endforeach</dd>
                            <dt>Pembimbing</dt><dd>{{ $team->supervisor?->lecturer->name ?? '—' }}</dd>
                            <dt>Penguji</dt><dd>{{ $team->examiner?->lecturer->name ?? '—' }}</dd>
                        </dl>
                    </section>
                @endif
            </div>
        </div>
    @endif
</x-layouts.app>
