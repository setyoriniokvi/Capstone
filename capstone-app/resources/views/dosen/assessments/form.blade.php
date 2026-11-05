{{-- F47 — Formulir penilaian dosen: tabel rubrik seperti Rubrik Dokumen admin (master data penilaian), satu baris per
     indikator berbobot pada bentuk penilaian ini. Kolom: centang · Kode · CLO · Indikator · Bobot · Nilai (dibekukan di layar
     ≥ sm) · Poin Penilaian · deskripsi tingkat 5–0 · Contoh Pemenuhan Tingkat 5.
     Cara menilai: centang indikator (satu, beberapa, atau satu CLO sekaligus) → pilih skor (+ pemahaman lisan bila ada
     indikator lisan) → "Simpan" (dosen.assessments.scores); "Hapus pilihan" mengosongkan skornya lagi.
     Baris yang sudah dinilai berwarna hijau, yang belum putih.
     Skor tersimpan langsung (real time); status Lengkap otomatis bila semua indikator sudah dinilai.
     Toolbar: progres "X dari N indikator sudah dinilai" + keterangan warna; perilaku per level lisan (lipat) di atas judul
     kolom, dengan kotak centang "Bekukan kolom indikator" (Alpine "freezeColumns") untuk membekukan / melepas kolom
     Kode–Nilai. Di bawah: feedback untuk tim (opsional) + Simpan feedback. Pemilihan baris memakai Alpine "rowSelection". --}}
@php
    $readonlyWindow = $window && ! $window->isOpen();
    $isFinal = $assessment?->status === 'final';
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $scoreText = fn ($level, $meta) => "{$level} - {$meta['label']} ({$meta['value']})";
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $total = $indicators->count();
    $scored = $indicators->filter(fn ($i) => $scores->has($i->id))->count();
    $hasOral = $indicators->contains(fn ($i) => $i->usesOralFactor());
    // Kolom beku (layar ≥ sm): centang · Kode · CLO · Indikator · Bobot · Nilai; posisi kiri = jumlah lebar kolom sebelumnya.
    $freeze = ['sm:sticky sm:left-0', 'sm:sticky sm:left-[3rem]', 'sm:sticky sm:left-[7.5rem]', 'sm:sticky sm:left-[12.5rem]', 'sm:sticky sm:left-[26.5rem]', 'sm:sticky sm:left-[31.5rem] sm:border-r-2 sm:border-r-rule-strong'];
