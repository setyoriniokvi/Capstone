{{-- Isian indikator (Tambah Indikator & Edit Pilihan): CLO, ID, Skema, Indikator, Bukti / Bab, bobot per bentuk penilaian.
     Butuh $prefix (mis. "rows[12]" atau "" untuk tambah), $errorPrefix (mis. "rows.12." atau ""), $uid, $values,
     $clos, $schemes, $components, $star. --}}
@php
    $name = fn (string $field) => $prefix === '' ? $field : "{$prefix}[{$field}]";
    $err = fn (string $field) => $errorPrefix.$field;
@endphp
<div class="grid gap-4 sm:grid-cols-4">
    <div>
        <label class="field-label" for="{{ $uid }}-clo">CLO{!! $star !!}</label>
        <select id="{{ $uid }}-clo" name="{{ $name('clo_id') }}" class="input" required>
            @foreach ($clos as $clo)<option value="{{ $clo->id }}" @selected((string) ($values['clo_id'] ?? '') === (string) $clo->id)>{{ $clo->code }}</option>@endforeach
        </select>
        @error($err('clo_id'))<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div>
        <label class="field-label" for="{{ $uid }}-code">ID indikator{!! $star !!}</label>
        <input id="{{ $uid }}-code" name="{{ $name('code') }}" class="input tabular uppercase" maxlength="20" value="{{ $values['code'] ?? '' }}" required>
        @error($err('code'))<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-2">
        <label class="field-label" for="{{ $uid }}-scheme">Skema penilaian{!! $star !!}</label>
        <select id="{{ $uid }}-scheme" name="{{ $name('scheme') }}" class="input" required>
            @foreach ($schemes as $key => $scheme)<option value="{{ $key }}" @selected(($values['scheme'] ?? '') === $key)>{{ $scheme['label'] }}</option>@endforeach
        </select>
    </div>
    <div class="sm:col-span-4">
        <label class="field-label" for="{{ $uid }}-name">Indikator{!! $star !!}</label>
        <input id="{{ $uid }}-name" name="{{ $name('name') }}" class="input" maxlength="255" value="{{ $values['name'] ?? '' }}" required>
        @error($err('name'))<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-4">
        <label class="field-label" for="{{ $uid }}-evidence">Bukti / Bab</label>
        <input id="{{ $uid }}-evidence" name="{{ $name('evidence') }}" class="input" maxlength="255" value="{{ $values['evidence'] ?? '' }}">
    </div>
    <fieldset class="sm:col-span-4">
        <legend class="field-label">Bobot per bentuk penilaian <span class="font-normal text-muted">(Bobot % = jumlahnya)</span></legend>
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            @foreach ($components as $key => $component)
                <div>
                    <label class="text-caption text-muted" for="{{ $uid }}-w-{{ $key }}">{{ $component['label'] }}</label>
                    <input id="{{ $uid }}-w-{{ $key }}" type="number" step="0.01" min="0" max="100" name="{{ $prefix === '' ? "weights[{$key}]" : "{$prefix}[weights][{$key}]" }}" class="input tabular" value="{{ $values['weights'][$key] ?? '' }}">
                </div>
            @endforeach
        </div>
    </fieldset>
</div>
