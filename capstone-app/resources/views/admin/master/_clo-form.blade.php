{{-- Isian CLO (Tambah CLO & Edit Pilihan): Kode, Skema Penilaian, Komponen Penilaian (deskripsi), bobot per bentuk
     penilaian (Bobot Penilaian = jumlahnya). Butuh $prefix ("" untuk tambah, mis. "rows[3]" untuk edit),
     $errorPrefix ("" / "rows.3."), $uid, $values, $components. --}}
@php
    $star = '<span class="text-bad-ink" aria-hidden="true"> *</span>';
    $name = fn (string $field) => $prefix === '' ? $field : "{$prefix}[{$field}]";
@endphp
<div class="grid gap-4 sm:grid-cols-4">
    <div>
        <label class="field-label" for="{{ $uid }}-code">CLO{!! $star !!}</label>
        <input id="{{ $uid }}-code" name="{{ $name('code') }}" class="input tabular uppercase" maxlength="10" value="{{ $values['code'] ?? '' }}" required>
        @error($errorPrefix.'code')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <div class="sm:col-span-3">
        <label class="field-label" for="{{ $uid }}-scheme">Skema Penilaian</label>
        <input id="{{ $uid }}-scheme" name="{{ $name('assessment_scheme') }}" class="input" maxlength="150" value="{{ $values['assessment_scheme'] ?? '' }}" placeholder="Mis. KPPM & Capstone Design dan Project">
    </div>
    <div class="sm:col-span-4">
        <label class="field-label" for="{{ $uid }}-desc">Komponen Penilaian{!! $star !!}</label>
        <textarea id="{{ $uid }}-desc" name="{{ $name('description') }}" rows="2" class="input" maxlength="2000" required>{{ $values['description'] ?? '' }}</textarea>
        @error($errorPrefix.'description')<p class="field-error">{{ $message }}</p>@enderror
    </div>
    <fieldset class="sm:col-span-4">
        <legend class="field-label">Bobot per bentuk penilaian <span class="font-normal text-muted">(Bobot Penilaian = jumlahnya)</span></legend>
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
