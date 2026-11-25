{{-- Overlay detail tim (dibuka tombol "Lihat detail" di Tim Bimbingan / Tim Uji). Memakai state registrationForm di <tr>.
     Isi: Anggota (KHS), lalu shared/_team-progress (Topik & Objek Final, Sesi Bimbingan → Approval Bimbingan, Deliverable dengan
     Nilai · Lihat · Unduh, Hasil Penilaian).
     Butuh $team (members.user, finalTopic, documents.currentVersion, approved_logbooks, assessment_summary),
     $role, $docMilestones, $minLogbooks, $jenisLabel. --}}
@php
    $card = 'rounded-lg border border-rule';
    $cardHead = 'border-b border-rule px-4 py-3 text-base font-semibold text-ink sm:px-5';
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
                                <span class="inline-block whitespace-nowrap rounded-md px-1.5 py-0.5 text-caption font-medium {{ $member->is_leader ? 'bg-brand-tint text-brand-deep' : 'bg-sunken text-muted' }}">{{ $member->is_leader ? 'Ketua' : 'Anggota' }}</span>
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

        @include('shared._team-progress', ['viewer' => 'dosen'])
    </div>
</x-modal>