@endphp
<x-layouts.app :title="'Penilaian '.$team->name">
    <x-page-head :title="$label" :lede="$team->name.' · Anda sebagai '.$role">
        <x-slot:actions>
            @if ($isFinal)<x-badge status="final" label="Lengkap" class="text-sm" />@elseif ($scored > 0)<x-badge status="draft" label="Sebagian dinilai" class="text-sm" />@endif
            <a href="{{ route('dosen.assessments.index', ['komponen' => $componentKey]) }}" class="btn btn-ghost">Kembali</a>
        </x-slot:actions>
    </x-page-head>

    @if ($viaQr)
        <p class="mb-4 rounded-lg border border-ok-line bg-ok-tint px-4 py-3 text-sm text-ok-ink">Dibuka dari QR Code tim. Identitas tim telah diverifikasi sistem.</p>
    @endif
    @if ($readonlyWindow)
            {{-- Isian dikunci di luar jendela penilaian: jelaskan kenapa tidak dapat diklik --}}
            <div class="mb-4 flex gap-3 rounded-lg border border-warn-line bg-warn-tint px-4 py-3 text-sm text-warn-ink" role="status">
                <x-heroicon-o-lock-closed class="mt-0.5 h-5 w-5 shrink-0" />
                <p><span class="font-semibold">Penilaian {{ now()->lt($window->opens_at) ? 'belum dibuka' : 'sudah ditutup' }}.</span>
                    Jendela penilaian {{ $window->opens_at->translatedFormat('j M Y H:i') }} – {{ $window->due_at->translatedFormat('j M Y H:i') }}, jadi centang indikator, skor, dan pemahaman lisan dikunci. Hubungi admin bila jadwal perlu diubah.</p>
            </div>
    @endif

    <section class="panel mb-6">
        <dl class="kv panel-body">
            <dt>Topik</dt><dd>{{ $team->finalTopic?->title ?? '—' }}</dd>
            <dt>Anggota</dt><dd>{{ $team->members->where('invite_status', 'diterima')->pluck('user.name')->implode(', ') }}</dd>
            <dt>Rumus</dt><dd>Nilai indikator = (Skor ÷ 5) × Bobot × Faktor pemahaman <span class="text-muted">(faktor hanya untuk skema lisan)</span>.</dd>
        </dl>
    </section>

    @foreach (['gate', 'window', 'scores', 'indicator_ids', 'score', 'oral_level'] as $key)
        @error($key)<p class="mb-4 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror
    @endforeach

    @if ($indicators->isEmpty())
        <div class="panel"><x-empty title="Belum ada indikator dengan bobot pada komponen ini" icon="list-bullet">Hubungi admin untuk melengkapi master indikator penilaian.</x-empty></div>
    @else
        <section x-data="rowSelection" data-row-selection class="panel mb-6 overflow-hidden" aria-labelledby="penilaian-judul">
            <h2 id="penilaian-judul" class="sr-only">Penilaian per indikator</h2>
            {{-- Toolbar: progres · pilih skor · pemahaman lisan · Simpan penilaian (untuk indikator yang dicentang) --}}
            <form method="POST" action="{{ route('dosen.assessments.scores', [$team, $componentKey]) }}" class="flex flex-wrap items-center gap-4 px-5 py-4 sm:px-6">
                @csrf @method('PUT')
                @if ($viaQr)<input type="hidden" name="via" value="qr">@endif
                <template x-for="id in ids" :key="id"><input type="hidden" name="indicator_ids[]" :value="id"></template>
                {{-- Progres + keterangan warna baris --}}
                <div class="min-w-[14rem] flex-1">
                    <p class="text-sm text-muted"><span class="tabular font-semibold text-ink">{{ $scored }}</span> dari <span class="tabular">{{ $total }}</span> indikator sudah dinilai</p>
                    <x-progress-bar class="mt-2" :value="$scored" :max="$total" label="Indikator sudah dinilai" />
                    <p class="mt-2 flex flex-wrap items-center gap-x-4 gap-y-1 text-caption text-muted">
                        <span class="inline-flex items-center gap-1.5"><span class="h-3.5 w-5 rounded border border-ok-line bg-ok-tint" aria-hidden="true"></span>Sudah dinilai</span>
                        <span class="inline-flex items-center gap-1.5"><span class="h-3.5 w-5 rounded border border-rule bg-surface" aria-hidden="true"></span>Belum dinilai</span>
                    </p>
                </div>
                {{-- Skor · Pemahaman lisan · Simpan · Hapus pilihan selalu sebaris di dalam kartu: isian menyusut mengikuti ruang;
                     lebar tetap hanya di layar sangat lebar (sebaris dengan progres) --}}
                <div class="flex w-full min-w-0 items-end gap-2 sm:gap-3 2xl:w-auto">
                <div class="min-w-0 flex-1 2xl:w-48 2xl:flex-none">
                    <label class="field-label" for="pilih-skor">Skor</label>
                    <select id="pilih-skor" name="score" class="input" required @disabled($readonlyWindow)>
                        <option value="">Pilih skor</option>
                        @foreach ($scoreLevels as $level => $meta)<option value="{{ $level }}" @selected((string) old('score') === (string) $level)>{{ $scoreText($level, $meta) }}</option>@endforeach
                    </select>
                </div>
                @if ($hasOral)
                    <div class="min-w-0 flex-1 2xl:w-56 2xl:flex-none">
                        <label class="field-label" for="pilih-lisan">Pemahaman lisan</label>
                        <select id="pilih-lisan" name="oral_level" class="input" @disabled($readonlyWindow)>
                            <option value="">Pilih level</option>
                            @foreach ($oralLevels as $lvl)<option value="{{ $lvl->level }}" @selected((string) old('oral_level') === (string) $lvl->level)>{{ $lvl->level }} - {{ $lvl->label }} ({{ number_format($lvl->factor, 2, ',', '') }})</option>@endforeach
                        </select>
                    </div>
                @endif
                <button class="btn btn-primary min-h-[44px] shrink-0 px-3 sm:px-4 disabled:cursor-not-allowed disabled:opacity-50" data-once :disabled="noneSelected" disabled title="Centang indikator terlebih dahulu">
                    <x-heroicon-o-check class="h-5 w-5" /> Simpan
                </button>
                {{-- Hapus pilihan: mengirim formulir #hapus-skor (di luar formulir ini) --}}
                <button type="submit" form="hapus-skor" class="btn btn-secondary min-h-[44px] shrink-0 px-3 text-bad-ink sm:px-4 disabled:cursor-not-allowed disabled:opacity-50" :disabled="noneSelected" disabled title="Centang indikator terlebih dahulu">
                    <x-heroicon-o-trash class="h-5 w-5" /> <span>Hapus<span class="hidden sm:inline"> pilihan</span></span>
                </button>
                </div>
            </form>

            {{-- Hapus pilihan: kosongkan skor indikator yang dicentang --}}
            <form id="hapus-skor" method="POST" action="{{ route('dosen.assessments.scores.destroy', [$team, $componentKey]) }}" class="hidden"
                  data-confirm="Hapus skor indikator yang dipilih? Barisnya kembali belum dinilai." data-confirm-label="Hapus">
                @csrf @method('DELETE')
                @if ($viaQr)<input type="hidden" name="via" value="qr">@endif
                <template x-for="id in ids" :key="id"><input type="hidden" name="indicator_ids[]" :value="id"></template>
            </form>

            {{-- Perilaku teramati per level pemahaman lisan (kiri) · "Bekukan kolom indikator" (kanan) — di atas baris judul kolom --}}
            <div x-data="freezeColumns">
            <div class="flex items-start justify-between gap-4 border-t border-rule px-5 py-3 text-sm sm:px-6">
                @if ($hasOral && $oralLevels->isNotEmpty())
                    <details class="min-w-0 flex-1"><summary class="cursor-pointer text-muted">Perilaku yang teramati per level pemahaman lisan</summary>
                        <dl class="mt-1 space-y-1">@foreach ($oralLevels as $lvl)<div class="grid grid-cols-[2rem_minmax(0,1fr)] gap-2"><dt class="tabular">{{ $lvl->level }}</dt><dd class="text-muted">{{ $lvl->label }} - {{ $lvl->behavior }}</dd></div>@endforeach</dl>
                    </details>
                @else
                    <span></span>
                @endif
                <label class="hidden shrink-0 cursor-pointer items-center gap-2 text-ink sm:flex">
                    <input type="checkbox" class="checkbox" :checked="frozen" @change="toggle" checked>
                    Bekukan kolom indikator
                </label>
            </div>

            <x-scroll-pager class="border-t border-rule">
                <table :class="tableClass" class="data-table w-full min-w-[160rem] table-fixed border-separate border-spacing-0 [&_td]:align-top [&_td]:whitespace-pre-line [&_td]:text-sm [&_td+td]:border-l [&_td]:border-rule" @change="pick">
                    <colgroup>
                        <col class="w-[3rem]"><col class="w-[4.5rem]"><col class="w-[5rem]"><col class="w-[14rem]"><col class="w-[5rem]"><col class="w-[9rem]"><col class="w-[16rem]">
                        @foreach ($scoreLevels as $level => $meta)<col class="w-[16rem]">@endforeach
                        <col class="w-[18rem]">
                    </colgroup>
                    <thead class="{{ $greenHead }}"><tr>
                        <th class="{{ $freeze[0] }} sm:z-[1] bg-brand-tint"><input type="checkbox" class="checkbox" data-select-all aria-label="Pilih semua indikator" @disabled($readonlyWindow)></th>
                        <th class="{{ $freeze[1] }} sm:z-[1] bg-brand-tint">Kode</th>
                        <th class="{{ $freeze[2] }} sm:z-[1] bg-brand-tint">CLO</th>
                        <th class="{{ $freeze[3] }} sm:z-[1] bg-brand-tint">Indikator</th>
                        <th class="{{ $freeze[4] }} sm:z-[1] bg-brand-tint text-center">Bobot</th>
                        <th class="{{ $freeze[5] }} sm:z-[1] bg-brand-tint">Nilai</th>
                        <th>Poin Penilaian</th>
                        @foreach ($scoreLevels as $level => $meta)<th>{{ $scoreText($level, $meta) }}</th>@endforeach
                        <th>Contoh Pemenuhan Tingkat 5</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($indicators as $indicator)
                            @php
                                $saved = $scores->get($indicator->id);
                                // Hijau = sudah dinilai (tetap hijau saat disorot); putih = belum
                                $bg = $saved ? '!bg-ok-tint' : 'bg-surface';
                                $rubric = $indicator->rubric;
                            @endphp
                            <tr @if ($saved) data-scored @endif>
                                <td class="{{ $freeze[0] }} sm:z-[1] {{ $bg }}"><input type="checkbox" class="checkbox" value="{{ $indicator->id }}" data-select-row aria-label="Pilih indikator {{ $indicator->code }}" @disabled($readonlyWindow)></td>
                                <td class="{{ $freeze[1] }} sm:z-[1] {{ $bg }} code font-medium text-ink">{{ $indicator->code }}</td>
                                <td class="{{ $freeze[2] }} sm:z-[1] {{ $bg }} code text-ink">{{ $indicator->clo->code }}</td>
                                <td class="{{ $freeze[3] }} sm:z-[1] {{ $bg }}"><span class="block font-medium text-ink">{{ $indicator->name }}</span><span class="mt-1 block text-caption text-muted">{{ $indicator->schemeLabel() }}@if ($indicator->evidence) · {{ $indicator->evidence }}@endif</span></td>
                                <td class="{{ $freeze[4] }} sm:z-[1] {{ $bg }} text-center tabular text-ink">{{ $num($indicator->weightFor($componentKey)) }}</td>
                                <td class="{{ $freeze[5] }} sm:z-[1] {{ $bg }}">
                                    @if ($saved)
                                        <span class="block font-semibold text-ok-ink">{{ $saved->score }} · {{ $scoreLevels[$saved->score]['label'] ?? '' }}</span>
                                        @if ($saved->oral_level)<span class="mt-0.5 block text-caption text-muted">Lisan {{ $saved->oral_level }} ({{ number_format($saved->factor, 2, ',', '') }})</span>@endif
                                    @else
                                        <span class="text-muted">Belum dinilai</span>
                                    @endif
                                </td>
                                <td class="{{ $bg }} {{ $rubric?->assessment_points ? 'text-ink' : 'text-muted' }}">{{ $rubric?->assessment_points ?: '—' }}</td>
                                @foreach ($scoreLevels as $level => $meta)<td class="{{ $bg }} text-ink">{{ $rubric?->describe($level) ?: '—' }}</td>@endforeach
                                <td class="{{ $bg }} {{ $rubric?->example_level_5 ? 'text-ink' : 'text-muted' }}">{{ $rubric?->example_level_5 ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-scroll-pager>
            </div>
        </section>

        {{-- Feedback untuk tim (opsional) — skor sudah tersimpan langsung setiap kali Simpan ditekan --}}
        <form method="POST" action="{{ route('dosen.assessments.update', [$team, $componentKey]) }}" class="panel">
            @csrf @method('PUT')
            @if ($viaQr)<input type="hidden" name="via" value="qr">@endif
            <div class="panel-body">
                <x-field label="Feedback untuk tim" name="feedback" hint="Opsional. Ditampilkan kepada mahasiswa bersama nilai komponen ini.">
                    <textarea id="feedback" name="feedback" rows="4" class="input" @disabled($readonlyWindow)>{{ old('feedback', $assessment?->feedback) }}</textarea>
                </x-field>
            </div>
            @unless ($readonlyWindow)
                <div class="flex justify-end border-t border-rule px-4 py-3 sm:px-6">
                    <button class="btn btn-secondary" data-once>Simpan feedback</button>
                </div>
            @endunless
        </form>
    @endif
</x-layouts.app>
