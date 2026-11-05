{{-- F14 — Indikator penilaian: satu tabel seperti lembar "Indikator" (CLO · ID · Indikator · Bukti / Bab · % · Skema ·
     bobot per bentuk penilaian), baris paling bawah = total bobot per bentuk penilaian (merah bila ≠ bobot CLO).
     Kotak centang tiap baris memilih indikator; kotak centang di judul kolom bentuk penilaian memilih bentuk penilaian itu
     (peer review mutlak ada: tanpa kotak centang). Di atas kartu Indikator: Hapus Pilihan · Edit Pilihan · Tambah Indikator (modal).
     Edit Pilihan berisi isian indikator & bentuk penilaian terpilih, disimpan sekaligus (Alpine "rowSelection").
     Di atasnya kartu informasi "Cara nilai dihitung" (admin/master/_score-rules, hanya dibaca).
     Di layar ≥ sm kolom centang · CLO · ID · Indikator dibekukan di kiri; tabel border-separate agar garisnya ikut membeku. --}}
@php
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $star = '<span class="text-bad-ink" aria-hidden="true"> *</span>';
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $schemes = config('capstone.schemes');
    $adding = old('_form') === 'tambah';
    $editing = old('_form') === 'edit';
    $editIds = $editing ? array_map('intval', array_keys(old('rows', []))) : [];
    $editCols = $editing ? array_keys(old('components', [])) : [];
    $isPeer = fn ($key) => in_array('mahasiswa', $components[$key]['roles'] ?? [], true);
    // Kolom beku (layar ≥ sm): posisi kiri = jumlah lebar kolom sebelumnya (sama dengan <colgroup>).
    $freeze = ['sm:sticky sm:left-0', 'sm:sticky sm:left-[3rem]', 'sm:sticky sm:left-[7.5rem]', 'sm:sticky sm:left-[12rem] sm:border-r-2 sm:border-r-rule-strong'];
    $indicatorFields = function (string $prefix, string $uid, array $values) use ($clos, $schemes, $components, $star) {
        return compact('prefix', 'uid', 'values', 'clos', 'schemes', 'components', 'star');
    };
