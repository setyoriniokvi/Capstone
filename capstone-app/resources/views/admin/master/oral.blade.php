{{-- F16 — Rubrik penilaian lisan: satu tabel seperti lembar "Rubrikasi Penilaian Lisan" (Level · Faktor · Sebutan ·
     Perilaku teramati pada verifikasi lisan), kotak centang per baris (Alpine "rowSelection"). Kanan atas: Tambah Level
     (modal, semua isian wajib), Edit Pilihan (modal berisi isian baris yang dicentang, disimpan sekaligus), Hapus Pilihan. --}}
@php
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $star = '<span class="text-bad-ink" aria-hidden="true"> *</span>';
    $adding = old('_form') === 'tambah';
    $editing = old('_form') === 'edit';
    $editIds = $editing ? array_map('intval', array_keys(old('rows', []))) : [];
@endphp
<x-layouts.app title="Rubrik Lisan">
<div x-data="rowSelection" data-row-selection>
    <x-page-head title="Rubrik penilaian lisan" lede="Level 1–5 dengan faktor pemahaman 0,25–1,00. Faktor dikalikan ke nilai indikator berskema Lisan atau Dokumen + Lisan.">
        <x-slot:actions>
            {{-- Hapus Pilihan --}}
            <form method="POST" action="{{ route('admin.oral.destroy-many') }}" data-confirm="Hapus level rubrik lisan yang dipilih?" data-confirm-label="Hapus">
                @csrf @method('DELETE')
                <template x-for="id in ids" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <button class="btn btn-secondary text-bad-ink disabled:cursor-not-allowed disabled:opacity-50" :disabled="noneSelected" disabled title="Centang level terlebih dahulu">
                    <x-heroicon-o-trash class="h-5 w-5" /> Hapus Pilihan
                </button>
            </form>

            {{-- Edit Pilihan --}}
            <div x-data="registrationForm" data-open="{{ $editing ? 'true' : 'false' }}">
                <button type="button" class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50" @click="open" :disabled="noneSelected" disabled title="Centang level terlebih dahulu">
                    <x-heroicon-o-pencil-square class="h-5 w-5" /> Edit Pilihan
                </button>
                <x-modal width="sm:max-w-2xl">
                    <x-slot:heading>Edit level terpilih (<span x-text="count"></span>)</x-slot:heading>
                    <form method="POST" action="{{ route('admin.oral.update-many') }}" class="space-y-5">
                        @csrf @method('PUT')
                        <input type="hidden" name="_form" value="edit">
                        @foreach ($levels as $lvl)
                            @php $old = fn ($f) => $editing ? old("rows.{$lvl->id}.{$f}", $lvl->{$f}) : $lvl->{$f}; @endphp
                            <fieldset x-show="isSelected({{ $lvl->id }})" x-cloak :disabled="! isSelected({{ $lvl->id }})" class="grid gap-3 rounded-lg border border-rule p-4 sm:grid-cols-[6rem_7rem_minmax(0,1fr)]">
                                <legend class="px-1 text-sm font-semibold text-ink">Level {{ $lvl->level }} · {{ $lvl->label }}</legend>
                                <div>
                                    <label class="field-label" for="e-{{ $lvl->id }}-level">Level{!! $star !!}</label>
                                    <input id="e-{{ $lvl->id }}-level" type="number" min="1" max="5" name="rows[{{ $lvl->id }}][level]" class="input tabular" value="{{ $old('level') }}" required>
                                </div>
                                <div>
                                    <label class="field-label" for="e-{{ $lvl->id }}-factor">Faktor{!! $star !!}</label>
                                    <input id="e-{{ $lvl->id }}-factor" type="number" step="0.01" min="0.25" max="1" name="rows[{{ $lvl->id }}][factor]" class="input tabular" value="{{ $old('factor') }}" required>
                                </div>
                                <div>
                                    <label class="field-label" for="e-{{ $lvl->id }}-label">Sebutan{!! $star !!}</label>
                                    <input id="e-{{ $lvl->id }}-label" name="rows[{{ $lvl->id }}][label]" class="input" maxlength="60" value="{{ $old('label') }}" required>
                                </div>
                                <div class="sm:col-span-3">
                                    <label class="field-label" for="e-{{ $lvl->id }}-behavior">Perilaku teramati pada verifikasi lisan{!! $star !!}</label>
                                    <textarea id="e-{{ $lvl->id }}-behavior" name="rows[{{ $lvl->id }}][behavior]" rows="3" class="input" required>{{ $old('behavior') }}</textarea>
                                </div>
                                @foreach (['level', 'factor', 'label', 'behavior'] as $f)@error("rows.{$lvl->id}.{$f}")<p class="field-error sm:col-span-3">{{ $message }}</p>@enderror @endforeach
                            </fieldset>
                        @endforeach
                        <div class="flex justify-end gap-3 border-t border-rule pt-4">
                            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                            <button class="btn btn-primary" data-once>Simpan perubahan</button>
                        </div>
                    </form>
                </x-modal>
            </div>

            {{-- Tambah level --}}
            <div x-data="registrationForm" data-open="{{ $adding ? 'true' : 'false' }}">
                <button type="button" class="btn btn-primary" @click="open" :aria-expanded="expanded"><x-heroicon-o-plus class="h-5 w-5" /> Tambah Level</button>
                <x-modal width="sm:max-w-2xl">
                    <x-slot:heading>Tambah level rubrik lisan</x-slot:heading>
                    <form method="POST" action="{{ route('admin.oral.store') }}" class="grid gap-4 sm:grid-cols-[6rem_7rem_minmax(0,1fr)]">
                        @csrf
                        <input type="hidden" name="_form" value="tambah">
                        <div>
                            <label class="field-label" for="t-level">Level{!! $star !!}</label>
                            <input id="t-level" type="number" min="1" max="5" name="level" class="input tabular" value="{{ $adding ? old('level') : '' }}" required>
                        </div>
                        <div>
                            <label class="field-label" for="t-factor">Faktor{!! $star !!}</label>
                            <input id="t-factor" type="number" step="0.01" min="0.25" max="1" name="factor" class="input tabular" value="{{ $adding ? old('factor') : '' }}" required>
                        </div>
                        <div>
                            <label class="field-label" for="t-label">Sebutan{!! $star !!}</label>
                            <input id="t-label" name="label" class="input" maxlength="60" value="{{ $adding ? old('label') : '' }}" required>
                        </div>
                        <div class="sm:col-span-3">
                            <label class="field-label" for="t-behavior">Perilaku teramati pada verifikasi lisan{!! $star !!}</label>
                            <textarea id="t-behavior" name="behavior" rows="3" class="input" required>{{ $adding ? old('behavior') : '' }}</textarea>
                        </div>
                        @if ($adding)@foreach (['level', 'factor', 'label', 'behavior'] as $f)@error($f)<p class="field-error sm:col-span-3">{{ $message }}</p>@enderror @endforeach @endif
                        <div class="flex justify-end gap-3 border-t border-rule pt-4 sm:col-span-3">
                            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                            <button class="btn btn-primary" data-once>Tambah level</button>
                        </div>
                    </form>
                </x-modal>
            </div>
        </x-slot:actions>
    </x-page-head>

    @error('ids')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror
    @error('rows')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror

    <section class="panel overflow-hidden" aria-labelledby="lisan-judul">
        <div class="flex items-center justify-between gap-3 px-5 py-4 sm:px-6">
            <h2 id="lisan-judul" class="text-lg font-semibold text-ink">Rubrikasi penilaian lisan</h2>
            <span class="text-sm text-muted"><span class="tabular font-medium text-ink" x-text="count">0</span> dipilih · {{ $levels->count() }} level</span>
        </div>
        @if ($levels->isEmpty())
            <div class="border-t border-rule"><x-empty title="Belum ada level rubrik lisan" icon="list-bullet">Tambahkan lewat tombol Tambah Level.</x-empty></div>
        @else
            <x-scroll-pager class="border-t border-rule">
                <table class="data-table w-full min-w-[48rem] table-fixed [&_td]:align-top [&_td]:text-sm [&_td+td]:border-l [&_td]:border-rule [&_tbody_tr:hover_td]:bg-transparent" @change="pick">
                    <colgroup><col class="w-[3rem]"><col class="w-[5rem]"><col class="w-[6rem]"><col class="w-[11rem]"><col></colgroup>
                    <thead class="{{ $greenHead }}"><tr>
                        <th class="!px-0 !text-center"><input type="checkbox" class="checkbox" data-select-all aria-label="Pilih semua level"></th>
                        <th class="text-center">Level</th><th class="text-center">Faktor</th><th>Sebutan</th><th>Perilaku teramati pada verifikasi lisan</th>
                    </tr></thead>
                    <tbody>
                        @foreach ($levels as $lvl)
                            <tr>
                                <td class="!px-0 text-center"><input type="checkbox" class="checkbox" value="{{ $lvl->id }}" data-select-row @checked(in_array($lvl->id, $editIds, true)) aria-label="Pilih level {{ $lvl->level }}"></td>
                                <td class="text-center tabular font-medium text-ink">{{ $lvl->level }}</td>
                                <td class="text-center tabular text-ink">{{ number_format($lvl->factor, 2, ',', '') }}</td>
                                <td class="font-medium text-ink">{{ $lvl->label }}</td>
                                <td class="text-ink">{{ $lvl->behavior }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-scroll-pager>
        @endif
    </section>
</div>
</x-layouts.app>
