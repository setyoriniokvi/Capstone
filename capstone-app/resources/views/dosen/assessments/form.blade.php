{{-- F47 — Formulir penilaian dosen, dikelompokkan per CLO (baris judul CLO + kode CLO iGadis). Tampilan mengikuti
     pengaturan periode (admin, halaman Indikator → Tampilan form penilaian dosen):
     Skenario 2 (split, bawaan) — kolom: CLO · Indikator · Poin Penilaian · Rubrikasi · Bobot · Dokumen · Lisan.
       Dokumen dan Lisan masing-masing punya centang sendiri di kiri dropdown (centang semua di judul kolom, centang satu
       CLO di baris judul CLO). Nilai dokumen bisa diisi per baris, nilai lisan dicentang beberapa / semua lalu dipilih
       sekali. Dropdown baris tercentang paling atas yang aktif; baris tercentang lainnya mengikuti nilainya.
     Skenario 1 (sequential) — kolom: centang · CLO · Indikator · Poin Penilaian · Rubrikasi · Bobot · Interval (+ Lisan);
       satu centang mengisi interval dan lisan sekaligus. CLO berikutnya terkunci sampai semua indikator CLO sebelumnya terisi.
     (Alpine "scoreSheet"). Pilihan interval "5 (95)" = tingkat (nilai level). Rubrikasi berupa baris [1]…[N]: kalimat
     utama + "…" dan tombol ▶ untuk teks lengkap (Alpine "clampText"). Interval "–" lalu simpan = skor dikosongkan.
     Jadwal penilaian hanya pengingat: setelah tenggat tetap dapat dinilai (sebelum dibuka terkunci).
     Simpan otomatis: setiap perubahan interval / pemahaman lisan langsung disimpan sebagai draft (belum dinilai: tidak terlihat
     mahasiswa, tetap kena reminder) tanpa tombol, juga saat dosen langsung kembali / menutup halaman; dibuka lagi = lanjut dari
     draft itu. Submit Final aktif bila semua indikator terisi. Tabel pas selebar kartu tanpa gulir samping (layar sempit: tiap baris menjadi kartu, .score-sheet di app.css).
     Di bawah: feedback untuk tim (opsional).
     $adminView (admin, Monitoring progres → Hasil Penilaian → Detail): tabel yang sama, hanya dilihat — semua isian
     terkunci, tanpa tombol simpan; dua penilai = pilihan Pembimbing / Penguji. --}}
@php
    $adminView ??= false;
    $readonlyWindow = $window && ! $window->isOpen();
    $formMode = $team->period?->formMode() ?? 'split';
    $split = $formMode === 'split';
    // Isian terkunci: di luar jendela penilaian, atau dibuka admin (lihat saja)
    $locked = $readonlyWindow || $adminView;
    $isFinal = $assessment?->status === 'final';
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $total = $indicators->count();
    $scored = $indicators->filter(fn ($i) => $scores->has($i->id))->count();
    $hasOral = $indicators->contains(fn ($i) => $i->usesOralFactor($componentKey));
    // Kelompok per CLO (urut kode CLO, lalu kode indikator)
    $groups = $indicators->groupBy('clo_id')->sortBy(fn ($rows) => $rows->first()->clo?->code, SORT_NATURAL);
    $oralText = fn ($lvl) => $lvl->level.' - '.number_format($lvl->factor, 2, ',', '');
    // Isi dropdown rata tengah (teks terpilih & pilihannya)
    $selectCenter = 'input tabular text-center [text-align-last:center] [&>option]:text-center';
    $cloCode = fn ($code) => \Illuminate\Support\Str::startsWith(mb_strtoupper($code), 'CLO') ? $code : 'CLO '.$code;
    // Kalimat utama poin rubrik (sampai titik / titik koma pertama, maks. ±90 karakter) + apakah teksnya dipotong.
    $lead = function (?string $text): array {
        $full = trim(preg_replace('/\s+/u', ' ', (string) $text));
        $short = preg_match('/^(.{15,}?[.;:])\s/u', $full, $m) ? $m[1] : $full;
        if (mb_strlen($short) > 90) {
            $short = rtrim(preg_replace('/\s+\S*$/u', '', mb_substr($short, 0, 90)), ',;: ');
        }

        // Tanda baca penutup kalimat utama diganti "…" (bukan ";…").
        return [rtrim($short, '.;:, '), $short !== $full];
    };
