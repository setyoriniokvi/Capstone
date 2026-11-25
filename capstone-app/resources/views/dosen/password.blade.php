{{-- Dosen mengganti password sendiri (menu profil → Ganti Password). Akun hasil impor memakai password default. --}}
<x-layouts.app title="Ganti Password">
    <x-page-head title="Ganti password" lede="Password ini dipakai untuk masuk ke Capstone di semua periode dan perangkat Anda." />

    @if ($usesDefault)
        <p role="status" class="mb-6 max-w-xl rounded-lg border border-warn-line bg-warn-tint px-4 py-3 text-sm text-warn-ink">
            Akun Anda masih memakai password default. Ganti dengan password Anda sendiri agar akun tetap aman.
        </p>
    @endif

    <form method="POST" action="{{ route('dosen.password.update') }}" class="panel max-w-xl">
        @csrf @method('PUT')
        <div class="panel-body space-y-4">
            <x-field label="Password saat ini" name="current_password" required>
                <x-password-input id="current_password" name="current_password" required autocomplete="current-password" />
            </x-field>
            <x-field label="Password baru" name="password" required hint="Minimal 8 karakter dan berbeda dari password default.">
                <x-password-input id="password" name="password" required minlength="8" maxlength="100" autocomplete="new-password" />
            </x-field>
            <x-field label="Ulangi password baru" name="password_confirmation" required>
                <x-password-input id="password_confirmation" name="password_confirmation" required minlength="8" maxlength="100" autocomplete="new-password" />
            </x-field>
            <p class="text-sm text-muted">Setelah password diganti, perangkat lain yang masih masuk dengan akun ini perlu masuk ulang. Perangkat ini tetap masuk.</p>
        </div>
        <div class="flex justify-end gap-2 border-t border-rule px-4 py-4 sm:px-6">
            <a href="{{ route('dosen.dashboard') }}" class="btn btn-ghost">Batal</a>
            <button class="btn btn-primary" data-once>Simpan password</button>
        </div>
    </form>
</x-layouts.app>
