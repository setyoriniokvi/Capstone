{{-- F15 — Rubrik penilaian dokumen: satu tabel seperti lembar "Rubrikasi Dokumen" (Kode · CLO · Indikator · Bobot ·
     Poin Penilaian · deskripsi tingkat 5–0 · Contoh Pemenuhan Tingkat 5), kotak centang per baris (Alpine "rowSelection").
     Kanan atas: Tambah Rubrik (modal; pilih indikator yang belum berubrik; semua isian wajib kecuali poin penilaian),
     Edit Pilihan (modal berisi isian rubrik baris yang dicentang, disimpan sekaligus), Hapus Pilihan.
     Kode, CLO, Indikator, dan Bobot adalah data indikator: hanya ditampilkan, diubah di menu Indikator.
     ?tambah={indicator_id} (tautan "Isi" di halaman Indikator) membuka modal Tambah dengan indikator tersebut.
     Di layar ≥ sm kolom centang · Kode · CLO · Indikator · Bobot dibekukan di kiri; yang digeser mulai Poin Penilaian.
     Tabel memakai border-separate (jarak 0) agar garis antarkolom milik tiap sel dan ikut membeku — dengan
     border-collapse garisnya milik tabel sehingga isi di belakang tembus di sela kolom beku. --}}
@php
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $star = '<span class="text-bad-ink" aria-hidden="true"> *</span>';
    $levelTitle = fn ($level, $meta) => "{$level} - {$meta['label']} ({$meta['value']})";
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    // Kolom beku (layar ≥ sm): centang · Kode · CLO · Indikator · Bobot tetap di kiri; mulai Poin Penilaian digeser.
    // Posisi kiri = jumlah lebar kolom sebelumnya (sama dengan <colgroup>).
    $freeze = ['sm:sticky sm:left-0', 'sm:sticky sm:left-[3rem]', 'sm:sticky sm:left-[7.5rem]', 'sm:sticky sm:left-[12.5rem]', 'sm:sticky sm:left-[26.5rem] sm:border-r-2 sm:border-r-rule-strong'];
    $frozenTd = fn (int $i) => $freeze[$i].' sm:z-[1] bg-surface';
    $frozenTh = fn (int $i) => $freeze[$i].' sm:z-[1] bg-brand-tint';
    $adding = old('_form') === 'tambah';
    $editing = old('_form') === 'edit';
    $preselect = $adding ? old('indicator_id') : request('tambah');
    $editIds = $editing ? array_map('intval', array_keys(old('rows', []))) : [];
