<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrasi Tim · Capstone FRI</title>
    <x-head-assets />
</head>
{{-- F21 — registrasi mahasiswa: ketua membuat akun sekaligus mendaftarkan tim & anggota (satu formulir).
     Di luar jadwal registrasi / tanpa periode aktif, formulir tetap tampil tetapi nonaktif ($blocker). --}}
<body class="bg-sunken">
    <header class="border-b border-rule bg-surface">
        <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <img src="{{ Vite::asset('resources/images/telu-horizontal.png') }}" alt="Telkom University" width="91" height="32" class="h-8 w-auto">
                <span class="h-7 w-px bg-rule-strong" aria-hidden="true"></span>
                <img src="{{ Vite::asset('resources/images/fri-horizontal.png') }}" alt="Fakultas Rekayasa Industri" width="142" height="32" class="h-8 w-auto">
            </div>
            <div class="flex shrink-0 items-center gap-3">
                <span class="hidden text-sm text-muted xs:inline">Sudah punya akun?</span>
                <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Masuk</a>
            </div>
        </div>
    </header>

    <main class="mx-auto max-w-3xl px-4 py-8 sm:px-6 sm:py-10">
        <h1 class="text-[1.75rem] font-bold tracking-tight text-ink">Registrasi akun &amp; tim</h1>
        @if ($period)<p class="mt-1 text-sm text-muted">{{ $period->name }}</p>@endif

        <section class="mt-5 rounded-lg border border-rule bg-surface px-5 py-4" aria-labelledby="alur-judul">
            <h2 id="alur-judul" class="font-semibold text-ink">Alur Registrasi Tim</h2>
            <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm text-muted marker:font-medium marker:text-ink">
                <li>Ketua tim mengisi data lengkap tim (minimal anggota tim adalah {{ $teamSize['min'] - 1 }}).</li>
                <li>Admin memvalidasi registrasi.</li>
                <li>Silakan mengecek email masing-masing secara berkala untuk melihat status penerimaan tim.</li>
                <li>Jika diterima, ketua tim dapat langsung login menggunakan email dan password yang dibuat, sedangkan anggota tim harus mengatur password terlebih dahulu melalui tautan di email untuk login.</li>
            </ol>
        </section>

        @if ($blocker)
            <p role="alert" class="mt-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $blocker }}</p>
        @endif
        @error('window')<p role="alert" class="mt-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror
        @if ($errors->any() && ! $errors->has('window'))
            <p role="alert" class="mt-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">Periksa kembali isian yang ditandai. KHS perlu dipilih ulang setelah formulir gagal dikirim.</p>
        @endif

        <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data" class="panel mt-6 p-5 sm:p-6">
            @csrf
            <fieldset class="space-y-6" @disabled($blocker)>
                @include('mahasiswa._registration-fields')

                <div class="flex flex-wrap items-center justify-end gap-3 border-t border-rule pt-5">
                    <a href="{{ route('login') }}" class="btn btn-ghost">Batal</a>
                    <button class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50" data-once @disabled($blocker)
                            @if ($blocker) title="{{ $blocker }}" @endif>Daftar Tim</button>
                </div>
            </fieldset>
        </form>
    </main>
    <x-page-loader />
</body>
</html>
