{{-- Rubrik Dokumen: "Dokumen terkait" = jenis dokumen yang dinilai indikator ini (boleh lebih dari satu; hanya informasi),
     dipilih dari jenis dokumen milestone di pengaturan periode ($documentTypes). Nilai lama di luar daftar tetap ditampilkan
     agar tidak hilang saat disimpan. Butuh $id, $name (tanpa []), $value (array), $error, $current (array). --}}
@php
    $value = (array) ($value ?? []);
    $current = (array) ($current ?? []);
    $options = $documentTypes + collect($current)->reject(fn ($t) => isset($documentTypes[$t]))
        ->mapWithKeys(fn ($t) => [$t => config("capstone.document_types.{$t}", $t)])->all();
@endphp
<fieldset>
    <legend class="field-label">Dokumen terkait <span class="font-normal text-muted">(opsional)</span></legend>
    @if ($options === [])
        <p class="text-sm text-muted">Belum ada milestone berjenis Dokumen di pengaturan periode ini.</p>
    @else
        <div class="flex flex-wrap gap-x-6 gap-y-1">
            @foreach ($options as $key => $label)
                <label class="flex min-h-[44px] items-center gap-3 text-sm text-ink">
                    <input type="checkbox" class="checkbox" id="{{ $id }}-{{ $key }}" name="{{ $name }}[]" value="{{ $key }}" @checked(in_array((string) $key, array_map('strval', $value), true))> {{ $label }}
                </label>
            @endforeach
        </div>
    @endif
    @error($error)<p class="field-error">{{ $message }}</p>@enderror
    @error($error.'.*')<p class="field-error">{{ $message }}</p>@enderror
</fieldset>