@endphp
<x-layouts.app title="Rubrik Dokumen">
<div x-data="rowSelection" data-row-selection>
    <x-page-head title="Rubrik penilaian dokumen" lede="Deskripsi tiap tingkat skor per indikator: 5 Sangat Baik (95), 4 Baik (80), 3 Cukup (70), 2 Kurang (50), 1 Sangat Kurang (40), 0 Tidak Mengumpulkan (0).">
        <x-slot:actions>
            {{-- Hapus Pilihan · Edit Pilihan · Tambah Rubrik selalu sebaris (deskripsi di kiri yang membungkus) --}}
            <div class="flex flex-nowrap items-center gap-2">
            {{-- Hapus Pilihan --}}
            <form method="POST" action="{{ route('admin.rubrics.destroy-many') }}" data-confirm="Hapus rubrik yang dipilih? Indikatornya tetap ada." data-confirm-label="Hapus">
                @csrf @method('DELETE')
                <template x-for="id in ids" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <button class="btn btn-secondary text-bad-ink disabled:cursor-not-allowed disabled:opacity-50" :disabled="noneSelected" disabled title="Centang rubrik terlebih dahulu">
                    <x-heroicon-o-trash class="h-5 w-5" /> Hapus Pilihan
                </button>
            </form>

            {{-- Edit Pilihan: isian baris yang dicentang, disimpan sekaligus --}}
            <div x-data="registrationForm" data-open="{{ $editing ? 'true' : 'false' }}">
                <button type="button" class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50" @click="open" :disabled="noneSelected" disabled title="Centang rubrik terlebih dahulu">
                    <x-heroicon-o-pencil-square class="h-5 w-5" /> Edit Pilihan
                </button>
                <x-modal width="sm:max-w-4xl">
                    <x-slot:heading>Edit rubrik terpilih (<span x-text="count"></span>)</x-slot:heading>
                    <form method="POST" action="{{ route('admin.rubrics.update-many') }}" class="space-y-6">
                        @csrf @method('PUT')
                        <input type="hidden" name="_form" value="edit">
                        @foreach ($rubrics as $ind)
                            @php $r = $ind->rubric; $old = fn ($f) => $editing ? old("rows.{$ind->id}.{$f}", $r->{$f}) : $r->{$f}; @endphp
                            <fieldset x-show="isSelected({{ $ind->id }})" x-cloak :disabled="! isSelected({{ $ind->id }})" class="space-y-3 rounded-lg border border-rule p-4">
                                <legend class="px-1 text-sm font-semibold text-ink"><span class="code">{{ $ind->code }}</span> · {{ $ind->clo->code }} · {{ $ind->name }}</legend>
                                <p class="text-caption text-muted">Bobot {{ $num($ind->weight) }}. Kode, CLO, nama indikator, dan bobot adalah data indikator — ubah di menu <a href="{{ route('admin.indicators.index') }}" class="link">Indikator</a>.</p>
                                <div>
                                    <label class="field-label" for="e-{{ $ind->id }}-points">Poin penilaian <span class="font-normal text-muted">(opsional)</span></label>
                                    <textarea id="e-{{ $ind->id }}-points" name="rows[{{ $ind->id }}][assessment_points]" rows="3" class="input">{{ $old('assessment_points') }}</textarea>
                                </div>
                                <div class="grid gap-3 sm:grid-cols-2">
                                    @foreach ($levels as $level => $meta)
                                        <div>
                                            <label class="field-label" for="e-{{ $ind->id }}-{{ $level }}">{{ $levelTitle($level, $meta) }}{!! $star !!}</label>
                                            <textarea id="e-{{ $ind->id }}-{{ $level }}" name="rows[{{ $ind->id }}][level_{{ $level }}]" rows="3" class="input" required>{{ $old("level_{$level}") }}</textarea>
                                            @error("rows.{$ind->id}.level_{$level}")<p class="field-error">{{ $message }}</p>@enderror
                                        </div>
                                    @endforeach
                                </div>
                                <div>
                                    <label class="field-label" for="e-{{ $ind->id }}-ex">Contoh pemenuhan tingkat 5{!! $star !!}</label>
                                    <textarea id="e-{{ $ind->id }}-ex" name="rows[{{ $ind->id }}][example_level_5]" rows="3" class="input" required>{{ $old('example_level_5') }}</textarea>
                                    @error("rows.{$ind->id}.example_level_5")<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                            </fieldset>
                        @endforeach
                        <div class="flex justify-end gap-3 border-t border-rule pt-4">
                            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                            <button class="btn btn-primary" data-once>Simpan perubahan</button>
                        </div>
                    </form>
                </x-modal>
            </div>

            {{-- Tambah rubrik untuk indikator yang belum berubrik --}}
            <div x-data="registrationForm" data-open="{{ $adding || request()->filled('tambah') ? 'true' : 'false' }}">
                <button type="button" class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50" @click="open" :aria-expanded="expanded" @disabled($available->isEmpty()) @if ($available->isEmpty()) title="Semua indikator sudah memiliki rubrik" @endif>
                    <x-heroicon-o-plus class="h-5 w-5" /> Tambah Rubrik
                </button>
                <x-modal width="sm:max-w-3xl">
                    <x-slot:heading>Tambah rubrik dokumen</x-slot:heading>
                    <form method="POST" action="{{ route('admin.rubrics.store') }}" class="space-y-4">
                        @csrf
                        <input type="hidden" name="_form" value="tambah">
                        <div>
                            <label class="field-label" for="t-indicator">Indikator (Kode · CLO · Indikator · Bobot){!! $star !!}</label>
                            <select id="t-indicator" name="indicator_id" class="input" required>
                                <option value="">Pilih indikator yang belum memiliki rubrik</option>
                                @foreach ($available as $ind)
                                    <option value="{{ $ind->id }}" @selected((string) $preselect === (string) $ind->id)>{{ $ind->code }} · {{ $ind->clo->code }} · {{ $ind->name }} · bobot {{ $num($ind->weight) }}</option>
                                @endforeach
                            </select>
                            @if ($adding)@error('indicator_id')<p class="field-error">{{ $message }}</p>@enderror @endif
                        </div>
                        <div>
                            <label class="field-label" for="t-points">Poin penilaian <span class="font-normal text-muted">(opsional)</span></label>
                            <textarea id="t-points" name="assessment_points" rows="3" class="input">{{ $adding ? old('assessment_points') : '' }}</textarea>
                        </div>
                        <div class="grid gap-3 sm:grid-cols-2">
                            @foreach ($levels as $level => $meta)
                                <div>
                                    <label class="field-label" for="t-{{ $level }}">{{ $levelTitle($level, $meta) }}{!! $star !!}</label>
                                    <textarea id="t-{{ $level }}" name="level_{{ $level }}" rows="3" class="input" required>{{ $adding ? old("level_{$level}") : ($level === 0 ? 'Tidak mengumpulkan, tidak mengerjakan, atau tidak ada bukti yang dapat dinilai.' : '') }}</textarea>
                                    @if ($adding)@error("level_{$level}")<p class="field-error">{{ $message }}</p>@enderror @endif
                                </div>
                            @endforeach
                        </div>
                        <div>
                            <label class="field-label" for="t-ex">Contoh pemenuhan tingkat 5{!! $star !!}</label>
                            <textarea id="t-ex" name="example_level_5" rows="3" class="input" required>{{ $adding ? old('example_level_5') : '' }}</textarea>
                            @if ($adding)@error('example_level_5')<p class="field-error">{{ $message }}</p>@enderror @endif
                        </div>
                        <div class="flex justify-end gap-3 border-t border-rule pt-4">
                            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                            <button class="btn btn-primary" data-once>Tambah rubrik</button>
                        </div>
                    </form>
                </x-modal>
            </div>
            </div>
        </x-slot:actions>
    </x-page-head>

    @error('ids')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror
    @error('rows')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror

    <section class="panel overflow-hidden" aria-labelledby="rubrik-judul">
        <div class="flex items-center justify-between gap-3 px-5 py-4 sm:px-6">
            <h2 id="rubrik-judul" class="text-lg font-semibold text-ink">Rubrikasi dokumen</h2>
            <span class="text-sm text-muted"><span class="tabular font-medium text-ink" x-text="count">0</span> dipilih · {{ $rubrics->count() }} rubrik @if ($available->isNotEmpty())· <span class="text-warn-ink">{{ $available->count() }} indikator belum berubrik</span>@endif</span>
        </div>
        @if ($rubrics->isEmpty())
            <div class="border-t border-rule"><x-empty title="Belum ada rubrik dokumen" icon="list-bullet">Tambahkan lewat tombol Tambah Rubrik.</x-empty></div>
        @else
            <x-scroll-pager class="border-t border-rule">
                <table class="data-table w-full min-w-[150rem] table-fixed border-separate border-spacing-0 [&_td]:align-top [&_td]:whitespace-pre-line [&_td]:text-sm [&_td+td]:border-l [&_td]:border-rule" @change="pick">
                    <colgroup>
                        <col class="w-[3rem]"><col class="w-[4.5rem]"><col class="w-[5rem]"><col class="w-[14rem]"><col class="w-[5rem]"><col class="w-[16rem]">
                        @foreach ($levels as $level => $meta)<col class="w-[16rem]">@endforeach
                        <col class="w-[18rem]">
                    </colgroup>
                    <thead class="{{ $greenHead }}"><tr>
                        <th class="{{ $frozenTh(0) }} !px-0 !text-center"><input type="checkbox" class="checkbox" data-select-all aria-label="Pilih semua rubrik"></th>
                        <th class="{{ $frozenTh(1) }}">Kode</th><th class="{{ $frozenTh(2) }}">CLO</th><th class="{{ $frozenTh(3) }}">Indikator</th><th class="{{ $frozenTh(4) }} text-center">Bobot</th><th>Poin Penilaian</th>
                        @foreach ($levels as $level => $meta)<th>{{ $levelTitle($level, $meta) }}</th>@endforeach
                        <th>Contoh Pemenuhan Tingkat 5</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($rubrics as $ind)
                            <tr>
                                <td class="{{ $frozenTd(0) }} !px-0 text-center"><input type="checkbox" class="checkbox" value="{{ $ind->id }}" data-select-row @checked(in_array($ind->id, $editIds, true)) aria-label="Pilih rubrik {{ $ind->code }}"></td>
                                <td class="{{ $frozenTd(1) }} code font-medium text-ink">{{ $ind->code }}</td>
                                <td class="{{ $frozenTd(2) }} code text-ink">{{ $ind->clo->code }}</td>
                                <td class="{{ $frozenTd(3) }} font-medium text-ink">{{ $ind->name }}</td>
                                <td class="{{ $frozenTd(4) }} text-center tabular text-ink">{{ $num($ind->weight) }}</td>
                                <td class="{{ $ind->rubric->assessment_points ? 'text-ink' : 'text-muted' }}">{{ $ind->rubric->assessment_points ?: '—' }}</td>
                                @foreach ($levels as $level => $meta)<td class="text-ink">{{ $ind->rubric->describe($level) }}</td>@endforeach
                                <td class="{{ $ind->rubric->example_level_5 ? 'text-ink' : 'text-muted' }}">{{ $ind->rubric->example_level_5 ?: '—' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-scroll-pager>
        @endif
    </section>
</div>
</x-layouts.app>
