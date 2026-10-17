@php $uid = $clo->id ?? 'new'; @endphp
<form method="POST" action="{{ $action }}" class="grid gap-3 sm:grid-cols-6">
    @csrf
    @if ($method === 'PUT') @method('PUT') @endif
    <div><label class="field-label" for="code-{{ $uid }}">Kode</label><input id="code-{{ $uid }}" name="code" class="input tabular uppercase" value="{{ $clo->code }}" required></div>
    <div class="sm:col-span-5"><label class="field-label" for="scheme-{{ $uid }}">Skema penilaian</label><input id="scheme-{{ $uid }}" name="assessment_scheme" class="input" value="{{ $clo->assessment_scheme }}"></div>
    <div class="sm:col-span-6"><label class="field-label" for="desc-{{ $uid }}">Deskripsi</label><textarea id="desc-{{ $uid }}" name="description" rows="2" class="input" required>{{ $clo->description }}</textarea></div>
    @foreach (config('capstone.components') as $k => $c)
        <div><label class="field-label truncate" for="w-{{ $uid }}-{{ $k }}" title="{{ $c['label'] }}">{{ $c['label'] }}</label><input id="w-{{ $uid }}-{{ $k }}" type="number" step="0.01" min="0" max="100" name="weights[{{ $k }}]" class="input tabular" value="{{ $clo->exists ? ($clo->weightFor($k) ?: '') : '' }}"></div>
    @endforeach
    <div class="sm:col-span-6"><button class="btn btn-primary">Simpan</button></div>
</form>
