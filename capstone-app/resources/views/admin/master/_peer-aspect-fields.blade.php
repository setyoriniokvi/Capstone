{{-- Isian satu aspek peer review (Tambah / Edit Pilihan), wajib semua: nama aspek, definisi, deskripsi tiap level (nilai 0 / 50 / 65 / 75 / 90).
     $prefix      : null (Tambah: name="name") atau "rows[ID]" (Edit: name="rows[ID][name]")
     $errorPrefix : awalan kunci error ("" atau "rows.ID.")   $id : awalan id input   $value : fn ($field) → nilai awal
     $showErrors  : tampilkan error (bawaan true). --}}
@php
    $name = fn (string $f) => $prefix ? "{$prefix}[{$f}]" : $f;
    $showErrors ??= true;
    $star = '<span class="text-bad-ink" aria-hidden="true"> *</span>';
@endphp
<div>
    <label class="field-label" for="{{ $id }}-name">Nama aspek{!! $star !!}</label>
    <input id="{{ $id }}-name" name="{{ $name('name') }}" class="input" maxlength="150" value="{{ $value('name') }}" required placeholder="mis. Kemampuan komunikasi dan memfasilitasi tim">
    @if ($showErrors) @error($errorPrefix.'name')<p class="field-error">{{ $message }}</p>@enderror @endif
</div>
<div>
    <label class="field-label" for="{{ $id }}-definition">Definisi{!! $star !!}</label>
    <textarea id="{{ $id }}-definition" name="{{ $name('definition') }}" rows="2" class="input" maxlength="3000" required>{{ $value('definition') }}</textarea>
    @if ($showErrors) @error($errorPrefix.'definition')<p class="field-error">{{ $message }}</p>@enderror @endif
</div>
@foreach (\App\Models\PeerAspect::LEVELS as $level)
    <div>
        <label class="field-label" for="{{ $id }}-level-{{ $level }}">Deskripsi nilai {{ \App\Models\PeerAspect::POINTS[$level] }}{!! $star !!}</label>
        <textarea id="{{ $id }}-level-{{ $level }}" name="{{ $name("level_{$level}") }}" rows="2" class="input" maxlength="3000" required>{{ $value("level_{$level}") }}</textarea>
        @if ($showErrors) @error($errorPrefix."level_{$level}")<p class="field-error">{{ $message }}</p>@enderror @endif
    </div>
@endforeach
