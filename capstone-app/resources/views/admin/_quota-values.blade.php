{{-- Isian kuota pembimbing & penguji (default · minimum · maksimum). Butuh $values (array kolom → nilai awal), $uid; $showErrors (formulir yang terakhir dikirim). --}}
@foreach (['supervise' => 'Kuota Pembimbing', 'examine' => 'Kuota Penguji'] as $role => $label)
    <fieldset>
        <legend class="field-label">{{ $label }}<span class="text-bad-ink" aria-hidden="true"> *</span></legend>
        <div class="grid grid-cols-3 gap-3">
            @foreach (['default' => 'Default', 'min' => 'Minimum', 'max' => 'Maksimum'] as $k => $l)
                <div>
                    <label for="{{ $uid }}-{{ $role }}-{{ $k }}" class="text-caption text-muted">{{ $l }}</label>
                    <input id="{{ $uid }}-{{ $role }}-{{ $k }}" type="number" min="0" max="99" name="{{ $role }}_{{ $k }}" value="{{ $values["{$role}_{$k}"] ?? 0 }}" class="input tabular" required>
                </div>
            @endforeach
        </div>
        @if ($showErrors)@error("{$role}_default")<p class="field-error">{{ $message }}</p>@enderror @endif
    </fieldset>
@endforeach
