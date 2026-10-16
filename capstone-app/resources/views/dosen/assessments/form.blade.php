@php
    $readonlyWindow = $window && ! $window->isOpen();
    $isFinal = $assessment?->status === 'final';
@endphp
<x-layouts.app :title="'Penilaian '.$team->code" section="Dosen · Penilaian">
    <x-page-head :title="$label" :lede="$team->code.' · '.$team->name.' · Anda sebagai '.$role">
        <x-slot:actions>
            @if ($isFinal)<x-badge status="final" label="Sudah final" class="text-sm" />@elseif ($assessment)<x-badge status="draft" label="Draf tersimpan" class="text-sm" />@endif
            <a href="{{ route('dosen.assessments.index') }}" class="btn btn-ghost">Kembali</a>
        </x-slot:actions>
    </x-page-head>

    @if ($viaQr)
        <p class="mb-4 rounded-lg border border-ok-line bg-ok-tint px-4 py-3 text-sm text-ok-ink">Dibuka dari QR Code tim. Identitas tim telah diverifikasi sistem.</p>
    @endif
    @if ($window)
        <p class="mb-4 text-sm {{ $readonlyWindow ? 'text-bad-ink' : 'text-muted' }}">Jendela penilaian {{ $window->opens_at->translatedFormat('j M Y H:i') }} – {{ $window->due_at->translatedFormat('j M Y H:i') }}{{ $readonlyWindow ? ' · saat ini tidak dapat disimpan.' : '' }}</p>
    @endif

    <section class="panel mb-6">
        <dl class="kv panel-body">
            <dt>Topik</dt><dd>{{ $team->finalTopic?->title ?? '—' }}</dd>
            <dt>Anggota</dt><dd>{{ $team->members->where('invite_status', 'diterima')->pluck('user.name')->implode(', ') }}</dd>
            <dt>Rumus</dt><dd>Nilai indikator = (Skor ÷ 5) × Bobot × Faktor pemahaman <span class="text-muted">(faktor hanya untuk skema lisan)</span></dd>
        </dl>
    </section>

    @if ($indicators->isEmpty())
        <div class="panel"><x-empty title="Belum ada indikator dengan bobot pada komponen ini" icon="list-bullet">Hubungi admin untuk melengkapi master indikator penilaian.</x-empty></div>
    @else
        <form method="POST" action="{{ route('dosen.assessments.update', [$team, $componentKey]) }}" class="space-y-5">
            @csrf @method('PUT')
            @if ($viaQr)<input type="hidden" name="via" value="qr">@endif

            @foreach ($indicators as $indicator)
                @php
                    $saved = $scores->get($indicator->id);
                    $selected = old("scores.{$indicator->id}.score", $saved?->score);
                    $selectedOral = old("scores.{$indicator->id}.oral_level", $saved?->oral_level);
                @endphp
                <fieldset class="panel">
                    <legend class="sr-only">{{ $indicator->code }} {{ $indicator->name }}</legend>
                    <div class="flex flex-wrap items-start gap-3 border-b border-rule px-4 py-3 sm:px-5">
                        <span class="code pt-0.5 font-medium text-brand-deep">{{ $indicator->code }}</span>
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ $indicator->name }}</p>
                            <p class="text-sm text-muted">{{ $indicator->clo->code }} · {{ $indicator->schemeLabel() }}@if ($indicator->evidence) · acuan: {{ $indicator->evidence }}@endif</p>
                            @if ($indicator->rubric?->assessment_points)<p class="mt-1 text-sm">{{ $indicator->rubric->assessment_points }}</p>@endif
                        </div>
                        <span class="whitespace-nowrap font-mono text-sm text-muted">bobot {{ $indicator->weightFor($componentKey) + 0 }}</span>
                    </div>

                    {{-- Tabel rubrik — kolom skor terpilih disorot (PRD §10.3) --}}
                    <div class="grid gap-px bg-rule sm:grid-cols-6" role="radiogroup" aria-label="Skor {{ $indicator->code }}">
                        @foreach ($scoreLevels as $level => $meta)
                            <label class="relative block cursor-pointer bg-surface">
                                <input type="radio" class="peer sr-only" name="scores[{{ $indicator->id }}][score]" value="{{ $level }}" @checked((string) $selected === (string) $level) @disabled($readonlyWindow)>
                                <span class="flex h-full min-h-[44px] flex-col gap-1 px-3 py-2.5 hover:bg-brand-wash peer-checked:bg-brand-tint peer-checked:ring-2 peer-checked:ring-inset peer-checked:ring-brand peer-focus-visible:ring-2 peer-focus-visible:ring-inset peer-focus-visible:ring-brand">
                                    <span class="flex items-baseline justify-between gap-2">
                                        <span class="font-mono text-lg font-medium">{{ $level }}</span>
                                        <span class="text-caption text-muted">{{ $meta['label'] }} · {{ $meta['value'] }}</span>
                                    </span>
                                    @if ($indicator->rubric)
                                        <span class="text-caption leading-snug text-ink">{{ $indicator->rubric->describe($level) }}</span>
                                    @endif
                                </span>
                            </label>
                        @endforeach
                    </div>
                    @if ($indicator->rubric?->example_level_5)
                        <details class="border-t border-rule px-4 py-2 text-sm sm:px-5"><summary class="cursor-pointer text-brand-deep">Contoh pemenuhan tingkat 5</summary><p class="mt-1 text-muted">{{ $indicator->rubric->example_level_5 }}</p></details>
                    @endif

                    @if ($indicator->usesOralFactor())
                        <div class="border-t border-rule bg-sunken px-4 py-3 sm:px-5">
                            <label class="field-label" for="oral-{{ $indicator->id }}">Pemahaman saat verifikasi lisan</label>
                            <select id="oral-{{ $indicator->id }}" name="scores[{{ $indicator->id }}][oral_level]" class="input max-w-xl" @disabled($readonlyWindow)>
                                <option value="">— pilih level —</option>
                                @foreach ($oralLevels as $lvl)
                                    <option value="{{ $lvl->level }}" @selected((string) $selectedOral === (string) $lvl->level)>Level {{ $lvl->level }} · {{ $lvl->label }} · faktor {{ number_format($lvl->factor, 2, ',', '') }}</option>
                                @endforeach
                            </select>
                            @if ($oralLevels->isNotEmpty())
                                <details class="mt-2 text-sm"><summary class="cursor-pointer text-muted">Perilaku yang teramati per level</summary>
                                    <dl class="mt-1 space-y-1">@foreach ($oralLevels as $lvl)<div class="grid grid-cols-[4.5rem_minmax(0,1fr)] gap-2"><dt class="font-mono">L{{ $lvl->level }}</dt><dd class="text-muted">{{ $lvl->behavior }}</dd></div>@endforeach</dl>
                                </details>
                            @endif
                        </div>
                    @endif
                    @error("scores.{$indicator->id}.score")<p class="field-error px-4 pb-3 sm:px-5">{{ $message }}</p>@enderror
                </fieldset>
            @endforeach

            <section class="panel">
                <div class="panel-body">
                    <x-field label="Feedback untuk tim" name="feedback" hint="Ditampilkan kepada mahasiswa bersama nilai komponen ini.">
                        <textarea id="feedback" name="feedback" rows="4" class="input" @disabled($readonlyWindow)>{{ old('feedback', $assessment?->feedback) }}</textarea>
                    </x-field>
                </div>
            </section>

            @unless ($readonlyWindow)
                <div class="sticky bottom-0 -mx-4 flex flex-wrap justify-end gap-2 border-t border-rule bg-paper px-4 py-3 sm:-mx-8 sm:px-8">
                    @unless ($isFinal)<button name="action" value="draft" class="btn btn-secondary">Simpan draf</button>@endunless
                    <button name="action" value="final" class="btn btn-primary" data-once>{{ $isFinal ? 'Perbarui nilai final' : 'Simpan penilaian' }}</button>
                </div>
            @endunless
        </form>
    @endif
</x-layouts.app>
