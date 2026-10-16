@php $uid = $log?->id ?? 'new'; @endphp
<div class="grid gap-3 sm:grid-cols-2">
    <div><label class="field-label" for="date-{{ $uid }}">Tanggal bimbingan</label><input id="date-{{ $uid }}" type="date" name="session_date" max="{{ now()->format('Y-m-d') }}" class="input" value="{{ old('session_date', $log?->session_date?->format('Y-m-d')) }}" required></div>
    <div><label class="field-label" for="pj-{{ $uid }}">Penanggung jawab</label><input id="pj-{{ $uid }}" name="responsible_person" class="input" value="{{ old('responsible_person', $log?->responsible_person) }}" required></div>
    <div class="sm:col-span-2"><label class="field-label" for="act-{{ $uid }}">Aktivitas perancangan</label><textarea id="act-{{ $uid }}" name="activity" rows="3" class="input" required>{{ old('activity', $log?->activity) }}</textarea></div>
    <div><label class="field-label" for="ins-{{ $uid }}">Instrumen</label><textarea id="ins-{{ $uid }}" name="instrument" rows="2" class="input">{{ old('instrument', $log?->instrument) }}</textarea></div>
    <div><label class="field-label" for="data-{{ $uid }}">Data yang dikumpulkan</label><textarea id="data-{{ $uid }}" name="data_collected" rows="2" class="input">{{ old('data_collected', $log?->data_collected) }}</textarea></div>
    <div class="sm:col-span-2"><label class="field-label" for="out-{{ $uid }}">Luaran aktivitas</label><textarea id="out-{{ $uid }}" name="output" rows="2" class="input" required>{{ old('output', $log?->output) }}</textarea></div>
</div>
