<x-layouts.app title="Hasil Penilaian" section="Mahasiswa · Hasil penilaian">
    <x-page-head title="Hasil penilaian" lede="Nilai dan feedback dari setiap komponen penilaian. Nilai CLO peer review bersifat individual; komponen lain berlaku sama untuk seluruh anggota tim." />

    @if (! $team || $team->status !== 'diterima')
        <div class="panel"><x-empty title="Belum ada penilaian" icon="star" /></div>
    @elseif (! $peerDone)
        <div class="panel">
            <x-empty title="Isi peer review terlebih dahulu untuk melihat nilai akhir." icon="lock-closed">
                <a href="{{ route('mahasiswa.peer.index') }}" class="btn btn-primary mt-4">Isi peer review</a>
            </x-empty>
        </div>
    @else
        @if ($mine)
            <section class="panel mb-6">
                <div class="grid gap-6 panel-body md:grid-cols-[14rem_minmax(0,1fr)]">
                    <div>
                        <p class="text-sm text-muted">Nilai akhir</p>
                        @if ($complete)
                            <p class="mt-1 tabular text-5xl font-semibold tracking-tight">{{ number_format($mine['total'], 2, ',', '') }}</p>
                            <p class="mt-1 text-lg font-semibold text-brand-deep">Grade {{ $mine['grade'] }}</p>
                        @else
                            <p class="mt-1 tabular text-3xl text-muted">—</p>
                            <p class="mt-1 text-sm text-warn-ink">Menunggu: {{ collect($missing)->map(fn ($c) => config("capstone.components.$c.label"))->implode(', ') }}</p>
                        @endif
                    </div>
                    <div class="min-w-0">
                        <p class="mb-2 text-sm text-muted">Capaian per CLO</p>
                        <ul class="space-y-2">
                            @foreach ($clos as $clo)
                                @php $c = $mine['clos'][$clo->code]; @endphp
                                <li class="grid grid-cols-[3.5rem_minmax(0,1fr)_4.5rem] items-center gap-3">
                                    <span class="code font-medium">{{ $clo->code }}</span>
                                    <span class="h-2 overflow-hidden rounded-sm bg-sunken"><span class="block h-full origin-left bg-brand" style="transform: scaleX({{ min(1, $c['percent'] / 100) }})"></span></span>
                                    <span class="text-right tabular text-sm">{{ number_format($c['points'], 2, ',', '') }}<span class="text-muted">/{{ $clo->weight + 0 }}</span></span>
                                </li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            </section>
        @endif

        <h2 class="mb-3 text-h2">Feedback per komponen</h2>
        @forelse ($assessments as $a)
            <section class="panel mb-4">
                <div class="panel-head">
                    <h3 class="panel-title">{{ $a->componentLabel() }}</h3>
                    <p class="text-sm text-muted">{{ $a->assessor->name }} · {{ $a->submitted_at?->translatedFormat('j M Y') }}</p>
                </div>
                <div class="panel-body">
                    <p class="whitespace-pre-line">{{ $a->feedback }}</p>
                    <ul class="mt-3 grid gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
                        @foreach ($a->scores as $s)
                            <li class="flex justify-between gap-3 border-b border-dashed border-rule py-1"><span class="min-w-0 truncate"><span class="code text-muted">{{ $s->indicator->code }}</span> {{ $s->indicator->name }}</span><span class="shrink-0 tabular">{{ $s->score }}/5</span></li>
                        @endforeach
                    </ul>
                </div>
            </section>
        @empty
            <div class="panel"><x-empty title="Belum ada komponen yang dinilai final" icon="star" /></div>
        @endforelse
    @endif
</x-layouts.app>
