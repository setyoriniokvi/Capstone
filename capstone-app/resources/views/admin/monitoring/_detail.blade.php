{{-- Modal "Detail tim" di Monitoring progres: topik final & dosen, anggota (nama, NIM, peminatan, ketua/anggota),
     pengumpulan dokumen per milestone, dan rincian log bimbingan. Memakai state registrationForm dari elemen induk.
     Butuh $team (members.user, documents, logbook_*), $docMilestones, $minLogbooks, $chip. --}}
@php
    $card = 'rounded-lg border border-rule';
    $cardHead = 'border-b border-rule px-4 py-3 text-base font-semibold text-ink sm:px-5';
@endphp
<x-modal width="sm:max-w-2xl" class="text-left font-normal">
    <x-slot:heading>{{ $team->name }}</x-slot:heading>
    <div class="space-y-5 text-left text-body">
        <section class="{{ $card }}" aria-label="Topik dan dosen {{ $team->name }}">
            <h3 class="{{ $cardHead }}">Topik &amp; dosen</h3>
            <dl class="grid grid-cols-[8.5rem_minmax(0,1fr)] gap-x-3 gap-y-2 px-4 py-4 text-sm sm:px-5">
                <dt class="text-muted">Topik final</dt>
                <dd class="text-ink">{{ $team->finalTopic?->title ?? 'Belum ada' }}
                    @if ($team->finalTopic)<span class="{{ $chip }} ml-1 {{ $team->finalTopic->source === 'dosen' ? 'bg-brand-tint text-brand-deep' : 'border border-rule bg-sunken text-muted' }}">{{ $team->finalTopic->source === 'dosen' ? 'Topik dosen' : 'Topik mandiri' }}</span>@endif
                </dd>
                @foreach (['Dosen pembimbing' => $team->supervisor, 'Dosen penguji' => $team->examiner] as $label => $assigned)
                    <dt class="text-muted">{{ $label }}</dt>
                    <dd class="min-w-0">
                        @if ($assigned)
                            <p class="text-ink">{{ $assigned->lecturer->name }}</p>
                            <a class="link break-all font-normal" href="mailto:{{ $assigned->lecturer->email }}">{{ $assigned->lecturer->email }}</a>
                        @else
                            <span class="text-faint">Belum diplot</span>
                        @endif
                    </dd>
                @endforeach
            </dl>
        </section>

        <section class="{{ $card }}" aria-label="Anggota {{ $team->name }}">
            <h3 class="{{ $cardHead }}">Anggota</h3>
            <ul class="divide-y divide-rule">
                @forelse ($team->members as $member)
                    <li class="flex items-center gap-3 px-4 py-3 sm:px-5">
                        <div class="min-w-0 flex-1">
                            <p class="font-medium text-ink">{{ $member->user->name }}</p>
                            <p class="text-caption text-muted"><span class="code">{{ $member->user->nim ?? '—' }}</span> · {{ $member->user->specialization ?? 'Peminatan belum diisi' }}</p>
                        </div>
                        <span class="{{ $chip }} {{ $member->is_leader ? 'bg-brand-tint text-brand-deep' : 'border border-rule bg-sunken text-muted' }}">{{ $member->is_leader ? 'Ketua' : 'Anggota' }}</span>
                    </li>
                @empty
                    <li class="px-4 py-3 text-sm text-muted sm:px-5">Belum ada anggota.</li>
                @endforelse
            </ul>
        </section>

        <section class="{{ $card }}" aria-label="Dokumen {{ $team->name }}">
            <h3 class="{{ $cardHead }}">Dokumen</h3>
            <ul class="divide-y divide-rule">
                @forelse ($docMilestones as $m)
                    @php
                        $docs = $team->documents->where('milestone_id', $m->id);
                        [$label, $tone] = $docs->isNotEmpty()
                            ? ($docs->contains('is_late', true) ? ['Terkumpul · terlambat', 'text-warn-ink'] : ['Terkumpul', 'text-ok-ink'])
                            : ($m->isPast() ? ['Belum dikumpulkan', 'text-bad-ink'] : ['Belum jatuh tempo', 'text-faint']);
                    @endphp
                    <li class="flex items-center justify-between gap-3 px-4 py-3 text-sm sm:px-5">
                        <div class="min-w-0">
                            <p class="text-ink">{{ $m->name }}</p>
                            @if ($m->due_at)<p class="text-caption text-muted">Deadline {{ $m->due_at->translatedFormat('j F Y H:i') }}</p>@endif
                        </div>
                        <span class="shrink-0 font-medium {{ $tone }}">{{ $label }}</span>
                    </li>
                @empty
                    <li class="px-4 py-3 text-sm text-muted sm:px-5">Belum ada milestone dokumen.</li>
                @endforelse
            </ul>
        </section>

        <section class="{{ $card }}" aria-label="Log bimbingan {{ $team->name }}">
            <h3 class="{{ $cardHead }}">Log bimbingan</h3>
            <div class="space-y-3 px-4 py-4 sm:px-5">
                <x-gate-cells :approved="$team->logbook_approved" :min="$minLogbooks" :pending="$team->logbook_pending" />
                <dl class="grid grid-cols-4 gap-3 text-sm">
                    @foreach (['Total' => $team->logbook_total, 'Disetujui' => $team->logbook_approved, 'Menunggu' => $team->logbook_pending, 'Ditolak' => $team->logbook_revision] as $label => $count)
                        <div class="rounded-md bg-sunken px-3 py-2"><dt class="text-caption text-muted">{{ $label }}</dt><dd class="tabular font-semibold text-ink">{{ $count }}</dd></div>
                    @endforeach
                </dl>
            </div>
        </section>
    </div>
</x-modal>
