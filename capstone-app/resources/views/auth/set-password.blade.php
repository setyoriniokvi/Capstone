<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atur Password · Capstone FRI</title>
    <x-head-assets />
</head>
{{-- F21 — anggota tim mengatur password pertama dari tautan email (Signed URL, dikirim saat admin menerima registrasi tim). --}}
<body class="bg-sunken">
    <main class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-10">
        <div class="panel p-6 sm:p-8">
            <div class="flex items-center gap-3">
                <img src="{{ Vite::asset('resources/images/telu-horizontal.png') }}" alt="Telkom University" width="91" height="32" class="h-8 w-auto">
                <span class="h-7 w-px bg-rule-strong" aria-hidden="true"></span>
                <img src="{{ Vite::asset('resources/images/fri-horizontal.png') }}" alt="Fakultas Rekayasa Industri" width="142" height="32" class="h-8 w-auto">
            </div>
            <h1 class="mt-6 text-2xl font-bold tracking-tight text-ink">Atur password Anda</h1>
            <p class="mt-2 text-sm text-muted">Tim Anda sudah diterima admin. Buat password untuk masuk ke Capstone.</p>

            @if ($signedInAs)
                <p class="mt-4 rounded-lg border border-warn-line bg-warn-tint px-4 py-3 text-sm text-warn-ink">
                    Browser ini sedang masuk sebagai {{ $signedInAs->name }}. Setelah password disimpan, sesi tersebut akan dikeluarkan.
                </p>
            @endif

            @if ($errors->any())
                <div role="alert" class="mt-4 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ $action }}" class="mt-6 space-y-4">
                @csrf
                {{-- Identitas akun pemilik tautan (hanya ditampilkan) --}}
                @foreach ([['name', 'Nama', $user->name, ''], ['nim', 'NIM', $user->nim, 'tabular'], ['email', 'Akun', $user->email, '']] as [$id, $label, $value, $class])
                    <div>
                        <label for="{{ $id }}" class="field-label">{{ $label }}</label>
                        <input id="{{ $id }}" class="input bg-sunken text-muted {{ $class }}" value="{{ $value ?? '—' }}" readonly aria-readonly="true" @if ($id === 'email') autocomplete="username" @endif>
                    </div>
                @endforeach
                <div>
                    <label for="password" class="field-label">Password Baru</label>
                    <x-password-input id="password" name="password" required minlength="8" autocomplete="new-password" />
                    <p class="field-hint">Minimal 8 karakter.</p>
                </div>
                <div>
                    <label for="password_confirmation" class="field-label">Konfirmasi Password Baru</label>
                    <x-password-input id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password" />
                </div>
                <button class="btn btn-primary w-full text-base" data-once>Simpan password</button>
            </form>
        </div>
    </main>
    <x-page-loader />
</body>
</html>
