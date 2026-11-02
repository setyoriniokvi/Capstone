{{-- F9 — Kuota beban dosen: kartu "Aturan kuota" (Berlaku untuk · Kuota Pembimbing · Kuota Penguji · Edit/Hapus).
     "Tambah Kuota" (kanan atas) membuka modal: cakupan Global atau Dosen tertentu — pencarian dosen (nama/NIP/peminatan,
     boleh lebih dari satu; Alpine "proposerPicker") hanya aktif untuk dosen tertentu (Alpine "quotaForm").
     Di bawahnya kartu daftar dosen (admin/_quota-lecturers): cari kode/nama + filter peminatan, area gulir setinggi
     10,5 baris dengan tombol panah ke bawah, kuota yang berlaku per dosen, serta Edit / Reset kuota. --}}
@php
    $greenHead = 'bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3.5 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep';
    $cells = '[&_td]:px-3 [&_th]:px-3 [&_td:first-child]:pl-5 [&_th:first-child]:pl-5 [&_td:last-child]:pr-5 [&_th:last-child]:pr-5 sm:[&_td:first-child]:pl-6 sm:[&_th:first-child]:pl-6 sm:[&_td:last-child]:pr-6 sm:[&_th:last-child]:pr-6';
    $creating = $errors->any() && old('_quota') === 'baru';
    $pickerOptions = $lecturers->map(fn ($l) => [
        'id' => $l->id, 'name' => $l->name, 'nip' => (string) $l->lecturerProfile?->nip,
        'code' => (string) $l->lecturerProfile?->lecturer_code, 'specialization' => (string) $l->lecturerProfile?->specialization,
    ])->values();
    $trio = fn ($q, string $role) => [$q->{"{$role}_default"}, $q->{"{$role}_min"}, $q->{"{$role}_max"}];
