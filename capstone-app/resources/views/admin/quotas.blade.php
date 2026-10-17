<x-layouts.app title="Kuota Dosen" section="Admin · Kuota beban dosen">
    <x-page-head title="Kuota beban dosen" lede="Atur jumlah tim default, minimum, dan maksimum sebagai pembimbing dan penguji. Prioritas: per dosen, lalu kategori dosen, lalu global." />

    @if (! $period)
        <div class="panel"><x-empty title="Belum ada periode aktif" icon="calendar-days" /></div>
    @else
        <div class="grid gap-6 md:grid-cols-[minmax(0,1.3fr)_minmax(0,1fr)]">
            <section class="panel">
                <div class="panel-head"><h2 class="panel-title">Aturan kuota</h2></div>
                <div class="table-wrap">
                    <table class="data-table">
                        <thead><tr><th>Berlaku untuk</th><th>Pembimbing<br><span class="normal-case">def · min · maks</span></th><th>Penguji<br><span class="normal-case">def · min · maks</span></th><th></th></tr></thead>
                        <tbody>
                            @forelse ($quotas as $q)
                                <tr>
                                    <td>@switch($q->scope)
                                        @case('global')<span class="font-medium">Semua dosen</span>@break
                                        @case('kategori')<span class="font-medium">{{ config('capstone.prodi_categories.'.$q->category) }}</span>@break
                                        @default<span class="font-medium">{{ $q->lecturer?->name }}</span>
                                    @endswitch</td>
                                    <td class="tabular">{{ $q->supervise_default }} · {{ $q->supervise_min }} · {{ $q->supervise_max }}</td>
                                    <td class="tabular">{{ $q->examine_default }} · {{ $q->examine_min }} · {{ $q->examine_max }}</td>
                                    <td class="text-right"><form method="POST" action="{{ route('admin.quotas.destroy', $q) }}" data-confirm="Hapus pengaturan kuota ini?">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-bad-ink">Hapus</button></form></td>
                                </tr>
                            @empty
                                <tr><td colspan="4"><x-empty title="Belum ada aturan kuota" icon="scale">Mulai dari aturan global.</x-empty></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="panel h-fit">
                <div class="panel-head"><h2 class="panel-title">Tambah / ubah aturan</h2></div>
                <form method="POST" action="{{ route('admin.quotas.store') }}" class="panel-body space-y-4">
                    @csrf
                    <x-field label="Cakupan" name="scope">
                        <select id="scope" name="scope" class="input">
                            <option value="global">Global — semua dosen</option>
                            <option value="kategori" @selected(old('scope') === 'kategori')>Kategori dosen</option>
                            <option value="dosen" @selected(old('scope') === 'dosen')>Dosen tertentu</option>
                        </select>
                    </x-field>
                    <x-field label="Kategori (bila cakupan kategori)" name="category">
                        <select id="category" name="category" class="input"><option value="">—</option>@foreach (config('capstone.prodi_categories') as $k => $l)<option value="{{ $k }}" @selected(old('category') === $k)>{{ $l }}</option>@endforeach</select>
                    </x-field>
                    <x-field label="Dosen (bila cakupan per dosen)" name="lecturer_id">
                        <select id="lecturer_id" name="lecturer_id" class="input"><option value="">—</option>@foreach ($lecturers as $l)<option value="{{ $l->id }}" @selected((int) old('lecturer_id') === $l->id)>{{ $l->name }}</option>@endforeach</select>
                    </x-field>
                    @foreach (['supervise' => 'Pembimbing', 'examine' => 'Penguji'] as $role => $label)
                        <fieldset>
                            <legend class="field-label">{{ $label }}</legend>
                            <div class="grid grid-cols-3 gap-2">
                                @foreach (['default' => 'Default', 'min' => 'Min', 'max' => 'Maks'] as $k => $l)
                                    <div><label for="{{ $role }}_{{ $k }}" class="text-caption text-muted">{{ $l }}</label><input id="{{ $role }}_{{ $k }}" type="number" min="0" max="99" name="{{ $role }}_{{ $k }}" value="{{ old("{$role}_{$k}", 0) }}" class="input tabular" required></div>
                                @endforeach
                            </div>
                            @error("{$role}_default")<p class="field-error">{{ $message }}</p>@enderror
                        </fieldset>
                    @endforeach
                    <button class="btn btn-primary w-full" data-once>Simpan aturan</button>
                    <p class="field-hint">Menyimpan cakupan yang sama akan menimpa aturan sebelumnya.</p>
                </form>
            </section>
        </div>
    @endif
</x-layouts.app>
