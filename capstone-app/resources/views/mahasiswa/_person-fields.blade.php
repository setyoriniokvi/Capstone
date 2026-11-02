{{-- Isian data satu mahasiswa pada formulir registrasi tim (ketua atau anggota). Dipakai mahasiswa/_registration-fields.
     $dot      : kunci validasi/old() — "leader" atau "members.N"; nama input dibentuk dari sini ("members[N][nim]").
     $required : baris wajib (ketua & anggota minimal); baris opsional boleh dikosongkan seluruhnya.
     $account  : ?User ketua yang masuk — NIM, nama, dan email diambil dari akunnya (hanya ditampilkan).
     $invite   : anggota dipilih (invite) dari $students lewat pencarian nama/NIM/email (Alpine "studentPicker");
                 peminatan & daerah asal terisi dari data mahasiswa terpilih.
     Butuh $courses, $specializations, $grades dari TeamRegistration::options(). --}}
@php
    $parts = explode('.', $dot);
    $base = $parts[0].collect(array_slice($parts, 1))->map(fn ($p) => "[{$p}]")->implode('');
    $input = fn (string $field) => "{$base}[{$field}]";
    $value = fn (string $field) => old("{$dot}.{$field}", $account?->{$field});
    $grade = fn ($course) => old("{$dot}.grades.{$course->id}", ($account?->course_grades ?? [])[$course->code] ?? null);
    $fileHint = 'PDF/JPG/PNG, maks. '.(config('capstone.upload.attachment_max_kb') / 1024).' MB';
