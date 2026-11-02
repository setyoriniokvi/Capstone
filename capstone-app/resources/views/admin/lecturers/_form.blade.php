{{-- Isian tambah/ubah (input manual: semua isian wajib, password wajib saat tambah) dosen di dalam modal halaman Data Dosen. Beberapa formulir ada di satu halaman, jadi
     ID input diberi akhiran $key dan nilai old() hanya dipakai pada formulir yang terakhir dikirim (_lecturer). --}}
@php
    $editing = $lecturer->exists;
    $key = $editing ? (string) $lecturer->id : 'baru';
    $mine = old('_lecturer') === $key;
    $val = fn (string $field, $default) => $mine ? old($field, $default) : $default;
    $star = '<span class="text-bad-ink" aria-hidden="true"> *</span>';
    $err = fn (string $field) => $mine && $errors->has($field) ? $errors->first($field) : null;
@endphp
<form method="POST" action="{{ $editing ? route('admin.lecturers.update', $lecturer) : route('admin.lecturers.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif
    <input type="hidden" name="_lecturer" value="{{ $key }}">
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ([
            ['name', 'Nama lengkap', true, 'sm:col-span-2', []],
            ['lecturer_code', 'Kode dosen', true, '', ['maxlength' => 10, 'class' => 'tabular uppercase']],
            ['nip', 'NIP', true, '', ['class' => 'tabular']],
            ['email', 'Email', true, '', ['type' => 'email']],
            ['phone', 'Nomor WhatsApp', true, '', ['inputmode' => 'tel', 'class' => 'tabular', 'hint' => 'Contoh: 081234567890']],
            ['specialization', 'Peminatan / bidang keahlian', true, 'sm:col-span-2', []],
        ] as [$field, $label, $required, $span, $opt])
            @php
                $default = in_array($field, ['name', 'email', 'phone'], true) ? $lecturer->{$field} : $profile->{$field};
                $id = "l-{$field}-{$key}";
            @endphp
            <div class="min-w-0 {{ $span }}">
                <label class="field-label" for="{{ $id }}">{{ $label }}{!! $required ? $star : '' !!}</label>
                <input id="{{ $id }}" name="{{ $field }}" type="{{ $opt['type'] ?? 'text' }}" class="input {{ $opt['class'] ?? '' }}" value="{{ $val($field, $default) }}"
                       @if ($required) required @endif @if (isset($opt['maxlength'])) maxlength="{{ $opt['maxlength'] }}" @endif @if (isset($opt['inputmode'])) inputmode="{{ $opt['inputmode'] }}" @endif>
                @if (isset($opt['hint']))<p class="field-hint">{{ $opt['hint'] }}</p>@endif
                @if ($message = $err($field))<p class="field-error">{{ $message }}</p>@endif
            </div>
        @endforeach
        <div class="min-w-0 sm:col-span-2">
            <label class="field-label" for="l-password-{{ $key }}">{{ $editing ? 'Password baru' : 'Password' }}@unless ($editing){!! $star !!}@endunless</label>
            <x-password-input id="l-password-{{ $key }}" name="password" autocomplete="new-password" minlength="6" :required="! $editing" />
            <p class="field-hint">{{ $editing ? 'Kosongkan bila tidak diubah.' : 'Password untuk login dosen.' }} Minimal 6 karakter.</p>
            @if ($message = $err('password'))<p class="field-error">{{ $message }}</p>@endif
        </div>
    </div>
    {{-- Hak akses: tercentang otomatis saat dosen diplot sebagai pembimbing / penguji; admin tetap dapat mengubahnya. --}}
    <fieldset class="mt-4">
        <legend class="field-label">Hak akses</legend>
        <div class="flex flex-wrap gap-x-6">
            <label class="flex min-h-[44px] items-center gap-3 text-muted"><input type="checkbox" class="checkbox" checked disabled><span>Dosen</span></label>
            @foreach (['can_supervise' => 'Pembimbing', 'can_examine' => 'Penguji'] as $k => $l)
                <label class="flex min-h-[44px] items-center gap-3"><input type="checkbox" class="checkbox" name="{{ $k }}" value="1" @checked($mine ? old($k) : $profile->{$k})><span>{{ $l }}</span></label>
            @endforeach
        </div>
        <p class="field-hint">Semua dosen berhak akses Dosen. Pembimbing dan Penguji tercentang otomatis saat dosen diplot.</p>
    </fieldset>
    <div class="mt-6 flex justify-end gap-2">
        <button type="button" class="btn btn-ghost" @click="close">Batal</button>
        <button class="btn btn-primary" data-once>{{ $editing ? 'Simpan perubahan' : 'Tambah dosen' }}</button>
    </div>
</form>
