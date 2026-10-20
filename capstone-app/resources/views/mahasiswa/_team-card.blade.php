{{-- Kartu tim: nama, status registrasi, topik, anggota, dosen. Butuh $team, $supervisor, $examiner. --}}
@php $members = $team->members->where('invite_status', 'diterima'); @endphp
<section class="panel flex min-w-0 flex-col p-5 sm:p-6" aria-labelledby="tim-judul">
    <div class="flex items-start justify-between gap-3">
        <h2 id="tim-judul" class="min-w-0 text-lg font-semibold">{{ $team->name }}</h2>
        <x-badge :status="$team->status" :label="$team->statusLabel()" class="shrink-0" />
    </div>

    <p class="mt-4 text-[0.9375rem] {{ $team->finalTopic ? 'text-ink' : 'text-muted' }}">{{ $team->finalTopic?->title ?? 'Topik final belum ditetapkan.' }}</p>

    <a href="{{ route('mahasiswa.team.show') }}" class="group -mx-2 mt-3 flex min-h-[44px] items-center gap-3 rounded px-2 hover:bg-sunken focus-visible:bg-sunken">
        <span class="flex -space-x-1" aria-hidden="true">
            @foreach ($members->take(4) as $member)
                <span class="grid h-8 w-8 place-items-center rounded-full bg-brand-tint text-[0.6875rem] font-semibold text-brand-deep ring-2 ring-surface" title="{{ $member->user->name }}">{{ $member->user->initials() }}</span>
            @endforeach
        </span>
        <span class="min-w-0 flex-1 text-sm text-muted">{{ $members->count() }} anggota<span class="sr-only">: {{ $members->map(fn ($m) => $m->user->name)->join(', ') }}. Buka detail tim</span></span>
        <x-heroicon-o-chevron-right class="h-4 w-4 shrink-0 text-faint group-hover:text-ink" aria-hidden="true" />
    </a>

    {{-- Dosen: daftar label : nilai, cukup teks — tanpa ubin inisial --}}
    <dl class="mt-3 grid grid-cols-[auto_minmax(0,1fr)] gap-x-4 gap-y-2 border-t border-rule pt-4 text-sm">
        @foreach (['Pembimbing' => $supervisor, 'Penguji' => $examiner] as $role => $lecturer)
            <dt class="text-muted">{{ $role }}</dt>
            <dd class="min-w-0 truncate {{ $lecturer ? 'font-medium text-ink' : 'text-muted' }}">{{ $lecturer?->name ?? 'Belum ditetapkan' }}</dd>
        @endforeach
    </dl>
</section>
