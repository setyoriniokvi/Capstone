@php $editing = $indicator->exists; @endphp
<x-layouts.app :title="$editing ? $indicator->code : 'Indikator baru'" section="Admin · Master penilaian">
    <x-page-head :title="$editing ? 'Ubah indikator '.$indicator->code : 'Indikator baru'" lede="Bobot indikator dihitung dari jumlah pembagian bobot ke setiap bentuk penilaian." />
    <form method="POST" action="{{ $editing ? route('admin.indicators.update', $indicator) : route('admin.indicators.store') }}" class="panel max-w-4xl">
        @csrf
        @if ($editing) @method('PUT') @endif
        <div class="grid gap-4 panel-body sm:grid-cols-4">
            <x-field label="CLO" name="clo_id" required>
                <select id="clo_id" name="clo_id" class="input">@foreach ($clos as $c)<option value="{{ $c->id }}" @selected((int) old('clo_id', $indicator->clo_id) === $c->id)>{{ $c->code }}</option>@endforeach</select>
            </x-field>
            <x-field label="ID indikator" name="code" required><input id="code" name="code" class="input tabular uppercase" value="{{ old('code', $indicator->code) }}" required></x-field>
            <x-field label="Skema penilaian" name="scheme" required class="sm:col-span-2">
                <select id="scheme" name="scheme" class="input">@foreach (config('capstone.schemes') as $k => $s)<option value="{{ $k }}" @selected(old('scheme', $indicator->scheme) === $k)>{{ $s['label'] }}</option>@endforeach</select>
            </x-field>
            <x-field label="Nama indikator" name="name" required class="sm:col-span-4"><input id="name" name="name" class="input" value="{{ old('name', $indicator->name) }}" required></x-field>
            <x-field label="Bukti / bab acuan" name="evidence" class="sm:col-span-4"><input id="evidence" name="evidence" class="input" value="{{ old('evidence', $indicator->evidence) }}"></x-field>
            <fieldset class="sm:col-span-4">
                <legend class="field-label">Pembagian bobot per bentuk penilaian</legend>
                <div class="grid gap-3 sm:grid-cols-3">
                    @foreach (config('capstone.components') as $k => $c)
                        <div><label for="w-{{ $k }}" class="text-sm text-muted">{{ $c['label'] }}</label><input id="w-{{ $k }}" type="number" step="0.01" min="0" max="100" name="weights[{{ $k }}]" class="input tabular" value="{{ old("weights.$k", $indicator->exists ? ($indicator->weightFor($k) ?: '') : '') }}"></div>
                    @endforeach
                </div>
            </fieldset>
        </div>
        <div class="flex justify-end gap-2 border-t border-rule px-4 py-3 sm:px-6">
            <a href="{{ route('admin.indicators.index') }}" class="btn btn-ghost">Batal</a>
            <button class="btn btn-primary" data-once>Simpan</button>
        </div>
    </form>
</x-layouts.app>