@endphp
{{-- Halaman fokus tanpa sidebar (dosen & admin lihat saja); kembali lewat tombol Kembali --}}
<x-layouts.app :title="'Penilaian '.$team->name" :sidebar="false">
    <x-page-head :title="$label" :lede="$adminView ? $team->name.' · dinilai '.$role.($assessorName ? ' ('.$assessorName.')' : '') : $team->name.' · Anda sebagai '.$role">
        <x-slot:actions>
            {{-- Kedua status dirender; simpan otomatis menukar yang tampil (penilaian final yang diubah kembali menjadi draft) --}}
            <span data-status-badge="final" @unless ($isFinal) hidden @endunless><x-badge status="final" label="Sudah dinilai" class="text-sm" /></span>
            <span data-status-badge="belum" @if ($isFinal) hidden @endif><x-badge status="belum" label="Belum dinilai" class="text-sm" /></span>
            @if ($adminView)
                <a href="{{ route('admin.monitoring.progress', ['tim' => $team->id]) }}" class="btn btn-ghost">Kembali</a>
            @else
                <a href="{{ route('dosen.assessments.index', ['komponen' => $componentKey]) }}" class="btn btn-ghost">Kembali</a>
            @endif
        </x-slot:actions>
    </x-page-head>

    @if ($adminView)
        <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border border-rule bg-sunken px-4 py-3 text-sm text-muted" role="status">
            <x-heroicon-o-eye class="h-5 w-5 shrink-0" />
            <p class="min-w-0 flex-1">Mode lihat saja. Nilai diisi dosen {{ $role }} dari akunnya{{ $assessment ? '' : '; belum ada penilaian tersimpan' }}{{ $assessment && ! $isFinal ? ' (masih draft, belum terlihat mahasiswa)' : '' }}.</p>
            @if (count($roles ?? []) > 1)
                {{-- Bentuk penilaian dua penilai: pilih penilaian pembimbing / penguji --}}
                <nav class="inline-flex rounded-lg border border-rule bg-surface p-0.5" aria-label="Penilai">
                    @foreach ($roles as $r)
                        <a href="{{ route('admin.monitoring.assessment', [$team, $componentKey, 'penilai' => $r]) }}" @if ($r === $role) aria-current="page" @endif
                           class="rounded-md px-3 py-1.5 text-sm font-medium {{ $r === $role ? 'bg-brand-tint text-brand-deep' : 'text-muted hover:text-ink' }}">{{ ucfirst($r) }}</a>
                    @endforeach
                </nav>
            @endif
        </div>
    @endif

    @if ($viaQr)
        <p class="mb-4 rounded-lg border border-ok-line bg-ok-tint px-4 py-3 text-sm text-ok-ink">Dibuka dari QR Code tim. Identitas tim telah diverifikasi sistem.</p>
    @endif
    @if ($readonlyWindow && ! $adminView)
        {{-- Isian dikunci sebelum jendela penilaian dibuka: jelaskan kenapa tidak dapat diklik --}}
        <div class="mb-4 flex gap-3 rounded-lg border border-warn-line bg-warn-tint px-4 py-3 text-sm text-warn-ink" role="status">
            <x-heroicon-o-lock-closed class="mt-0.5 h-5 w-5 shrink-0" />
            <p><span class="font-semibold">Penilaian belum dibuka.</span>
                Penilaian dapat diisi mulai {{ $window->opens_at->translatedFormat('j M Y H:i') }} (tenggat {{ $window->due_at->translatedFormat('j M Y H:i') }}). Hubungi admin bila jadwal perlu diubah.</p>
        </div>
    @elseif ($window?->isOverdue() && ! $adminView)
        {{-- Setelah tenggat tetap dapat dinilai: jadwal hanya pengingat --}}
        <div class="mb-4 flex gap-3 rounded-lg border border-warn-line bg-warn-tint px-4 py-3 text-sm text-warn-ink" role="status">
            <x-heroicon-o-clock class="mt-0.5 h-5 w-5 shrink-0" />
            <p><span class="font-semibold">Tenggat penilaian sudah lewat</span> ({{ $window->due_at->translatedFormat('j M Y H:i') }}). Penilaian tetap dapat diisi dan di-Submit Final.</p>
        </div>
    @endif

    <section class="panel mb-6">
        {{-- Topik final (+ jenis: mandiri / dosen) · objek topik · anggota --}}
        @php
            $topic = $team->finalTopic;
            // Rumus tidak ditampilkan ke dosen, tetapi tetap dipakai perhitungan nilai di tabel bawah
            $formula = \App\Models\AssessmentComponent::formulaOf($componentKey);
        @endphp
        <dl class="kv panel-body">
            <dt>Topik</dt>
            <dd>
                {{ $topic?->title ?? '—' }}
                @if ($topic)<span class="ml-1 inline-block whitespace-nowrap rounded border border-rule bg-sunken px-1.5 py-0.5 align-middle text-caption font-medium text-muted">{{ $topic->source === 'mandiri' ? 'Topik Mandiri' : 'Topik Dosen' }}</span>@endif
            </dd>
            <dt>Objek topik</dt><dd>{{ $topic?->object ?: '—' }}</dd>
            <dt>Anggota</dt><dd>{{ $team->members->where('invite_status', 'diterima')->pluck('user.name')->implode(', ') }}</dd>
        </dl>
    </section>

    @foreach (['gate', 'window', 'scores'] as $key)
        @error($key)<p class="mb-4 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror
    @endforeach
    @php $rowErrors = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'scores.'))->flatten(); @endphp
    @if ($rowErrors->isNotEmpty())
        <ul class="mb-4 list-disc space-y-0.5 rounded-lg border border-bad-line bg-bad-tint py-3 pl-9 pr-4 text-sm text-bad-ink">@foreach ($rowErrors as $message)<li>{{ $message }}</li>@endforeach</ul>
    @endif

    @if ($indicators->isEmpty())
        <div class="panel"><x-empty title="Belum ada indikator dengan bobot pada komponen ini" icon="list-bullet">Hubungi admin untuk melengkapi master indikator penilaian.</x-empty></div>
    @else
        <section x-data="scoreSheet" data-mode="{{ $formMode }}" data-locked="{{ $locked ? 'true' : 'false' }}" data-autosave="{{ $locked ? 'false' : 'true' }}"
                 data-saved-at="{{ $assessment && ! $isFinal ? $assessment->updated_at?->format('H:i') : '' }}" @change="change" class="panel mb-6 overflow-hidden" aria-labelledby="penilaian-judul">
            <h2 id="penilaian-judul" class="sr-only">Penilaian per indikator</h2>
                {{-- Toolbar: progres (kiri) · Simpan Final (kanan, selalu di samping progres). Status simpan otomatis di baris
                     keterangan di bawah progres, sehingga panjang teksnya tidak menggeser tombol. --}}
                <form id="nilai-form" method="POST" action="{{ $adminView ? '' : route('dosen.assessments.scores', [$team, $componentKey]) }}" class="flex items-center gap-4 px-5 py-4 sm:px-6">
                    @csrf @method('PUT')
                    @if ($viaQr)<input type="hidden" name="via" value="qr">@endif
                    <div class="min-w-0 flex-1">
                        <p class="text-sm text-muted"><span class="tabular font-semibold text-ink" data-scored-count>{{ $scored }}</span> dari <span class="tabular">{{ $total }}</span> indikator sudah dinilai</p>
                        <x-progress-bar class="mt-2" :value="$scored" :max="$total" label="Indikator sudah dinilai" />
                        <div class="mt-2 flex flex-wrap items-center justify-between gap-x-4 gap-y-1">
                            <p class="flex flex-wrap items-center gap-x-4 gap-y-1 text-caption text-muted">
                                <span class="inline-flex items-center gap-1.5"><span class="h-3.5 w-5 rounded border border-ok-line bg-ok-tint" aria-hidden="true"></span>Sudah dinilai</span>
                                <span class="inline-flex items-center gap-1.5"><span class="h-3.5 w-5 rounded border border-rule bg-surface" aria-hidden="true"></span>Belum dinilai</span>
                            </p>
                            @unless ($adminView)
                                {{-- Pengganti tombol Simpan Draft: status simpan otomatis --}}
                                <p class="flex items-center gap-1.5 text-caption" :class="saveTone" role="status" aria-live="polite">
                                    <x-heroicon-o-cloud-arrow-up class="h-4 w-4 shrink-0" aria-hidden="true" />
                                    <span x-text="saveLabel">{{ $locked ? 'Penilaian dikunci' : 'Perubahan tersimpan otomatis sebagai draft' }}</span>
                                </p>
                            @endunless
                        </div>
                    </div>
                    @unless ($adminView)
                        <button name="mode" value="final" class="btn btn-primary min-h-[44px] shrink-0 disabled:cursor-not-allowed disabled:opacity-50" data-once
                                :disabled="finalLocked" :title="finalTitle" disabled>
                            <x-heroicon-o-check-badge class="h-5 w-5" /> Submit Final
                        </button>
                    @endunless
                </form>

                @if ($hasOral && $oralLevels->isNotEmpty())
                    {{-- Selalu terbuka agar terbaca dosen saat menilai --}}
                    <section class="border-t border-rule px-5 py-3 text-sm sm:px-6" aria-labelledby="lisan-judul">
                        <h3 id="lisan-judul" class="font-medium text-ink">Indikator Penilaian Lisan</h3>
                        <dl class="mt-1 space-y-1">@foreach ($oralLevels as $lvl)<div class="grid grid-cols-[2rem_minmax(0,1fr)] gap-2"><dt class="tabular">{{ $lvl->level }}</dt><dd class="text-muted">{{ $lvl->label }} ({{ number_format($lvl->factor, 2, ',', '') }}) - {{ $lvl->behavior }}</dd></div>@endforeach</dl>
                    </section>
                @endif

                @php
                    // Pilihan interval: "5 (95)" bila rumusnya memakai nilai level Rubrik Dokumen
                    $levelOption = fn (int $level) => $formula['divisor'] === 'level' ? $level.' ('.$num(\App\Models\AssessmentComponent::levelValue($level)).')' : (string) $level;
                    $checkCell = fn (string $kind, string $attr, string $label) => '<input type="checkbox" class="checkbox" '.$attr.' data-kind="'.$kind.'" aria-label="'.e($label).'"'.($locked ? ' disabled' : '').'>';
                @endphp
                <table class="score-sheet data-table border-t border-rule [&_td]:align-top [&_td]:text-sm" data-mode="{{ $formMode }}">
                    <colgroup>
                        @if ($split)
                            <col class="w-[5.5rem]"><col class="w-[17%]"><col class="w-[18%]"><col><col class="w-16"><col class="w-[8.5rem]"><col class="w-[9.5rem]">
                        @else
                            <col class="w-11"><col class="w-[5.5rem]"><col class="w-[17%]"><col class="w-[19%]"><col><col class="w-16"><col class="w-[8.5rem]">
                        @endif
                    </colgroup>
                    <thead class="bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep">
                        <tr>
                            @if ($split)
                                <th>CLO</th><th>Indikator</th><th>Poin Penilaian</th><th>Rubrikasi</th><th class="!text-center">Bobot</th>
                                {{-- Skenario 2: centang kolom Dokumen / Lisan masing-masing (di kiri judulnya) --}}
                                <th><label class="flex items-center gap-2">{!! $checkCell('doc', 'data-select-all', 'Centang semua nilai dokumen') !!}<span>Dokumen</span></label></th>
                                <th>@if ($hasOral)<label class="flex items-center gap-2">{!! $checkCell('oral', 'data-select-all', 'Centang semua nilai lisan') !!}<span>Lisan</span></label>@else Lisan @endif</th>
                            @else
                                <th class="!px-0"><div class="flex justify-center max-md:justify-start">{!! $checkCell('both', 'data-select-all', 'Centang semua indikator') !!}</div></th>
                                <th>CLO</th><th>Indikator</th><th>Poin Penilaian</th><th>Rubrikasi</th><th class="!text-center">Bobot</th><th class="!text-center">Interval</th>
                            @endif
                        </tr>
                    </thead>
                    @foreach ($groups as $cloId => $rows)
                        @php
                            $clo = $rows->first()->clo;
                            $cloLabel = preg_replace('/^CLO\s*/i', 'CLO ', (string) $clo?->code);
                            $groupOral = $rows->contains(fn ($i) => $i->usesOralFactor($componentKey));
                        @endphp
                        <tbody data-group="{{ $cloId }}">
                            {{-- Baris judul CLO (+ kode CLO iGadis): centang = pilih semua indikator CLO ini untuk dinilai sekaligus --}}
                            <tr class="bg-sunken">
                                @unless ($split)
                                    <td class="!px-0"><div class="flex justify-center max-md:justify-start">{!! $checkCell('both', 'data-select-group="'.$cloId.'"', 'Centang semua indikator '.$cloLabel) !!}</div></td>
                                @endunless
                                <td colspan="{{ $split ? 5 : 6 }}" data-label="CLO">
                                    <p class="font-semibold text-ink"><span class="code">{{ $cloLabel }}</span>@if ($clo?->igadis_code) <span class="code text-caption font-normal text-muted">({{ $clo->igadis_code }})</span>@endif <span class="font-normal text-muted">· {{ $rows->count() }} indikator · bobot {{ $num($rows->sum(fn ($i) => $i->weightFor($componentKey))) }}%</span></p>
                                    @if ($clo?->description)<p class="mt-0.5 text-muted">{{ $clo->description }}</p>@endif
                                    @unless ($split)
                                        <p class="mt-1 flex items-center gap-1.5 text-caption font-medium text-warn-ink" data-group-lock hidden><x-heroicon-o-lock-closed class="h-4 w-4 shrink-0" />Selesaikan penilaian CLO sebelumnya terlebih dahulu.</p>
                                    @endunless
                                </td>
                                @if ($split)
                                    <td data-label="Centang dokumen CLO ini"><label class="flex items-center gap-2 text-caption text-muted">{!! $checkCell('doc', 'data-select-group="'.$cloId.'"', 'Centang nilai dokumen semua indikator '.$cloLabel) !!}<span>Semua dokumen</span></label></td>
                                    <td data-label="Centang lisan CLO ini">
                                        @if ($groupOral)<label class="flex items-center gap-2 text-caption text-muted">{!! $checkCell('oral', 'data-select-group="'.$cloId.'"', 'Centang nilai lisan semua indikator '.$cloLabel) !!}<span>Semua lisan</span></label>@endif
                                    </td>
                                @endif
                            </tr>
                            @foreach ($rows as $indicator)
                                @php
                                    $saved = $scores->get($indicator->id);
                                    $max = $indicator->maxInterval();
                                    $oldScore = old("scores.{$indicator->id}.score", $saved?->score);
                                    $oldOral = old("scores.{$indicator->id}.oral_level", $saved?->oral_level);
                                    $usesOral = $indicator->usesOralFactor($componentKey);
                                    $code = $cloCode($indicator->code);
                                @endphp
                                <tr class="{{ $saved ? 'bg-ok-tint' : '' }}" data-clo="{{ $cloId }}" @if ($saved) data-scored @endif>
                                    @unless ($split)
                                        <td class="!px-0"><div class="flex justify-center max-md:justify-start"><input type="checkbox" class="checkbox" value="{{ $indicator->id }}" data-select-row data-kind="both" data-clo="{{ $cloId }}" aria-label="Centang indikator {{ $code }}" @disabled($locked)></div></td>
                                    @endunless
                                    <td data-label="CLO" class="code whitespace-nowrap font-medium text-ink">{{ $code }}</td>
                                    <td data-label="Indikator">
                                        <span class="block font-medium text-ink">{{ $indicator->name }}</span>
                                        <span class="mt-1 flex flex-wrap gap-1">
                                            <span class="text-caption text-muted">{{ $indicator->schemeLabel() }}@if ($indicator->evidence) · {{ $indicator->evidence }}@endif</span>
                                        </span>
                                    </td>
                                    <td data-label="Poin Penilaian" class="whitespace-pre-line break-words {{ $indicator->rubric?->assessment_points ? 'text-ink' : 'text-muted' }}">{{ $indicator->rubric?->assessment_points ?: '—' }}</td>
                                    <td data-label="Rubrikasi">
                                        <ol class="space-y-1.5">
                                            @foreach ($indicator->rubricPoints() as $level => $text)
                                                @php [$short, $cut] = $lead($text); @endphp
                                                <li x-data="clampText" class="grid grid-cols-[1.75rem_minmax(0,1fr)_auto] items-start gap-1 text-ink">
                                                    <span class="tabular font-semibold text-muted">[{{ $level }}]</span>
                                                    @if ($text === '')
                                                        <span class="text-muted">—</span><span></span>
                                                    @elseif ($cut)
                                                        <span class="break-words"><span x-show="closed">{{ $short }}…</span><span x-cloak x-show="open" class="whitespace-pre-line">{{ trim($text) }}</span></span>
                                                        <button type="button" class="grid h-6 w-6 place-items-center rounded text-muted hover:bg-sunken hover:text-ink" @click="toggle" :aria-expanded="open" :aria-label="label" aria-label="Tampilkan semua teks poin {{ $level }}">
                                                            {{-- Segitiga ▶ seperti penanda <details>; berputar ke bawah saat teks lengkap dibuka --}}
                                                            <svg viewBox="0 0 10 10" class="h-2.5 w-2.5 fill-current transition-transform" :class="iconClass" aria-hidden="true"><path d="M2.5 1 8.5 5l-6 4z" /></svg>
                                                        </button>
                                                    @else
                                                        <span class="whitespace-pre-line break-words">{{ trim($text) }}</span><span></span>
                                                    @endif
                                                </li>
                                            @endforeach
                                        </ol>
                                    </td>
                                    <td data-label="Bobot" class="text-center tabular font-medium text-ink">{{ $num($indicator->weightFor($componentKey)) }}%</td>
                                    @php
                                        $intervalSelect = '<select id="interval-'.$indicator->id.'" form="nilai-form" name="scores['.$indicator->id.'][score]" class="'.e($selectCenter).' min-w-0 flex-1" data-interval="'.$indicator->id.'" data-max="'.$max.'"'.($locked ? ' disabled' : '').'>'
                                            .'<option value="">–</option>'
                                            .collect(range($max, 0))->map(fn ($l) => '<option value="'.$l.'"'.((string) $oldScore === (string) $l ? ' selected' : '').'>'.e($levelOption($l)).'</option>')->implode('')
                                            .'</select>';
                                        $oralSelect = $usesOral
                                            ? '<select id="oral-'.$indicator->id.'" form="nilai-form" name="scores['.$indicator->id.'][oral_level]" class="'.e($selectCenter).' min-w-0 flex-1" data-oral="'.$indicator->id.'"'.($locked ? ' disabled' : '').'>'
                                                .'<option value="">–</option>'
                                                .$oralLevels->sortByDesc('level')->map(fn ($lvl) => '<option value="'.$lvl->level.'"'.((string) $oldOral === (string) $lvl->level ? ' selected' : '').' title="'.e($lvl->label).'">'.e($oralText($lvl)).'</option>')->implode('')
                                                .'</select>'
                                            : '';
                                    @endphp
                                    @if ($split)
                                        {{-- Skenario 2: Dokumen | Lisan berdampingan, centang masing-masing di kiri dropdown --}}
                                        <td data-label="Dokumen">
                                            <label class="sr-only" for="interval-{{ $indicator->id }}">Nilai dokumen {{ $indicator->code }} (0–{{ $max }})</label>
                                            <div class="flex items-center gap-2">
                                                <input type="checkbox" class="checkbox shrink-0" value="{{ $indicator->id }}" data-select-row data-kind="doc" data-clo="{{ $cloId }}" aria-label="Centang nilai dokumen {{ $code }}" @disabled($locked)>
                                                {!! $intervalSelect !!}
                                            </div>
                                            <p class="mt-1 pl-7 text-caption text-muted" data-mirror-note>Maks. {{ $max }}</p>
                                        </td>
                                        <td data-label="Lisan">
                                            @if ($usesOral)
                                                <label class="sr-only" for="oral-{{ $indicator->id }}">Nilai lisan {{ $indicator->code }}</label>
                                                <div class="flex items-center gap-2">
                                                    <input type="checkbox" class="checkbox shrink-0" value="{{ $indicator->id }}" data-select-row data-kind="oral" data-clo="{{ $cloId }}" aria-label="Centang nilai lisan {{ $code }}" @disabled($locked)>
                                                    {!! $oralSelect !!}
                                                </div>
                                            @else
                                                <p class="pt-2 text-caption text-muted">Tanpa lisan</p>
                                            @endif
                                        </td>
                                    @else
                                        <td data-label="Interval" class="text-center">
                                            <label class="sr-only" for="interval-{{ $indicator->id }}">Interval {{ $indicator->code }} (0–{{ $max }})</label>
                                            <div class="flex">{!! $intervalSelect !!}</div>
                                            <p class="mt-1 text-caption text-muted" data-mirror-note>Maks. {{ $max }}</p>
                                            @if ($usesOral)
                                                <label class="field-label mt-2 !text-caption" for="oral-{{ $indicator->id }}">Lisan</label>
                                                <div class="flex">{!! $oralSelect !!}</div>
                                            @endif
                                        </td>
                                    @endif
                                </tr>
                            @endforeach
                        </tbody>
                    @endforeach
                </table>

        </section>

        {{-- Feedback untuk tim (opsional) --}}
        <form method="POST" action="{{ $adminView ? '' : route('dosen.assessments.update', [$team, $componentKey]) }}" class="panel">
            @csrf @method('PUT')
            @if ($viaQr)<input type="hidden" name="via" value="qr">@endif
            <div class="panel-body">
                <x-field label="Feedback untuk tim" name="feedback">
                    <textarea id="feedback" name="feedback" rows="4" class="input" @disabled($locked)>{{ old('feedback', $assessment?->feedback) }}</textarea>
                </x-field>
            </div>
            @unless ($locked)
                <div class="flex justify-end border-t border-rule px-4 py-3 sm:px-6">
                    <button class="btn btn-secondary" data-once>Simpan feedback</button>
                </div>
            @endunless
        </form>
    @endif
</x-layouts.app>