@endphp
<x-layouts.app title="Kuota Dosen">
    <x-page-head title="Kuota beban dosen" lede="Atur jumlah tim default, minimum, dan maksimum sebagai pembimbing dan penguji. Prioritas: per dosen, lalu global.">
        @if ($period)
            <x-slot:actions>
                {{-- Tambah kuota: modal di atas halaman; terbuka lagi bila simpan gagal validasi --}}
                <div x-data="registrationForm" data-open="{{ $creating ? 'true' : 'false' }}">
                    <button type="button" class="btn btn-primary" @click="open" :aria-expanded="expanded"><x-heroicon-o-plus class="h-5 w-5" /> Tambah Kuota</button>
                    <x-modal width="sm:max-w-xl">
                        <x-slot:heading>Tambah kuota</x-slot:heading>
                        <script type="application/json" id="data-dosen">{!! $pickerOptions->toJson(JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
                        <form method="POST" action="{{ route('admin.quotas.store') }}" x-data="quotaForm" data-scope="{{ $creating ? old('scope', 'global') : 'global' }}" class="space-y-4">
                            @csrf
                            <input type="hidden" name="_quota" value="baru">
                            <x-field label="Cakupan" name="scope" required>
                                <select id="scope" name="scope" class="input" @change="change">
                                    <option value="global">Global (semua dosen)</option>
                                    <option value="dosen" @selected($creating && old('scope') === 'dosen')>Dosen tertentu</option>
                                </select>
                            </x-field>

                            {{-- Cari dosen: aktif hanya untuk cakupan dosen tertentu; boleh lebih dari satu --}}
                            <div class="relative" x-data="proposerPicker" data-selected="{{ $creating ? implode(',', old('lecturer_ids', [])) : '' }}" @click.outside="hide" @keydown.escape="hide">
                                <label class="field-label" for="quota-lecturer-search" :class="disabledClass">Cari dosen</label>
                                <ul class="mb-2 flex flex-wrap gap-2" aria-label="Dosen terpilih" x-show="isDosen">
                                    <template x-for="person in chosen" :key="person.id">
                                        <li class="inline-flex min-h-[36px] items-center gap-1 rounded-full border border-rule bg-sunken pl-3 pr-1 text-sm text-ink">
                                            <span x-text="person.name"></span>
                                            <input type="hidden" name="lecturer_ids[]" :value="person.id">
                                            <button type="button" class="grid h-7 w-7 place-items-center rounded-full text-muted hover:bg-surface hover:text-bad-ink" :data-id="person.id" @click="remove" :aria-label="person.removeLabel">
                                                <x-heroicon-o-x-mark class="h-4 w-4" />
                                            </button>
                                        </li>
                                    </template>
                                </ul>
                                <div class="relative">
                                    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
                                    <input id="quota-lecturer-search" x-ref="search" type="text" class="input pl-9 disabled:cursor-not-allowed disabled:bg-sunken" placeholder="Cari nama, NIP, atau peminatan dosen" autocomplete="off"
                                           role="combobox" aria-autocomplete="list" aria-controls="quota-lecturer-list" :aria-expanded="expanded"
                                           :value="query" @input="search" @focus="show" :disabled="isGlobal">
                                </div>
                                <ul id="quota-lecturer-list" x-show="open" role="listbox"
                                    class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-y-auto rounded-lg border border-rule bg-surface py-1 shadow-overlay">
                                    <template x-for="option in results" :key="option.id">
                                        <li role="option">
                                            <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-sunken focus-visible:bg-sunken" :data-id="option.id" @click="choose">
                                                <span class="min-w-0">
                                                    <span class="block truncate text-ink" x-text="option.name"></span>
                                                    <span class="block truncate text-caption text-muted" x-text="option.specialization"></span>
                                                </span>
                                                <span class="shrink-0 tabular text-caption text-muted" x-text="option.nip"></span>
                                            </button>
                                        </li>
                                    </template>
                                    <li x-show="noResults" class="px-3 py-2 text-sm text-muted">Dosen tidak ditemukan atau sudah dipilih.</li>
                                </ul>
                                <p class="field-hint">Aktif bila cakupan Dosen tertentu. Boleh memilih lebih dari satu dosen.</p>
                                @if ($creating)@error('lecturer_ids')<p class="field-error">{{ $message }}</p>@enderror @endif
                            </div>

                            @include('admin._quota-values', ['values' => $creating ? old() : [], 'uid' => 'baru', 'showErrors' => $creating])

                            <div class="flex justify-end gap-3 border-t border-rule pt-4">
                                <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                <button class="btn btn-primary" data-once>Simpan kuota</button>
                            </div>
                            <p class="text-caption text-muted">Menyimpan cakupan yang sama (global, atau dosen yang sudah punya aturan) menimpa aturan sebelumnya.</p>
                        </form>
                    </x-modal>
                </div>
            </x-slot:actions>
        @endif
    </x-page-head>

    @if (! $period)
        <div class="panel"><x-empty title="Buka dashboard periode terlebih dahulu" icon="calendar-days" /></div>
    @else
        <section class="panel overflow-hidden" aria-labelledby="kuota-judul">
            <div class="flex items-center justify-between gap-3 px-5 py-4 sm:px-6">
                <h2 id="kuota-judul" class="text-lg font-semibold text-ink">Aturan kuota</h2>
                <span class="text-sm text-muted"><span class="tabular font-medium text-ink">{{ $quotas->count() }}</span> aturan</span>
            </div>
            @if ($quotas->isEmpty())
                <div class="border-t border-rule"><x-empty title="Belum ada aturan kuota" icon="scale">Mulai dari aturan global lewat tombol Tambah Kuota.</x-empty></div>
            @else
                <div class="overflow-x-auto">
                    <table class="data-table w-full min-w-[44rem] border-t border-rule {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent">
                        <thead class="{{ $greenHead }}"><tr><th>Berlaku untuk</th><th>Kuota Pembimbing</th><th>Kuota Penguji</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                        <tbody>
                            @foreach ($quotas as $q)
                                @php $editing = $errors->any() && old('_quota') === (string) $q->id; @endphp
                                <tr x-data="registrationForm" data-open="{{ $editing ? 'true' : 'false' }}" class="[&>td]:py-4">
                                    <td>
                                        @if ($q->scope === 'global')
                                            <p class="font-medium text-ink">Semua dosen</p><p class="text-caption text-muted">Global</p>
                                        @else
                                            <p class="font-medium text-ink">{{ $q->lecturer?->name ?? '—' }}</p><p class="text-caption text-muted">{{ $q->lecturer?->lecturerProfile?->nip ?: 'Dosen tertentu' }}</p>
                                        @endif
                                    </td>
                                    @foreach (['supervise', 'examine'] as $role)
                                        @php [$def, $min, $max] = $trio($q, $role); @endphp
                                        <td>
                                            <dl class="grid w-fit grid-cols-3 gap-x-5 text-sm">
                                                <dt class="text-caption text-muted">Default</dt><dt class="text-caption text-muted">Minimum</dt><dt class="text-caption text-muted">Maksimum</dt>
                                                <dd class="tabular font-medium text-ink">{{ $def }}</dd><dd class="tabular text-ink">{{ $min }}</dd><dd class="tabular text-ink">{{ $max }}</dd>
                                            </dl>
                                        </td>
                                    @endforeach
                                    <td>
                                        <div class="flex justify-end gap-1">
                                            <button type="button" class="btn btn-ghost btn-sm" @click="open" :aria-expanded="expanded"><x-heroicon-o-pencil-square class="h-4 w-4" /> Edit</button>
                                            <form method="POST" action="{{ route('admin.quotas.destroy', $q) }}" data-confirm="Hapus aturan kuota {{ $q->scope === 'global' ? 'global' : 'untuk '.$q->lecturer?->name }}?" data-confirm-label="Hapus">
                                                @csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-bad-ink"><x-heroicon-o-trash class="h-4 w-4" /> Hapus</button>
                                            </form>
                                        </div>
                                        <x-modal width="sm:max-w-xl">
                                            <x-slot:heading>Edit kuota · {{ $q->scope === 'global' ? 'Semua dosen' : $q->lecturer?->name }}</x-slot:heading>
                                            <form method="POST" action="{{ route('admin.quotas.update', $q) }}" class="space-y-4">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="_quota" value="{{ $q->id }}">
                                                @include('admin._quota-values', ['values' => $editing ? old() : $q->only(\App\Http\Controllers\Admin\QuotaController::FIELDS), 'uid' => 'q'.$q->id, 'showErrors' => $editing])
                                                <div class="flex justify-end gap-3 border-t border-rule pt-4">
                                                    <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                                    <button class="btn btn-primary" data-once>Simpan perubahan</button>
                                                </div>
                                            </form>
                                        </x-modal>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </section>

        @include('admin._quota-lecturers')
    @endif
</x-layouts.app>
