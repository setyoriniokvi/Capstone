{{-- Satu baris lipat ketua/anggota pada formulir registrasi (mahasiswa/_registration-fields). Judul menampilkan nama
     & NIM yang sudah diisi (diperbarui app.js saat mengetik); isian lengkap (mahasiswa/_person-fields) muncul setelah baris dibuka.
     $dot, $title, $optional; $password = tampilkan isian password ketua; $account = akun ketua yang masuk (identitas terkunci);
     $invite = anggota dipilih dari daftar mahasiswa yang belum bertim ($students), bukan diketik. --}}
@php
    $invalid = $errors->has("{$dot}.*");
    $invited = $invite ? $students->firstWhere('id', (int) old("{$dot}.user_id")) : null;
    $filled = collect(match (true) {
        (bool) $account => [$account->name, $account->nim],
        $invite => [$invited?->name, $invited?->nim],
        default => [old("{$dot}.name"), old("{$dot}.nim")],
    })->filter()->implode(' · ');
@endphp
<details class="group rounded-lg border {{ $invalid ? 'border-bad-line' : 'border-rule' }} bg-surface" @if ($invalid) open @endif>
    <summary class="flex cursor-pointer list-none items-center gap-3 px-4 py-3.5 sm:px-5 [&::-webkit-details-marker]:hidden">
        <span class="min-w-0 flex-1">
            <span class="block font-semibold text-ink">{{ $title }}@if ($optional) <span class="font-normal text-muted">(opsional)</span>@endif</span>
            <span class="block truncate text-sm {{ $invalid ? 'text-bad-ink' : 'text-muted' }}" data-summary>{{ $invalid ? 'Ada isian yang perlu diperbaiki' : ($filled ?: 'Belum diisi') }}</span>
        </span>
        <x-heroicon-o-chevron-down class="h-5 w-5 shrink-0 text-muted transition-transform group-open:rotate-180" />
    </summary>
    <div class="border-t border-rule px-4 py-4 sm:px-5 sm:py-5">
        @include('mahasiswa._person-fields', ['required' => ! $optional])

        @if ($password)
            <div class="mt-4 grid gap-4 border-t border-rule pt-4 sm:grid-cols-2">
                <x-field label="Password" name="leader.password" required hint="Minimal 8 karakter.">
                    <x-password-input id="leader.password" name="leader[password]" autocomplete="new-password" minlength="8" required />
                </x-field>
                <x-field label="Ulangi password" name="leader.password_confirmation" required>
                    <x-password-input id="leader.password_confirmation" name="leader[password_confirmation]" autocomplete="new-password" minlength="8" required />
                </x-field>
            </div>
        @endif
    </div>
</details>