@endphp
<x-layouts.app title="Indikator Penilaian">
<div x-data="rowSelection" data-row-selection>
    <x-page-head title="Indikator penilaian" lede="Nilai indikator = (Skor ÷ 5) × Bobot × Faktor Pemahaman. Bobot (%) = jumlah bobot pada setiap bentuk penilaian." />

    @error('selection')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror

    @include('admin.master._score-rules')

    {{-- Tombol tabel indikator: tepat di atas kartu Indikator (di bawah kartu Cara nilai dihitung) --}}
    <div class="mb-3 flex justify-end">
            <div class="flex flex-nowrap items-center gap-2">
            {{-- Hapus Pilihan: indikator & bentuk penilaian yang dicentang --}}
            <form method="POST" action="{{ route('admin.indicators.destroy-many') }}" data-confirm="Hapus indikator / bentuk penilaian yang dipilih? Rubrik indikator dan bobot bentuk penilaian ikut terhapus." data-confirm-label="Hapus">
                @csrf @method('DELETE')
                <template x-for="id in ids" :key="'i' + id"><input type="hidden" name="ids[]" :value="id"></template>
                <template x-for="key in cols" :key="'c' + key"><input type="hidden" name="components[]" :value="key"></template>
                <button class="btn btn-secondary text-bad-ink disabled:cursor-not-allowed disabled:opacity-50" :disabled="noneSelected" disabled title="Centang indikator atau bentuk penilaian terlebih dahulu">
                    <x-heroicon-o-trash class="h-5 w-5" /> Hapus Pilihan
                </button>
            </form>

            {{-- Edit Pilihan --}}
            <div x-data="registrationForm" data-open="{{ $editing ? 'true' : 'false' }}">
                <button type="button" class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50" @click="open" :disabled="noneSelected" disabled title="Centang indikator atau bentuk penilaian terlebih dahulu">
                    <x-heroicon-o-pencil-square class="h-5 w-5" /> Edit Pilihan
                </button>
                <x-modal width="sm:max-w-4xl">
                    <x-slot:heading>Edit pilihan (<span x-text="count"></span>)</x-slot:heading>
                    <form method="POST" action="{{ route('admin.indicators.update-many') }}" class="space-y-5">
                        @csrf @method('PUT')
                        <input type="hidden" name="_form" value="edit">
                        @foreach ($indicators as $ind)
                            @php
                                $values = $editing && isset(old('rows')[$ind->id]) ? old("rows.{$ind->id}") : [
                                    'clo_id' => $ind->clo_id, 'code' => $ind->code, 'name' => $ind->name, 'evidence' => $ind->evidence, 'scheme' => $ind->scheme,
                                    'weights' => collect($components)->mapWithKeys(fn ($c, $k) => [$k => $ind->weightFor($k) ? $ind->weightFor($k) + 0 : ''])->all(),
                                ];
                            @endphp
                            <fieldset x-show="isSelected({{ $ind->id }})" x-cloak :disabled="! isSelected({{ $ind->id }})" class="rounded-lg border border-rule p-4">
                                <legend class="px-1 text-sm font-semibold text-ink">Indikator <span class="code">{{ $ind->code }}</span> · {{ $ind->name }}</legend>
                                @include('admin.master._indicator-fields', $indicatorFields("rows[{$ind->id}]", "e{$ind->id}", $values) + ['errorPrefix' => "rows.{$ind->id}."])
                            </fieldset>
                        @endforeach
                        @foreach ($componentModels as $key => $component)
                            @continue($isPeer($key))
                            @php
                                $label = $editing && isset(old('components')[$key]) ? old("components.{$key}.label") : $component->label;
                                $compRoles = $editing && isset(old('components')[$key]) ? old("components.{$key}.roles", []) : $component->roles;
                            @endphp
                            <fieldset x-show="isColSelected('{{ $key }}')" x-cloak :disabled="! isColSelected('{{ $key }}')" class="grid gap-4 rounded-lg border border-rule p-4 sm:grid-cols-[minmax(0,1fr)_auto]">
                                <legend class="px-1 text-sm font-semibold text-ink">Bentuk penilaian · {{ $component->label }}</legend>
                                <div>
                                    <label class="field-label" for="c-{{ $key }}-label">Nama bentuk penilaian{!! $star !!}</label>
                                    <input id="c-{{ $key }}-label" name="components[{{ $key }}][label]" class="input" maxlength="100" value="{{ $label }}" required>
                                    @error("components.{$key}.label")<p class="field-error">{{ $message }}</p>@enderror
                                </div>
                                <div>
                                    <p class="field-label">Penilai{!! $star !!}</p>
                                    <div class="flex min-h-[44px] flex-wrap items-center gap-x-5 gap-y-2">
                                        @foreach (\App\Models\AssessmentComponent::LECTURER_ROLES as $role => $roleLabel)
                                            <label class="flex cursor-pointer items-center gap-2 text-sm text-ink">
                                                <input type="checkbox" class="checkbox" name="components[{{ $key }}][roles][]" value="{{ $role }}" @checked(in_array($role, $compRoles, true))> {{ $roleLabel }}
                                            </label>
                                        @endforeach
                                    </div>
                                    @error("components.{$key}.roles")<p class="field-error">{{ $message }}</p>@enderror
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

            {{-- Tambah Indikator --}}
            <div x-data="registrationForm" data-open="{{ $adding ? 'true' : 'false' }}">
                <button type="button" class="btn btn-primary" @click="open" :aria-expanded="expanded" @disabled($clos->isEmpty()) @if ($clos->isEmpty()) title="Isi resume CLO terlebih dahulu" @endif>
                    <x-heroicon-o-plus class="h-5 w-5" /> Tambah Indikator
                </button>
                <x-modal width="sm:max-w-3xl">
                    <x-slot:heading>Tambah indikator</x-slot:heading>
                    <form method="POST" action="{{ route('admin.indicators.store') }}" class="space-y-5">
                        @csrf
                        <input type="hidden" name="_form" value="tambah">
                        @include('admin.master._indicator-fields', $indicatorFields('', 't', $adding ? old() : ['scheme' => 'dokumen_lisan']) + ['errorPrefix' => ''])
                        <div class="flex justify-end gap-3 border-t border-rule pt-4">
                            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                            <button class="btn btn-primary" data-once>Tambah indikator</button>
                        </div>
                    </form>
                </x-modal>
            </div>
            </div>
    </div>

    <section class="panel overflow-hidden" aria-labelledby="indikator-judul">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
            <h2 id="indikator-judul" class="text-lg font-semibold text-ink">Indikator</h2>
            <p class="text-sm text-muted">
                <span class="tabular font-medium text-ink" x-text="count">0</span> dipilih · {{ $indicators->count() }} indikator ·
                Total bobot CLO <span class="tabular font-medium {{ abs($summary['clo_total'] - 100) < 0.01 ? 'text-ok-ink' : 'text-bad-ink' }}">{{ $num($summary['clo_total']) }}</span> / 100
            </p>
        </div>
        @if ($indicators->isEmpty())
            <div class="border-t border-rule"><x-empty title="Belum ada indikator" icon="list-bullet">Tambahkan lewat tombol Tambah Indikator.</x-empty></div>
        @else
            <x-scroll-pager class="border-t border-rule">
                <table style="min-width: {{ 51 + count($components) * 8 }}rem" class="data-table w-full table-fixed border-separate border-spacing-0 [&_td]:align-top [&_td]:text-sm [&_td+td]:border-l [&_td]:border-rule" @change="pick">
                    <colgroup>
                        <col class="w-[3rem]"><col class="w-[4.5rem]"><col class="w-[4.5rem]"><col class="w-[18rem]"><col class="w-[8rem]"><col class="w-[4rem]"><col class="w-[9rem]">
                        @foreach ($components as $key => $c)<col class="w-[8rem]">@endforeach
                    </colgroup>
                    <thead class="{{ $greenHead }}">
                        <tr>
                            <th class="{{ $freeze[0] }} sm:z-[1] bg-brand-tint !px-0 !text-center"><input type="checkbox" class="checkbox" data-select-all aria-label="Pilih semua indikator"></th>
                            <th class="{{ $freeze[1] }} sm:z-[1] bg-brand-tint">CLO</th>
                            <th class="{{ $freeze[2] }} sm:z-[1] bg-brand-tint">ID</th>
                            <th class="{{ $freeze[3] }} sm:z-[1] bg-brand-tint">Indikator</th>
                            <th>Bukti / Bab</th><th class="text-center">%</th><th>Skema Penilaian</th>
                            @foreach ($components as $key => $c)
                                <th class="text-center align-top">
                                    @if ($isPeer($key))
                                        {{-- Peer review mutlak ada (diisi mahasiswa): tanpa kotak centang, tidak dapat diubah / dihapus --}}
                                        <div class="flex flex-col items-center gap-1.5"><span class="h-5" aria-hidden="true"></span><span>{{ $c['label'] }}</span></div>
                                    @else
                                        {{-- Centang judul kolom = pilih bentuk penilaian untuk Edit / Hapus Pilihan --}}
                                        <label class="flex cursor-pointer flex-col items-center gap-1.5">
                                            <input type="checkbox" class="checkbox" value="{{ $key }}" data-select-col @checked(in_array($key, $editCols, true)) aria-label="Pilih bentuk penilaian {{ $c['label'] }}">
                                            <span>{{ $c['label'] }}</span>
                                        </label>
                                    @endif
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($indicators as $ind)
                            <tr>
                                <td class="{{ $freeze[0] }} sm:z-[1] bg-surface !px-0 text-center"><input type="checkbox" class="checkbox" value="{{ $ind->id }}" data-select-row @checked(in_array($ind->id, $editIds, true)) aria-label="Pilih indikator {{ $ind->code }}"></td>
                                <td class="{{ $freeze[1] }} sm:z-[1] bg-surface code text-ink">{{ $ind->clo?->code }}</td>
                                <td class="{{ $freeze[2] }} sm:z-[1] bg-surface code font-medium text-ink">{{ $ind->code }}</td>
                                <td class="{{ $freeze[3] }} sm:z-[1] bg-surface text-ink">{{ $ind->name }}</td>
                                <td class="{{ $ind->evidence ? 'text-ink' : 'text-muted' }}">{{ $ind->evidence ?: '—' }}</td>
                                <td class="text-center tabular font-medium text-ink">{{ $num($ind->weight) }}</td>
                                <td class="text-ink">{{ $ind->schemeLabel() }}</td>
                                @foreach ($components as $key => $c)<td class="text-center tabular text-ink">{{ $ind->weightFor($key) ? $num($ind->weightFor($key)) : '' }}</td>@endforeach
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        {{-- Baris total di bawah: Σ bobot indikator per bentuk penilaian; merah bila ≠ bobot CLO --}}
                        <tr class="bg-sunken [&>td]:border-t-2 [&>td]:border-t-rule-strong [&>td]:py-2.5 [&>td]:font-semibold">
                            <td class="{{ $freeze[0] }} sm:z-[1] bg-sunken !px-0 text-center"></td><td class="{{ $freeze[1] }} sm:z-[1] bg-sunken"></td><td class="{{ $freeze[2] }} sm:z-[1] bg-sunken"></td>
                            <td class="{{ $freeze[3] }} sm:z-[1] bg-sunken text-right text-muted">Total</td>
                            <td></td>
                            <td class="text-center tabular text-ink">{{ $num($indicators->sum('weight')) }}</td>
                            <td></td>
                            @foreach ($components as $key => $c)
                                @php $ok = abs($summary['components'][$key]['clo'] - $summary['components'][$key]['indicator']) < 0.01; @endphp
                                <td class="text-center tabular {{ $ok ? 'text-ink' : 'text-bad-ink' }}" @unless ($ok) title="Bobot CLO {{ $num($summary['components'][$key]['clo']) }}" @endunless>{{ $num($summary['components'][$key]['indicator']) }}</td>
                            @endforeach
                        </tr>
                    </tfoot>
                </table>
            </x-scroll-pager>
            <p class="border-t border-rule px-5 py-3 text-caption text-muted sm:px-6">Centang baris untuk memilih indikator, atau centang judul kolom bentuk penilaian untuk mengubah / menghapus bentuk penilaian itu. Total merah: jumlah bobot indikator belum sama dengan bobot CLO.</p>
        @endif
    </section>
</div>
</x-layouts.app>
