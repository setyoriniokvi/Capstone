{{-- Isian log bimbingan: tanggal + catatan bimbingan. $log = null untuk sesi baru.
     Nilai lama & pesan error hanya untuk formulir yang dikirim (_log = id log yang diubah, kosong = sesi baru). --}}
@php
    $uid = $log?->id ?? 'new';
    $mine = old('_log') == $log?->id;
    $value = fn ($key, $default) => $mine ? old($key, $default) : $default;
@endphp
<div class="grid gap-4 sm:grid-cols-[14rem_minmax(0,1fr)]">
    <div>
        <label class="field-label" for="date-{{ $uid }}">Tanggal<span class="text-bad-ink" aria-hidden="true"> *</span></label>
        <input id="date-{{ $uid }}" type="date" name="session_date" max="{{ now()->format('Y-m-d') }}" class="input" value="{{ $value('session_date', $log?->session_date?->format('Y-m-d')) }}" required>
        @if ($mine) @error('session_date')<p class="field-error">{{ $message }}</p>@enderror @endif
    </div>
    <div>
        <label class="field-label" for="act-{{ $uid }}">Catatan bimbingan<span class="text-bad-ink" aria-hidden="true"> *</span></label>
        <textarea id="act-{{ $uid }}" name="activity" rows="4" class="input" required maxlength="3000" placeholder="Hal yang dibahas dan hasil bimbingan">{{ $value('activity', $log?->activity) }}</textarea>
        @if ($mine) @error('activity')<p class="field-error">{{ $message }}</p>@enderror @endif
    </div>
</div>
