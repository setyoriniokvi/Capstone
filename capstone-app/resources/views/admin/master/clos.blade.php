{{-- F13 — Resume CLO: satu tabel seperti lembar "Resume Penilaian" (CLO · Komponen Penilaian · Skema Penilaian · Bobot
     Penilaian, baris Total Penilaian di bawah), kotak centang tiap baris (Alpine "rowSelection"). Kanan atas: Hapus Pilihan
     · Edit Pilihan · Tambah CLO (modal). Bobot Penilaian = jumlah pembagian bobot ke setiap bentuk penilaian (diisi di form).
     Di bawah tabel: ringkasan validasi bobot per bentuk penilaian (admin/master/_weights-summary). --}}
@php
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $num = fn ($v) => rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',');
    $adding = old('_form') === 'tambah';
    $editing = old('_form') === 'edit';
    $editIds = $editing ? array_map('intval', array_keys(old('rows', []))) : [];
    $totalOk = abs($summary['clo_total'] - 100) < 0.01;
@endphp
<x-layouts.app title="Resume CLO">
<div x-data="rowSelection" data-row-selection>
    <x-page-head title="Resume penilaian per CLO" lede="Kode, komponen penilaian, skema penilaian, dan bobot CLO. Bobot penilaian dihitung dari jumlah pembagiannya ke setiap bentuk penilaian.">
        <x-slot:actions>
            <div class="flex flex-nowrap items-center gap-2">
                {{-- Hapus Pilihan --}}
                <form method="POST" action="{{ route('admin.clos.destroy-many') }}" data-confirm="Hapus CLO yang dipilih?" data-confirm-label="Hapus">
                    @csrf @method('DELETE')
                    <template x-for="id in ids" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                    <button class="btn btn-secondary text-bad-ink disabled:cursor-not-allowed disabled:opacity-50" :disabled="noneSelected" disabled title="Centang CLO terlebih dahulu">
                        <x-heroicon-o-trash class="h-5 w-5" /> Hapus Pilihan
                    </button>
                </form>

                {{-- Edit Pilihan --}}
                <div x-data="registrationForm" data-open="{{ $editing ? 'true' : 'false' }}">
                    <button type="button" class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50" @click="open" :disabled="noneSelected" disabled title="Centang CLO terlebih dahulu">
                        <x-heroicon-o-pencil-square class="h-5 w-5" /> Edit Pilihan
                    </button>
                    <x-modal width="sm:max-w-3xl">
                        <x-slot:heading>Edit CLO terpilih (<span x-text="count"></span>)</x-slot:heading>
                        <form method="POST" action="{{ route('admin.clos.update-many') }}" class="space-y-5">
                            @csrf @method('PUT')
                            <input type="hidden" name="_form" value="edit">
                            @foreach ($clos as $clo)
                                @php
                                    $values = $editing && isset(old('rows')[$clo->id]) ? old("rows.{$clo->id}") : [
                                        'code' => $clo->code, 'description' => $clo->description, 'assessment_scheme' => $clo->assessment_scheme,
                                        'weights' => collect($components)->mapWithKeys(fn ($c, $k) => [$k => $clo->weightFor($k) ? $clo->weightFor($k) + 0 : ''])->all(),
                                    ];
                                @endphp
                                <fieldset x-show="isSelected({{ $clo->id }})" x-cloak :disabled="! isSelected({{ $clo->id }})" class="rounded-lg border border-rule p-4">
                                    <legend class="px-1 text-sm font-semibold text-ink"><span class="code">{{ $clo->code }}</span></legend>
                                    @include('admin.master._clo-form', ['prefix' => "rows[{$clo->id}]", 'errorPrefix' => "rows.{$clo->id}.", 'uid' => "e{$clo->id}", 'values' => $values])
                                </fieldset>
                            @endforeach
                            <div class="flex justify-end gap-3 border-t border-rule pt-4">
                                <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                <button class="btn btn-primary" data-once>Simpan perubahan</button>
                            </div>
                        </form>
                    </x-modal>
                </div>

                {{-- Tambah CLO --}}
                <div x-data="registrationForm" data-open="{{ $adding ? 'true' : 'false' }}">
                    <button type="button" class="btn btn-primary" @click="open" :aria-expanded="expanded"><x-heroicon-o-plus class="h-5 w-5" /> Tambah CLO</button>
                    <x-modal width="sm:max-w-3xl">
                        <x-slot:heading>Tambah CLO</x-slot:heading>
                        <form method="POST" action="{{ route('admin.clos.store') }}" class="space-y-5">
                            @csrf
                            <input type="hidden" name="_form" value="tambah">
                            @include('admin.master._clo-form', ['prefix' => '', 'errorPrefix' => '', 'uid' => 't', 'values' => $adding ? old() : []])
                            <div class="flex justify-end gap-3 border-t border-rule pt-4">
                                <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                <button class="btn btn-primary" data-once>Tambah CLO</button>
                            </div>
                        </form>
                    </x-modal>
                </div>
            </div>
        </x-slot:actions>
    </x-page-head>

    @error('ids')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror
    @error('rows')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror

    <section class="panel mb-6 overflow-hidden" aria-labelledby="clo-judul">
        <div class="flex items-center justify-between gap-3 px-5 py-4 sm:px-6">
            <h2 id="clo-judul" class="text-lg font-semibold text-ink">Resume penilaian</h2>
            <span class="text-sm text-muted"><span class="tabular font-medium text-ink" x-text="count">0</span> dipilih · {{ $clos->count() }} CLO</span>
        </div>
        @if ($clos->isEmpty())
            <div class="border-t border-rule"><x-empty title="Belum ada CLO" icon="squares-2x2">Tambahkan lewat tombol Tambah CLO.</x-empty></div>
        @else
            <x-scroll-pager class="border-t border-rule">
                <table class="data-table w-full min-w-[46rem] table-fixed [&_td]:align-top [&_td]:text-sm [&_td+td]:border-l [&_td]:border-rule [&_tbody_tr:hover_td]:bg-transparent" @change="pick">
                    <colgroup><col class="w-[3rem]"><col class="w-[5.5rem]"><col><col class="w-[16rem]"><col class="w-[8rem]"></colgroup>
                    <thead class="{{ $greenHead }}">
                        <tr>
                            <th class="!px-0 !text-center"><input type="checkbox" class="checkbox" data-select-all aria-label="Pilih semua CLO"></th>
                            <th class="!text-center">CLO</th><th>Komponen Penilaian</th><th>Skema Penilaian</th><th class="!text-center">Bobot Penilaian</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($clos as $clo)
                            <tr>
                                <td class="!px-0 text-center"><input type="checkbox" class="checkbox" value="{{ $clo->id }}" data-select-row @checked(in_array($clo->id, $editIds, true)) aria-label="Pilih {{ $clo->code }}"></td>
                                <td class="code text-center font-medium text-ink">{{ $clo->code }}</td>
                                <td class="text-ink">{{ $clo->description }}</td>
                                <td class="{{ $clo->assessment_scheme ? 'text-ink' : 'text-muted' }}">{{ $clo->assessment_scheme ?: '—' }}</td>
                                <td class="text-center tabular font-medium text-ink">{{ $num($clo->weight) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot>
                        <tr class="bg-sunken [&>td]:border-t-2 [&>td]:border-t-rule-strong [&>td]:py-2.5 [&>td]:font-semibold">
                            <td colspan="4" class="text-center text-ink">Total Penilaian</td>
                            <td class="text-center tabular {{ $totalOk ? 'text-ink' : 'text-bad-ink' }}" @unless ($totalOk) title="Total bobot CLO harus 100" @endunless>{{ $num($summary['clo_total']) }}</td>
                        </tr>
                    </tfoot>
                </table>
            </x-scroll-pager>
        @endif
    </section>

    @include('admin.master._weights-summary')
</div>
</x-layouts.app>
