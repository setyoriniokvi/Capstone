{{-- F28 — Rubrik peer review: satu tabel (No · Aspek & definisi · deskripsi level bernilai 0 / 50 / 65 / 75 / 90), kotak centang per baris (Alpine
     "rowSelection"). Kanan atas: Tambah Aspek (modal, semua isian wajib), Edit Pilihan (modal berisi isian baris yang
     dicentang, disimpan sekaligus), Hapus Pilihan (nilai peer review pada aspek itu ikut dihapus). Rubrik ini dipakai
     formulir peer review mahasiswa, rekap peer review dosen, dan perhitungan nilai peer review. --}}
@php
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $star = '<span class="text-bad-ink" aria-hidden="true"> *</span>';
    $adding = old('_form') === 'tambah';
    $editing = old('_form') === 'edit';
    $editIds = $editing ? array_map('intval', array_keys(old('rows', []))) : [];
    $levels = \App\Models\PeerAspect::LEVELS;
@endphp
<x-layouts.app title="Rubrik Peer Review">
<div x-data="rowSelection" data-row-selection>
    <x-page-head title="Rubrik peer review">
        <x-slot:actions>
            {{-- Hapus Pilihan --}}
            <form method="POST" action="{{ route('admin.peer-rubric.destroy-many') }}" data-confirm="Hapus aspek peer review yang dipilih? Nilai peer review mahasiswa pada aspek tersebut ikut dihapus." data-confirm-label="Hapus">
                @csrf @method('DELETE')
                <template x-for="id in ids" :key="id"><input type="hidden" name="ids[]" :value="id"></template>
                <button class="btn btn-secondary text-bad-ink disabled:cursor-not-allowed disabled:opacity-50" :disabled="noneSelected" disabled title="Centang aspek terlebih dahulu">
                    <x-heroicon-o-trash class="h-5 w-5" /> Hapus Pilihan
                </button>
            </form>

            {{-- Edit Pilihan --}}
            <div x-data="registrationForm" data-open="{{ $editing ? 'true' : 'false' }}">
                <button type="button" class="btn btn-secondary disabled:cursor-not-allowed disabled:opacity-50" @click="open" :disabled="noneSelected" disabled title="Centang aspek terlebih dahulu">
                    <x-heroicon-o-pencil-square class="h-5 w-5" /> Edit Pilihan
                </button>
                <x-modal width="sm:max-w-3xl">
                    <x-slot:heading>Edit aspek terpilih (<span x-text="count"></span>)</x-slot:heading>
                    <form method="POST" action="{{ route('admin.peer-rubric.update-many') }}" class="space-y-5">
                        @csrf @method('PUT')
                        <input type="hidden" name="_form" value="edit">
                        @foreach ($aspects as $aspect)
                            @php $old = fn ($f) => $editing ? old("rows.{$aspect->id}.{$f}", $aspect->{$f}) : $aspect->{$f}; @endphp
                            <fieldset x-show="isSelected({{ $aspect->id }})" x-cloak :disabled="! isSelected({{ $aspect->id }})" class="grid gap-3 rounded-lg border border-rule p-4">
                                <legend class="px-1 text-sm font-semibold text-ink">{{ $aspect->name }}</legend>
                                @include('admin.master._peer-aspect-fields', ['prefix' => "rows[{$aspect->id}]", 'errorPrefix' => "rows.{$aspect->id}.", 'id' => "e-{$aspect->id}", 'value' => $old])
                            </fieldset>
                        @endforeach
                        <div class="flex justify-end gap-3 border-t border-rule pt-4">
                            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                            <button class="btn btn-primary" data-once>Simpan perubahan</button>
                        </div>
                    </form>
                </x-modal>
            </div>

            {{-- Tambah aspek --}}
            <div x-data="registrationForm" data-open="{{ $adding ? 'true' : 'false' }}">
                <button type="button" class="btn btn-primary" @click="open" :aria-expanded="expanded"><x-heroicon-o-plus class="h-5 w-5" /> Tambah Aspek</button>
                <x-modal width="sm:max-w-3xl">
                    <x-slot:heading>Tambah aspek peer review</x-slot:heading>
                    <form method="POST" action="{{ route('admin.peer-rubric.store') }}" class="grid gap-3">
                        @csrf
                        <input type="hidden" name="_form" value="tambah">
                        @include('admin.master._peer-aspect-fields', ['prefix' => null, 'errorPrefix' => '', 'id' => 't', 'value' => fn ($f) => $adding ? old($f) : '', 'showErrors' => $adding])
                        <div class="flex justify-end gap-3 border-t border-rule pt-4">
                            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                            <button class="btn btn-primary" data-once>Tambah aspek</button>
                        </div>
                    </form>
                </x-modal>
            </div>
        </x-slot:actions>
    </x-page-head>

    @error('ids')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror
    @error('rows')<p class="mb-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror

    <section data-sort-scope class="panel overflow-hidden" aria-labelledby="peer-judul">
        <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4 sm:px-6">
            <h2 id="peer-judul" class="text-lg font-semibold text-ink">Rubrikasi peer review</h2>
            <div class="flex flex-wrap items-center justify-end gap-x-4 gap-y-2">
                @if ($aspects->isNotEmpty())<x-sort-by id="urut-peer" default="urutan" :options="['urutan' => 'No', 'nama' => 'Aspek', 'terbaru' => 'Tanggal']" />@endif
                <span class="text-sm text-muted"><span class="tabular font-medium text-ink" x-text="count">0</span> dipilih · {{ $aspects->count() }} aspek</span>
            </div>
        </div>
        @if ($aspects->isEmpty())
            <div class="border-t border-rule"><x-empty title="Belum ada aspek peer review" icon="user-group">Tambahkan lewat tombol Tambah Aspek.</x-empty></div>
        @else
            <x-scroll-pager class="border-t border-rule">
                <table class="data-table w-full min-w-[78rem] table-fixed [&_td]:align-top [&_td]:text-sm [&_td+td]:border-l [&_td]:border-rule [&_tbody_tr:hover_td]:bg-transparent" @change="pick">
                    <colgroup><col class="w-[3rem]"><col class="w-[3.5rem]"><col class="w-[16rem]">@foreach ($levels as $l)<col>@endforeach</colgroup>
                    <thead class="{{ $greenHead }}"><tr>
                        <th class="!px-0 !text-center"><input type="checkbox" class="checkbox" data-select-all aria-label="Pilih semua aspek"></th>
                        <th class="!text-center">No</th><th>Aspek</th>
                        @foreach ($levels as $l)<th class="!text-center">Nilai {{ \App\Models\PeerAspect::POINTS[$l] }}</th>@endforeach
                    </tr></thead>
                    <tbody data-sort-list>
                        @foreach ($aspects as $aspect)
                            <tr data-sort-item data-sort-urutan="{{ $loop->iteration }}" data-sort-nama="{{ $aspect->name }}" data-sort-terbaru="{{ $aspect->created_at?->timestamp }}">
                                <td class="!px-0 text-center"><input type="checkbox" class="checkbox" value="{{ $aspect->id }}" data-select-row @checked(in_array($aspect->id, $editIds, true)) aria-label="Pilih {{ $aspect->name }}"></td>
                                <td class="text-center tabular font-medium text-ink" data-sort-no>{{ $loop->iteration }}</td>
                                <td><p class="font-semibold text-ink">{{ $aspect->name }}</p><p class="mt-1 text-muted">{{ $aspect->definition }}</p></td>
                                @foreach ($levels as $l)<td class="text-ink">{{ $aspect->{"level_{$l}"} }}</td>@endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-scroll-pager>
        @endif
    </section>
</div>
</x-layouts.app>
