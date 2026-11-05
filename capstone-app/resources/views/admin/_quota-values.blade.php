{{-- Isian kuota pembimbing & penguji (minimum · maksimum). Maksimum = kuota tim yang dapat dibimbing / diuji (0 = tidak
     menerima tim); minimum hanya untuk menandai dosen UNDER. Butuh $values (array kolom → nilai awal), $uid; $showErrors. --}}
@foreach (['supervise' => 'Kuota Pembimbing', 'examine' => 'Kuota Penguji'] as $role => $label)
    <fieldset>
        <legend class="field-label">{{ $label }}<span class="text-bad-ink" aria-hidden="true"> *</span></legend>
        <div class="grid grid-cols-2 gap-3">
            @foreach (['min' => 'Minimum', 'max' => 'Maksimum'] as $k => $l)
                <div>
                    <label for="{{ $uid }}-{{ $role }}-{{ $k }}" class="text-caption text-muted">{{ $l }}</label>
                    <input id="{{ $uid }}-{{ $role }}-{{ $k }}" type="number" min="0" max="99" name="{{ $role }}_{{ $k }}" value="{{ $values["{$role}_{$k}"] ?? 0 }}" class="input tabular" required>
                </div>
            @endforeach
        </div>
        @if ($showErrors)@error("{$role}_min")<p class="field-error">{{ $message }}</p>@enderror @endif
    </fieldset>
@endforeach
<p class="field-hint">Maksimum = jumlah tim yang dapat diterima (0 = tidak menerima tim). Minimum hanya untuk menandai dosen yang bebannya UNDER.</p>