@endphp
<div class="grid gap-4 sm:grid-cols-2">
    @if ($account)
        <div>
            <label class="field-label" for="{{ $dot }}.name">Nama lengkap</label>
            <input id="{{ $dot }}.name" class="input bg-sunken text-muted" value="{{ $account->name }}" readonly aria-readonly="true">
        </div>
        <div>
            <label class="field-label" for="{{ $dot }}.nim">NIM</label>
            <input id="{{ $dot }}.nim" class="input tabular bg-sunken text-muted" value="{{ $account->nim }}" readonly aria-readonly="true">
        </div>
        <div class="sm:col-span-2">
            <label class="field-label" for="{{ $dot }}.email">Email</label>
            <input id="{{ $dot }}.email" class="input bg-sunken text-muted" value="{{ $account->email }}" readonly aria-readonly="true">
        </div>
    @elseif ($invite)
        <div class="relative sm:col-span-2" x-data="studentPicker" data-row="{{ $dot }}" data-selected="{{ old("{$dot}.user_id") }}" @click.outside="hide" @keydown.escape="hide">
            <label class="field-label" for="{{ $dot }}.user_id">Nama / NIM / email anggota{!! $required ? '<span class="text-bad-ink" aria-hidden="true"> *</span>' : '' !!}</label>
            <input type="hidden" name="{{ $input('user_id') }}" :value="selectedId">

            <div x-show="hasSelection" class="input flex items-center justify-between gap-3">
                <span class="min-w-0 truncate" x-text="selectedLabel"></span>
                <button type="button" class="link shrink-0 text-sm" @click="clear">Ganti</button>
            </div>
            <div x-show="noSelection" class="relative">
                <x-heroicon-o-magnifying-glass class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-faint" />
                <input id="{{ $dot }}.user_id" x-ref="search" type="text" class="input pl-9" placeholder="Cari nama, NIM, atau email mahasiswa" autocomplete="off"
                       role="combobox" aria-autocomplete="list" aria-controls="{{ $dot }}-list" :aria-expanded="expanded"
                       :value="query" @input="search" @focus="show">
            </div>
            <ul id="{{ $dot }}-list" x-show="open" role="listbox"
                class="absolute inset-x-0 top-full z-20 mt-1 max-h-72 overflow-y-auto rounded-lg border border-rule bg-surface py-1 shadow-overlay">
                <template x-for="option in results" :key="option.id">
                    <li role="option">
                        <button type="button" class="flex w-full items-center justify-between gap-3 px-3 py-2 text-left text-sm hover:bg-sunken focus-visible:bg-sunken" :data-id="option.id" @click="choose">
                            <span class="min-w-0">
                                <span class="block truncate text-ink" x-text="option.name"></span>
                                <span class="block truncate text-caption text-muted" x-text="option.email"></span>
                            </span>
                            <span class="shrink-0 tabular text-muted" x-text="option.nim"></span>
                        </button>
                    </li>
                </template>
                <li x-show="noResults" class="px-3 py-2 text-sm text-muted">Mahasiswa tidak ditemukan atau sudah bertim.</li>
            </ul>
            <p class="field-hint">Hanya mahasiswa terdaftar yang belum tergabung di tim pada periode ini.</p>
            @error("{$dot}.user_id")<p class="field-error">{{ $message }}</p>@enderror
        </div>
    @else
    <x-field label="Nama lengkap" name="{{ $dot }}.name" :required="$required">
        <input id="{{ $dot }}.name" name="{{ $input('name') }}" data-summary-source="name" class="input" value="{{ $value('name') }}" maxlength="150" autocomplete="{{ $dot === 'leader' ? 'name' : 'off' }}" @required($required)>
    </x-field>
    <x-field label="NIM" name="{{ $dot }}.nim" :required="$required">
        <input id="{{ $dot }}.nim" name="{{ $input('nim') }}" data-summary-source="nim" class="input tabular" value="{{ $value('nim') }}" inputmode="numeric" pattern="[0-9]{6,20}" maxlength="20" autocomplete="off" @required($required)>
    </x-field>
    <x-field label="Email" name="{{ $dot }}.email" class="sm:col-span-2" :required="$required"
             :hint="$dot === 'leader' ? 'Dipakai untuk masuk ke aplikasi.' : 'Tautan atur password dikirim ke email ini setelah tim disetujui admin.'">
        <input id="{{ $dot }}.email" type="email" name="{{ $input('email') }}" class="input" value="{{ $value('email') }}" maxlength="150" autocomplete="{{ $dot === 'leader' ? 'email' : 'off' }}" @required($required)>
    </x-field>
    @endif

    <x-field label="Peminatan" name="{{ $dot }}.specialization" hint="Kosongkan jika belum ada peminatan.">
        <select id="{{ $dot }}.specialization" name="{{ $input('specialization') }}" class="input">
            <option value="">— Belum ada peminatan —</option>
            @foreach ($specializations as $spec)
                <option value="{{ $spec }}" @selected($value('specialization') === $spec)>{{ $spec }}</option>
            @endforeach
        </select>
    </x-field>
    <x-field label="Total SKS sudah lulus" name="{{ $dot }}.sks_passed" :required="$required">
        <input id="{{ $dot }}.sks_passed" type="number" min="0" max="200" name="{{ $input('sks_passed') }}" class="input tabular" value="{{ $value('sks_passed') }}" @required($required)>
    </x-field>

    @foreach ($courses as $course)
        <x-field label="Indeks nilai {{ $course->code }} — {{ $course->name }}" name="{{ $dot }}.grades.{{ $course->id }}" :required="$required">
            <select id="{{ $dot }}.grades.{{ $course->id }}" name="{{ $base }}[grades][{{ $course->id }}]" class="input" @required($required)>
                <option value="">Pilih indeks nilai</option>
                @foreach ($grades as $option)
                    <option value="{{ $option }}" @selected($grade($course) === $option)>{{ $option }}</option>
                @endforeach
            </select>
        </x-field>
    @endforeach

    <x-field label="Daerah asal (berdasarkan KTP)" name="{{ $dot }}.origin_region" :required="$required">
        <input id="{{ $dot }}.origin_region" name="{{ $input('origin_region') }}" class="input" value="{{ $value('origin_region') }}" placeholder="Kota, Provinsi" maxlength="100" @required($required)>
    </x-field>
    <x-field label="KHS" name="{{ $dot }}.khs" class="sm:col-span-2" :required="$required" :hint="$fileHint">
        <input id="{{ $dot }}.khs" type="file" name="{{ $input('khs') }}" accept=".pdf,.jpg,.jpeg,.png" class="input py-2" @required($required)>
    </x-field>
</div>
