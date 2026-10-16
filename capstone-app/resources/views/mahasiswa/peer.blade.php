<x-layouts.app title="Peer Review" section="Mahasiswa · Peer review">
    <x-page-head title="Peer review" lede="Nilai setiap rekan satu tim pada enam aspek kerja tim, skala 1–5. Isian dapat diubah (ditimpa) selama periode peer review dibuka. Nilai akhir terbuka setelah peer review terisi." />

    @if (! $team || $team->status !== 'diterima')
        <div class="panel"><x-empty title="Peer review tersedia setelah tim diterima" icon="users" /></div>
    @elseif ($teammates->isEmpty())
        <div class="panel"><x-empty title="Tidak ada rekan satu tim untuk dinilai" icon="users" /></div>
    @else
        <form method="POST" action="{{ route('mahasiswa.peer.store') }}" class="space-y-6">
            @csrf
            @foreach ($aspects as $aspect => $label)
                <section class="panel">
                    <div class="border-b border-rule px-4 py-3 sm:px-5">
                        <h2 class="panel-title">{{ $label }}</h2>
                        <p class="mt-0.5 text-sm text-muted">{{ $rubric[$aspect]['definition'] }}</p>
                        <details class="mt-2 text-sm">
                            <summary class="cursor-pointer text-brand-deep">Lihat rubrik perilaku 1–5</summary>
                            <dl class="mt-2 space-y-1.5">
                                @foreach ($rubric[$aspect]['levels'] as $score => $text)
                                    <div class="grid grid-cols-[1.5rem_minmax(0,1fr)] gap-2"><dt class="font-mono font-medium">{{ $score }}</dt><dd class="text-muted">{{ $text }}</dd></div>
                                @endforeach
                            </dl>
                        </details>
                    </div>
                    <div class="divide-y divide-rule">
                        @foreach ($teammates as $mate)
                            @php $current = old("scores.{$mate->id}.{$aspect}", $existing->get($mate->id)?->get($aspect)); @endphp
                            <fieldset class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 sm:px-5">
                                <legend class="sr-only">{{ $label }} — {{ $mate->name }}</legend>
                                <span class="font-medium" aria-hidden="true">{{ $mate->name }}</span>
                                <div class="flex gap-1.5">
                                    @for ($s = 1; $s <= 5; $s++)
                                        <label class="relative">
                                            <input type="radio" class="peer sr-only" name="scores[{{ $mate->id }}][{{ $aspect }}]" value="{{ $s }}" @checked((int) $current === $s) required>
                                            <span class="grid h-11 w-11 cursor-pointer place-items-center rounded border border-rule-strong bg-surface font-mono text-sm peer-checked:border-brand peer-checked:bg-brand peer-checked:text-white peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-brand" title="{{ $rubric[$aspect]['levels'][$s] }}">{{ $s }}</span>
                                        </label>
                                    @endfor
                                </div>
                            </fieldset>
                        @endforeach
                    </div>
                </section>
            @endforeach
            <div class="sticky bottom-0 -mx-4 flex justify-end border-t border-rule bg-paper px-4 py-3 sm:-mx-8 sm:px-8">
                <button class="btn btn-primary" data-once>Simpan peer review</button>
            </div>
        </form>
    @endif
</x-layouts.app>
