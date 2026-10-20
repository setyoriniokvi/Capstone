{{-- Formulir registrasi tim (ketua mengisi untuk seluruh tim). Butuh $team (null = tim baru), $students, $teamSize.
     Ketua = pengguna yang login (nama & NIM tidak dapat diubah). Anggota dipilih dari dropdown mahasiswa
     yang bisa dicari dengan nama/NIM (Alpine "memberRow"); daerah asal & KHS diisi per anggota. --}}
@php
    $me = auth()->user();
    $existing = $team?->members->where('invite_status', 'diterima')->keyBy('user_id') ?? collect();
    $others = $existing->reject(fn ($m) => $m->user_id === $me->id)->values();
    $hasKhs = fn ($userId) => (bool) $existing->get($userId)?->khs_path;
    $fileHint = 'PDF/JPG/PNG, maks. '.(config('capstone.upload.attachment_max_kb') / 1024).' MB';
    $options = $students->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'nim' => (string) $s->nim, 'region' => $s->origin_region])->values();
@endphp
<script type="application/json" id="data-mahasiswa">{!! $options->toJson(JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>

<x-modal>
    <x-slot:heading>{{ $team ? 'Edit registrasi tim' : 'Registrasi tim' }}</x-slot:heading>

    <form method="POST" action="{{ $team ? route('mahasiswa.team.update') : route('mahasiswa.team.store') }}" enctype="multipart/form-data" class="space-y-6">
        @csrf
        @if ($team) @method('PUT') @endif

        <x-field label="Nama kelompok" name="name" required>
            <input id="name" name="name" class="input" value="{{ old('name', $team?->name) }}" required maxlength="150">
        </x-field>

        {{-- Ketua: otomatis pengguna yang mendaftarkan --}}
        <fieldset class="rounded-lg border border-rule p-4 sm:p-5">
            <legend class="px-1 text-sm font-semibold text-ink">Ketua kelompok</legend>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="field-label" for="leader_name">Nama ketua</label>
                    <input id="leader_name" class="input bg-sunken text-muted" value="{{ $me->name }}" readonly aria-readonly="true">
                </div>
                <div>
                    <label class="field-label" for="leader_nim">NIM ketua</label>
                    <input id="leader_nim" class="input tabular bg-sunken text-muted" value="{{ $me->nim }}" readonly aria-readonly="true">
                </div>
                <x-field label="Daerah asal (berdasarkan KTP)" name="leader_region" required>
                    <input id="leader_region" name="leader_region" class="input" value="{{ old('leader_region', $me->origin_region) }}" placeholder="Kota, Provinsi" required maxlength="100">
                </x-field>
                <x-field label="KHS" name="leader_khs" :required="! $hasKhs($me->id)" :hint="$hasKhs($me->id) ? 'Sudah diunggah — unggah lagi untuk mengganti.' : $fileHint">
                    <input id="leader_khs" type="file" name="leader_khs" accept=".pdf,.jpg,.jpeg,.png" class="input py-2" @required(! $hasKhs($me->id))>
                </x-field>
            </div>
        </fieldset>

        {{-- Anggota: baris pertama s.d. jumlah minimal wajib, sisanya opsional --}}
        @for ($i = 0; $i < $teamSize['max'] - 1; $i++)
            @php
                $current = $others->get($i);
                $selected = old("members.$i.user_id", $current?->user_id);
                $region = old("members.$i.origin_region", $current?->user->origin_region);
                $required = $i < $teamSize['min'] - 1;
            @endphp
            <fieldset x-data="memberRow" data-row="{{ $i }}" data-selected="{{ $selected }}" data-region="{{ $region }}" class="rounded-lg border border-rule p-4 sm:p-5">
                <legend class="px-1 text-sm font-semibold text-ink">Anggota {{ $i + 1 }}@unless ($required) <span class="font-normal text-muted">(opsional)</span>@endunless</legend>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div class="relative sm:col-span-2" @click.outside="hide" @keydown.escape="hide">
                        <label class="field-label" for="member-{{ $i }}">{{ 'Nama / NIM anggota' }}@if ($required)<span class="text-bad-ink" aria-hidden="true"> *</span>@endif</label>
                        <input type="hidden" name="members[{{ $i }}][user_id]" :value="selectedId">

                        <div x-show="hasSelection" class="input flex items-center justify-between gap-3">
                            <span class="min-w-0 truncate" x-text="selectedLabel"></span>
                            <button type="button" class="link shrink-0 text-sm" @click="clear">Ganti</button>
                        </div>
                        <div x-show="noSelection" class="relative">
                            <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
                            <input id="member-{{ $i }}" x-ref="search" type="text" class="input pl-9" placeholder="Cari nama atau NIM mahasiswa" autocomplete="off"
                                   role="combobox" aria-autocomplete="list" aria-controls="member-{{ $i }}-list" :aria-expanded="expanded"
                                   :value="query" @input="search" @focus="show">
                        </div>
                        <ul id="member-{{ $i }}-list" x-show="open" role="listbox"
                            class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-y-auto rounded-lg border border-rule bg-surface py-1 shadow-overlay">
                            <template x-for="option in results" :key="option.id">
                                <li role="option">
                                    <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-sunken focus-visible:bg-sunken" :data-id="option.id" @click="choose">
                                        <span class="min-w-0 truncate text-ink" x-text="option.name"></span>
                                        <span class="shrink-0 tabular text-muted" x-text="option.nim"></span>
                                    </button>
                                </li>
                            </template>
                            <li x-show="noResults" class="px-3 py-2 text-sm text-muted">Mahasiswa tidak ditemukan.</li>
                        </ul>
                        @error("members.$i.user_id")<p class="field-error">{{ $message }}</p>@enderror
                    </div>

                    <x-field label="Daerah asal (berdasarkan KTP)" name="members.{{ $i }}.origin_region" :required="$required">
                        <input id="members.{{ $i }}.origin_region" name="members[{{ $i }}][origin_region]" class="input" placeholder="Kota, Provinsi" maxlength="100"
                               :value="region" @input="setRegion">
                    </x-field>
                    <x-field label="KHS" name="members.{{ $i }}.khs" :required="$required && ! ($current && $hasKhs($current->user_id))"
                             :hint="$current && $hasKhs($current->user_id) ? 'Sudah diunggah — unggah lagi untuk mengganti.' : $fileHint">
                        <input id="members.{{ $i }}.khs" type="file" name="members[{{ $i }}][khs]" accept=".pdf,.jpg,.jpeg,.png" class="input py-2">
                    </x-field>
                </div>
            </fieldset>
        @endfor
        @error('members')<p class="field-error">{{ $message }}</p>@enderror

        <div class="flex flex-wrap items-center justify-end gap-3 border-t border-rule pt-5">
            <button type="button" class="btn btn-ghost" @click="close">Batal</button>
            <button class="btn btn-primary" data-once>{{ $team ? 'Simpan & ajukan ulang' : 'Ajukan registrasi' }}</button>
        </div>
    </form>
</x-modal>
