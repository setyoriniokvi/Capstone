{{-- F14 — Kartu "Cara nilai dihitung" (di atas tabel Indikator):
     1. Ringkasan rumus yang berlaku per bentuk penilaian (hanya dibaca) + tombol "Ubah rumus" → modal pengaturan rumus
        (per periode): nilai dokumen (Nilai level ÷ 100 — bawaan / Interval ÷ interval maksimum / Interval ÷ angka tetap; isian
        angka tetap aktif hanya untuk pilihan terakhir) · kali Bobot · kali Faktor Lisan · pengali tambahan → Simpan rumus.
     1b. Tampilan form penilaian dosen periode ini: Skenario 2 (bawaan, Dokumen & Lisan berdampingan) / Skenario 1.
        Peer review (diisi mahasiswa) memakai rumusnya sendiri: (rata-rata nilai ÷ 100) × Bobot.
     Butuh $componentModels (opsional $formPeriod). --}}
@php
    $formPeriod ??= null;
    $formulaComponents = $componentModels->reject->isPeerReview();
    $formulaError = collect($errors->getMessages())->filter(fn ($m, $k) => str_starts_with($k, 'formula'))->flatten();
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
@endphp
<section class="panel mb-6 overflow-hidden" aria-labelledby="cara-nilai-judul">
    {{-- Modal "Ubah rumus" memakai state registrationForm (terbuka lagi bila simpan gagal validasi) --}}
    <div x-data="registrationForm" data-open="{{ $formulaError->isNotEmpty() ? 'true' : 'false' }}">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
            <h2 id="cara-nilai-judul" class="text-lg font-semibold text-ink">Cara nilai dihitung</h2>
            <button type="button" class="btn btn-secondary btn-sm disabled:cursor-not-allowed disabled:opacity-50" @click="open" :aria-expanded="expanded" @disabled($formulaComponents->isEmpty())>
                <x-heroicon-o-pencil-square class="h-4 w-4" /> Ubah rumus
            </button>
        </div>

        {{-- Ringkasan: rumus yang berlaku per bentuk penilaian --}}
        <table class="data-table w-full min-w-0 table-fixed border-t border-rule [&_td]:text-sm [&_td]:break-words [&_tbody_tr:hover_td]:bg-transparent">
            <colgroup><col class="w-[35%]"><col></colgroup>
            <thead class="bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep">
                <tr><th>Bentuk penilaian</th><th>Rumus nilai indikator</th></tr>
            </thead>
            <tbody>
                @forelse ($formulaComponents as $component)
                    <tr>
                        <td><p class="font-medium text-ink">{{ $component->label }}</p><p class="text-caption text-muted">Dinilai {{ strtolower($component->rolesLabel()) }}</p></td>
                        <td class="text-ink">{{ \App\Models\AssessmentComponent::formulaText(\App\Models\AssessmentComponent::normalizeFormula($component->formula)) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="2" class="text-muted">Belum ada bentuk penilaian yang dinilai dosen di periode ini.</td></tr>
                @endforelse
            </tbody>
        </table>

        <x-modal width="sm:max-w-5xl">
            <x-slot:heading>Ubah rumus nilai indikator</x-slot:heading>
            <form method="POST" action="{{ route('admin.indicators.formulas') }}" class="space-y-4">
                @csrf @method('PUT')
                @if ($formulaError->isNotEmpty())
                    <ul class="list-disc rounded-lg border border-bad-line bg-bad-tint py-3 pl-9 pr-4 text-sm text-bad-ink">@foreach ($formulaError->unique() as $message)<li>{{ $message }}</li>@endforeach</ul>
                @endif
                <x-scroll-pager class="-mx-5 sm:-mx-6">
                    <table class="data-table w-full min-w-[56rem] [&_td]:align-middle [&_td]:text-sm [&_tbody_tr:hover_td]:bg-transparent">
                        <thead class="bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep">
                            <tr><th>Bentuk penilaian</th><th>Nilai dokumen</th><th class="text-center">× Bobot</th><th class="text-center">× Faktor Lisan</th><th>Pengali tambahan</th><th>Rumus yang berlaku</th></tr>
                        </thead>
                <tbody>
                    @forelse ($formulaComponents as $key => $component)
                        @php
                            $f = \App\Models\AssessmentComponent::normalizeFormula($component->formula);
                            $old = fn (string $field, $default) => old("formula.{$key}.{$field}", $default);
                            $divisor = $old('divisor', $f['divisor']);
                        @endphp
                        <tr x-data="formulaDivisor">
                            <td><p class="font-medium text-ink">{{ $component->label }}</p><p class="text-caption text-muted">Dinilai {{ strtolower($component->rolesLabel()) }}</p></td>
                            <td>
                                <div class="flex items-center gap-2">
                                    <label class="sr-only" for="f-{{ $key }}-divisor">Pembagi interval {{ $component->label }}</label>
                                    <select id="f-{{ $key }}-divisor" name="formula[{{ $key }}][divisor]" class="input min-h-[40px] w-auto py-1.5 text-sm" @change="pick">
                                        <option value="level" @selected($divisor === 'level')>Nilai level ÷ 100</option>
                                        <option value="max" @selected($divisor === 'max')>Interval ÷ interval maksimum</option>
                                        <option value="fixed" @selected($divisor === 'fixed')>Interval ÷ angka tetap</option>
                                    </select>
                                    <label class="sr-only" for="f-{{ $key }}-fixed">Angka pembagi tetap {{ $component->label }}</label>
                                    <input id="f-{{ $key }}-fixed" name="formula[{{ $key }}][fixed]" type="number" min="1" max="100" step="1" class="input tabular min-h-[40px] w-20 py-1.5 text-sm disabled:cursor-not-allowed disabled:opacity-50" value="{{ $old('fixed', $f['fixed']) }}" title="Angka pembagi; aktif bila memilih Interval ÷ angka tetap" data-fixed @disabled($divisor !== 'fixed')>
                                </div>
                            </td>
                            <td class="text-center"><input type="hidden" name="formula[{{ $key }}][weight]" value="0"><input type="checkbox" class="checkbox" name="formula[{{ $key }}][weight]" value="1" @checked($old('weight', $f['weight'])) aria-label="Kali bobot {{ $component->label }}"></td>
                            <td class="text-center"><input type="hidden" name="formula[{{ $key }}][oral]" value="0"><input type="checkbox" class="checkbox" name="formula[{{ $key }}][oral]" value="1" @checked($old('oral', $f['oral'])) aria-label="Kali faktor lisan {{ $component->label }}"></td>
                            <td>
                                <label class="sr-only" for="f-{{ $key }}-multiplier">Pengali tambahan {{ $component->label }}</label>
                                <input id="f-{{ $key }}-multiplier" name="formula[{{ $key }}][multiplier]" type="number" min="0.01" max="100" step="0.01" class="input tabular min-h-[40px] w-24 py-1.5 text-sm" value="{{ $old('multiplier', $f['multiplier']) }}">
                            </td>
                            <td class="text-ink">{{ \App\Models\AssessmentComponent::formulaText($f) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="text-muted">Belum ada bentuk penilaian yang dinilai dosen di periode ini.</td></tr>
                    @endforelse
                        </tbody>
                    </table>
                </x-scroll-pager>
                <p class="text-caption text-muted">Nilai level Rubrik Dokumen: 5 = 95, 4 = 80, 3 = 70, 2 = 50, 1 = 40, 0 = 0. Interval maksimum = banyaknya poin rubrik indikator. Faktor Lisan dari Rubrik Lisan untuk skema lisan (per tim), faktor bawaan untuk skema lain. Peer review: (rata-rata nilai ÷ 100) × Bobot.</p>
                <div class="flex justify-end gap-3 border-t border-rule pt-4">
                    <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                    <button class="btn btn-primary" data-once>Simpan rumus</button>
                </div>
            </form>
        </x-modal>
    </div>
    {{-- Tampilan form penilaian dosen (skenario 1 / 2) untuk periode ini --}}
    @if ($formPeriod)
        <form method="POST" action="{{ route('admin.indicators.form-mode') }}" class="border-t border-rule px-5 py-4 sm:px-6">
            @csrf @method('PUT')
            <label class="field-label" for="form-mode">Tampilan form penilaian dosen</label>
            {{-- Dropdown + tombol sebaris, tombol sejajar tengah dropdown --}}
            <div class="flex flex-wrap items-center gap-3">
                <select id="form-mode" name="assessment_form_mode" class="input min-w-[16rem] flex-1">
                    @foreach (\App\Models\Period::FORM_MODES as $mode => $modeLabel)<option value="{{ $mode }}" @selected($formPeriod->formMode() === $mode)>{{ $modeLabel }}</option>@endforeach
                </select>
                <button class="btn btn-secondary btn-sm" data-once>Simpan tampilan</button>
            </div>
        </form>
    @endif
</section>
