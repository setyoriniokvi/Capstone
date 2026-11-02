{{-- Isian tambah/ubah (input manual: identitas/kontak/SKS/daerah asal wajib; peminatan & indeks nilai dipilih dari aturan periode,
     opsional; password wajib saat tambah) mahasiswa di dalam modal halaman Data Mahasiswa. Beberapa formulir ada di satu halaman, jadi
     ID input diberi akhiran $key dan nilai old() hanya dipakai pada formulir yang terakhir dikirim (_student). --}}
@php
    $editing = $student->exists;
    $key = $editing ? (string) $student->id : 'baru';
    $mine = old('_student') === $key;
    $val = fn (string $field, $default) => $mine ? old($field, $default) : $default;
    $star = '<span class="text-bad-ink" aria-hidden="true"> *</span>';
    $err = fn (string $field) => $mine && $errors->has($field) ? $errors->first($field) : null;
@endphp
<form method="POST" action="{{ $editing ? route('admin.students.update', $student) : route('admin.students.store') }}">
    @csrf
    @if ($editing) @method('PUT') @endif
    <input type="hidden" name="_student" value="{{ $key }}">
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach ([
            ['nim', 'NIM', true, '', ['class' => 'tabular']],
            ['name', 'Nama lengkap', true, '', []],
            ['email', 'Email', true, '', ['type' => 'email']],
            ['phone', 'Nomor WhatsApp', true, '', ['inputmode' => 'tel', 'class' => 'tabular', 'hint' => 'Contoh: 081234567890']],
            ['sks_passed', 'SKS lulus', true, '', ['type' => 'number', 'class' => 'tabular']],
            ['origin_region', 'Daerah asal', true, '', []],
        ] as [$field, $label, $required, $span, $opt])
            @php
                $default = $student->{$field};
                $id = "s-{$field}-{$key}";
            @endphp
            <div class="min-w-0 {{ $span }}">
                <label class="field-label" for="{{ $id }}">{{ $label }}{!! $required ? $star : '' !!}</label>
                <input id="{{ $id }}" name="{{ $field }}" type="{{ $opt['type'] ?? 'text' }}" class="input {{ $opt['class'] ?? '' }}" value="{{ $val($field, $default) }}"
                       @if ($required) required @endif @if (isset($opt['inputmode'])) inputmode="{{ $opt['inputmode'] }}" @endif>
                @if (isset($opt['hint']))<p class="field-hint">{{ $opt['hint'] }}</p>@endif
                @if ($message = $err($field))<p class="field-error">{{ $message }}</p>@endif
            </div>
        @endforeach
        @php $spec = $val('specialization', $student->specialization); @endphp
        <div class="min-w-0">
            <label class="field-label" for="s-specialization-{{ $key }}">Peminatan</label>
            <select id="s-specialization-{{ $key }}" name="specialization" class="input">
                <option value="">— Belum ada peminatan —</option>
                @foreach (collect($specializations)->push($student->specialization)->filter()->unique() as $option)
                    <option value="{{ $option }}" @selected($spec === $option)>{{ $option }}</option>
                @endforeach
            </select>
            <p class="field-hint">Kosongkan jika belum ada peminatan. Pilihan dari pengaturan periode.</p>
            @if ($message = $err('specialization'))<p class="field-error">{{ $message }}</p>@endif
        </div>
        @foreach ($courses as $course)
            @php $grade = $val("grades.{$course->id}", ($student->course_grades ?? [])[$course->code] ?? null); @endphp
            <div class="min-w-0">
                <label class="field-label" for="s-grade-{{ $course->id }}-{{ $key }}">Indeks nilai {{ $course->code }} — {{ $course->name }}</label>
                <select id="s-grade-{{ $course->id }}-{{ $key }}" name="grades[{{ $course->id }}]" class="input">
                    <option value="">—</option>
                    @foreach ($grades as $option)
                        <option value="{{ $option }}" @selected($grade === $option)>{{ $option }}</option>
                    @endforeach
                </select>
                @if ($message = $err("grades.{$course->id}"))<p class="field-error">{{ $message }}</p>@endif
            </div>
        @endforeach
        <div class="min-w-0 sm:col-span-2">
            <label class="field-label" for="s-password-{{ $key }}">{{ $editing ? 'Password baru' : 'Password' }}@unless ($editing){!! $star !!}@endunless</label>
            <x-password-input id="s-password-{{ $key }}" name="password" autocomplete="new-password" minlength="6" :required="! $editing" />
            <p class="field-hint">{{ $editing ? 'Kosongkan bila tidak diubah.' : 'Password untuk login mahasiswa.' }} Minimal 6 karakter.</p>
            @if ($message = $err('password'))<p class="field-error">{{ $message }}</p>@endif
        </div>
    </div>
    <div class="mt-6 flex justify-end gap-2">
        <button type="button" class="btn btn-ghost" @click="close">Batal</button>
        <button class="btn btn-primary" data-once>{{ $editing ? 'Simpan perubahan' : 'Tambah mahasiswa' }}</button>
    </div>
</form>
