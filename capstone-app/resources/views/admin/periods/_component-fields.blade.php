{{-- Isian modal tambah/ubah bentuk penilaian dosen (kartu "Jadwal pengisian nilai dosen").
     Bentuk penilaian & penilai wajib (pembimbing, penguji, atau keduanya); dibuka & deadline opsional (dapat diatur nanti).
     Jenis dokumen (opsional, boleh lebih dari satu): pilihan dari milestone berjenis Dokumen periode ini ($docTypes); menjadi
     penghubung tombol "Nilai" di Deliverable detail tim dosen.
     Butuh $c (AssessmentComponent; baru bila belum ada), $w (?AssessmentWindow periode ini), $docTypes, $action, $method, $submit, $dt.
     old() hanya dipakai pada formulir yang terakhir dikirim (_component = id atau "baru"). --}}
@php
    $formId = $c->exists ? (string) $c->id : 'baru';
    $mine = old('_component') === $formId;
    $roles = $mine ? old('roles', []) : ($c->roles ?? []);
    $types = $mine ? old('document_types', []) : ($c->document_types ?? []);
    // Pilihan: jenis dokumen milestone periode ini, ditambah yang sudah tersimpan pada bentuk penilaian ini
    $available = collect($docTypes ?? [])->merge($types);
    $typeOptions = collect(array_keys(config('capstone.document_types')))->filter(fn ($t) => $available->contains($t))->values();
    $field = fn (string $name, $value) => $mine ? old($name, $value) : $value;
    $uid = 'komp-'.$formId;
@endphp
<form method="POST" action="{{ $action }}" class="space-y-4">
    @csrf
    @if ($method === 'PUT') @method('PUT') @endif
    <input type="hidden" name="_component" value="{{ $formId }}">

    <div>
        <label class="field-label" for="{{ $uid }}-label">Bentuk penilaian<span class="text-bad-ink" aria-hidden="true"> *</span></label>
        <input id="{{ $uid }}-label" name="label" class="input" value="{{ $field('label', $c->label) }}" placeholder="Seminar Kemajuan" required maxlength="100">
        @if ($mine)@error('label')<p class="field-error">{{ $message }}</p>@enderror @endif
    </div>

    <fieldset>
        <legend class="field-label">Penilai<span class="text-bad-ink" aria-hidden="true"> *</span></legend>
        <div class="flex flex-wrap gap-x-6 gap-y-1">
            @foreach (\App\Models\AssessmentComponent::LECTURER_ROLES as $value => $text)
                <label class="flex min-h-[44px] items-center gap-3 text-sm text-ink">
                    <input type="checkbox" class="checkbox" name="roles[]" value="{{ $value }}" @checked(in_array($value, $roles, true))> {{ $text }}
                </label>
            @endforeach
        </div>
        <p class="field-hint">Centang keduanya bila pembimbing dan penguji sama-sama menilai.</p>
        @if ($mine)@error('roles')<p class="field-error">{{ $message }}</p>@enderror @error('roles.*')<p class="field-error">{{ $message }}</p>@enderror @endif
    </fieldset>

    <fieldset>
        <legend class="field-label">Jenis dokumen</legend>
        @if ($typeOptions->isEmpty())
            <p class="text-sm text-muted">Belum ada milestone berjenis Dokumen pada periode ini. Tambahkan milestone dokumen terlebih dahulu.</p>
        @else
            <div class="flex flex-wrap gap-x-6 gap-y-1">
                @foreach ($typeOptions as $type)
                    <label class="flex min-h-[44px] items-center gap-3 text-sm text-ink">
                        <input type="checkbox" class="checkbox" name="document_types[]" value="{{ $type }}" @checked(in_array($type, $types, true))> {{ config("capstone.document_types.$type", $type) }}
                    </label>
                @endforeach
            </div>
        @endif
        @if ($mine)@error('document_types')<p class="field-error">{{ $message }}</p>@enderror @error('document_types.*')<p class="field-error">{{ $message }}</p>@enderror @endif
    </fieldset>

    <div class="grid gap-4 sm:grid-cols-2">
        <div>
            <label class="field-label" for="{{ $uid }}-opens">Dibuka</label>
            <input id="{{ $uid }}-opens" type="datetime-local" name="opens_at" class="input" value="{{ $field('opens_at', $dt($w?->opens_at)) }}">
            @if ($mine)@error('opens_at')<p class="field-error">{{ $message }}</p>@enderror @endif
        </div>
        <div>
            <label class="field-label" for="{{ $uid }}-due">Deadline</label>
            <input id="{{ $uid }}-due" type="datetime-local" name="due_at" class="input" value="{{ $field('due_at', $dt($w?->due_at)) }}">
            @if ($mine)@error('due_at')<p class="field-error">{{ $message }}</p>@enderror @endif
        </div>
    </div>
    <p class="text-caption text-muted">Kosongkan jadwal bila belum ditentukan; dapat diatur nanti lewat tombol Ubah.</p>

    <div class="flex justify-end gap-3 border-t border-rule pt-4">
        <button type="button" class="btn btn-ghost" @click="close">Batal</button>
        <button class="btn btn-primary" data-once>{{ $submit }}</button>
    </div>
</form>
