{{-- Kartu daftar dosen di Kuota Dosen (Alpine "peekTable"): cari kode/nama + filter peminatan, area gulir setinggi
     10,5 baris (baris ke-11 terlihat setengah) dengan tombol bulat panah ke bawah bila lebih dari 10 dosen.
     Kolom: Kode, Nama, Kontak, Peminatan, Status dosen (Pembimbing/Penguji), Kuota awal yang berlaku sebagai pembimbing & penguji (default ·
     minimum · maksimum), lalu Edit (aturan kuota per dosen) dan Reset (kuota per dosen menjadi 0).
     Butuh $rows, $specializations, $greenHead, $cells. --}}
@php $phone = fn (?string $p) => $p ? preg_replace('/^(\d{4})(\d{4})(\d+)$/', '$1-$2-$3', $p) : null; @endphp
<section x-data="peekTable" class="panel mt-6 overflow-hidden" aria-labelledby="dosen-kuota-judul">
    <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:px-6">
        <h2 id="dosen-kuota-judul" class="shrink-0 text-lg font-semibold text-ink">Kuota per dosen</h2>
        <div class="flex min-w-0 flex-1 flex-col gap-3 sm:flex-row sm:justify-end">
            <div class="relative min-w-0 sm:w-80">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
                <label for="cari-dosen-kuota" class="sr-only">Cari kode atau nama dosen</label>
                <input id="cari-dosen-kuota" type="search" class="input pl-9" placeholder="Cari kode atau nama dosen" @input="search">
            </div>
            <label for="filter-peminatan-kuota" class="sr-only">Filter peminatan</label>
            <select id="filter-peminatan-kuota" class="input sm:w-56" @change="pick">
                <option value="">Semua peminatan</option>
                @foreach ($specializations as $spec)<option value="{{ $spec }}">{{ $spec }}</option>@endforeach
            </select>
        </div>
    </div>

    @if ($rows->isEmpty())
        <div class="border-t border-rule"><x-empty title="Belum ada dosen aktif" icon="academic-cap" /></div>
    @else
        <div class="relative border-t border-rule">
            <div x-ref="scroller" class="overflow-auto" @scroll="track">
                <table class="data-table w-full min-w-[62rem] {{ $cells }} [&_tbody_tr:hover_td]:bg-transparent">
                    <thead class="sticky top-0 z-10 {{ $greenHead }}"><tr>
                        <th>Kode</th><th>Nama</th><th>Kontak</th><th>Peminatan</th><th>Status dosen</th><th>Kuota</th><th><span class="sr-only">Aksi</span></th>
                    </tr></thead>
                    <tbody>
                        @foreach ($rows as $row)
                            @php
                                $l = $row['lecturer'];
                                $profile = $l->lecturerProfile;
                                $roles = array_keys(array_filter(['pembimbing' => $profile?->can_supervise, 'penguji' => $profile?->can_examine]));
                                $editing = $errors->any() && old('_quota') === 'dosen-'.$l->id;
                            @endphp
                            <tr x-data="registrationForm" data-open="{{ $editing ? 'true' : 'false' }}" class="[&>td]:py-3 [&>td]:align-top"
                                data-search="{{ \Illuminate\Support\Str::lower(($profile?->lecturer_code ?? '').' '.$l->name) }}" data-category="{{ $profile?->specialization }}">
                                <td class="code font-medium text-ink">{{ $profile?->lecturer_code ?? '—' }}</td>
                                <td><p class="font-medium text-ink">{{ $l->name }}</p>@if ($profile?->nip)<p class="code text-caption text-muted">{{ $profile->nip }}</p>@endif</td>
                                <td class="text-sm"><p class="text-ink">{{ $l->email }}</p><p class="tabular text-muted">{{ $l->phone ? 'WA '.$phone($l->phone) : '— WA belum diisi' }}</p></td>
                                <td class="text-sm text-ink">{{ $profile?->specialization ?? '—' }}</td>
                                <td>
                                    <div class="flex flex-wrap gap-1">
                                        @forelse ($roles as $role)<span class="rounded-md border border-rule bg-sunken px-2 py-0.5 text-caption font-medium text-muted">{{ ucfirst($role) }}</span>@empty<span class="text-sm text-muted">Dosen</span>@endforelse
                                    </div>
                                </td>
                                <td class="text-sm">
                                    {{-- Kuota awal dosen sebagai pembimbing & penguji (sebelum diplot), apa pun statusnya --}}
                                    <div>
                                        <dl class="grid w-fit grid-cols-[auto_repeat(3,auto)] gap-x-4 gap-y-1">
                                            <span></span><dt class="text-caption text-muted">Default</dt><dt class="text-caption text-muted">Min</dt><dt class="text-caption text-muted">Maks</dt>
                                            @foreach (['pembimbing', 'penguji'] as $role)
                                                <dt class="text-caption text-muted">{{ ucfirst($role) }}</dt>
                                                <dd class="tabular font-medium text-ink">{{ $row['quota'][$role]['default'] }}</dd>
                                                <dd class="tabular text-ink">{{ $row['quota'][$role]['min'] }}</dd>
                                                <dd class="tabular text-ink">{{ $row['quota'][$role]['max'] }}</dd>
                                            @endforeach
                                        </dl>
                                        @if ($row['own'])<p class="mt-1 text-caption text-muted">Aturan per dosen</p>@endif
                                    </div>
                                </td>
                                <td class="!align-middle">
                                    <div class="flex justify-end gap-1">
                                        <button type="button" class="btn btn-ghost btn-sm" @click="open" :aria-expanded="expanded"><x-heroicon-o-pencil-square class="h-4 w-4" /> Edit</button>
                                        <form method="POST" action="{{ route('admin.quotas.reset', $l) }}" data-confirm="Reset kuota {{ $l->name }}? Kuota pembimbing dan penguji (default, minimum, maksimum) menjadi 0." data-confirm-label="Reset">
                                            @csrf<button class="btn btn-ghost btn-sm text-bad-ink"><x-heroicon-o-arrow-path class="h-4 w-4" /> Reset</button>
                                        </form>
                                    </div>
                                    {{-- Edit = simpan aturan kuota khusus dosen ini (menimpa aturan per dosen yang ada) --}}
                                    <x-modal width="sm:max-w-xl">
                                        <x-slot:heading>Edit kuota · {{ $l->name }}</x-slot:heading>
                                        <form method="POST" action="{{ route('admin.quotas.store') }}" class="space-y-4">
                                            @csrf
                                            <input type="hidden" name="_quota" value="dosen-{{ $l->id }}">
                                            <input type="hidden" name="scope" value="dosen">
                                            <input type="hidden" name="lecturer_ids[]" value="{{ $l->id }}">
                                            @include('admin._quota-values', [
                                                'values' => $editing ? old() : collect(['supervise' => 'pembimbing', 'examine' => 'penguji'])
                                                    ->flatMap(fn ($role, $prefix) => collect($row['quota'][$role])->only(['default', 'min', 'max'])->mapWithKeys(fn ($v, $k) => ["{$prefix}_{$k}" => $v]))->all(),
                                                'uid' => 'd'.$l->id, 'showErrors' => $editing,
                                            ])
                                            <div class="flex justify-end gap-3 border-t border-rule pt-4">
                                                <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                                <button class="btn btn-primary" data-once>Simpan kuota</button>
                                            </div>
                                        </form>
                                    </x-modal>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p x-show="empty" x-cloak class="px-5 py-5 text-sm text-muted sm:px-6">Tidak ada dosen yang cocok.</p>
            </div>
            {{-- Tombol bulat panah ke bawah bila daftar lebih dari 10 dosen --}}
            <button type="button" x-show="showArrow" x-cloak @click="down" aria-label="Gulir daftar dosen ke bawah"
                    class="absolute bottom-3 left-1/2 z-20 grid h-10 w-10 -translate-x-1/2 place-items-center rounded-full border border-rule bg-surface text-ink shadow-overlay hover:bg-sunken">
                <x-heroicon-o-arrow-down class="h-5 w-5" />
            </button>
        </div>
    @endif
</section>
