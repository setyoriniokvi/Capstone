<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registrasi Tim - Capstone FRI</title>
    <x-head-assets />
</head>
{{-- F21 — registrasi mahasiswa: ketua membuat akun sekaligus mendaftarkan tim & anggota (satu formulir).
     Di luar jadwal registrasi / tanpa periode aktif, formulir tetap tampil tetapi nonaktif ($blocker). --}}
<body class="bg-sunken">
    {{-- Bagian atas: foto gedung kampus dari puncak halaman (di belakang topbar) sampai tepat setengah kartu Alur Registrasi
         Tim, digelapkan agar logo putih, judul, dan teks topbar terbaca. Grid empat baris (topbar | judul | ½ kartu | ½ kartu,
         dua baris terakhir sama tinggi): foto mengisi baris 1–3, kartu baris 3–4 (.register-hero di app.css). --}}
    <div class="register-hero">
        <div class="register-hero__photo" aria-hidden="true"></div>
        <header class="register-hero__bar">
            <div class="mx-auto flex max-w-3xl items-center justify-between gap-4 px-4 py-4 sm:px-6">
                <div class="flex min-w-0 items-center gap-3">
                    <img src="{{ Vite::asset('resources/images/telu-horizontal-white.png') }}" alt="Telkom University" width="91" height="32" class="h-8 w-auto">
                    <span class="h-7 w-px bg-white/40" aria-hidden="true"></span>
                    <img src="{{ Vite::asset('resources/images/fri-horizontal-white.png') }}" alt="Fakultas Rekayasa Industri" width="143" height="32" class="h-8 w-auto">
                </div>
                <div class="flex shrink-0 items-center gap-3">
                    <span class="hidden text-sm text-white/85 xs:inline">Sudah punya akun?</span>
                    <a href="{{ route('login') }}" class="btn btn-primary btn-sm">Masuk</a>
                </div>
            </div>
        </header>
        <div class="register-hero__title">
        <h1 class="text-[1.75rem] font-bold tracking-tight text-white">Registrasi akun &amp; tim</h1>
        @if ($periods->count() <= 1 && $period)
            <p class="mt-1 text-sm text-white/85">{{ $period->name }} · {{ $period->prodi_name }}</p>
        @endif
        </div>

        {{-- Kartu alur: ilustrasi di kiri (layar ≥ sm; di HP di atas, lebih kecil), langkah-langkah di kanan --}}
        <section class="register-hero__card relative flex flex-col items-center gap-4 rounded-lg border border-rule bg-surface px-5 py-4 sm:flex-row sm:gap-6" aria-labelledby="alur-judul">
            <img src="{{ Vite::asset('resources/images/register-illustration.png') }}" alt="" aria-hidden="true" width="640" height="640"
                 class="w-40 shrink-0 mix-blend-multiply sm:w-48">
            <div class="min-w-0 flex-1">
            <h2 id="alur-judul" class="font-semibold text-ink">Alur Registrasi Tim</h2>
            <ol class="mt-2 list-decimal space-y-1.5 pl-5 text-sm text-muted marker:font-medium marker:text-ink">
                <li>Ketua memilih prodi/periode capstone.</li>
                <li>Ketua tim mengisi data lengkap tim (minimal anggota tim adalah {{ $teamSize['min'] - 1 }}).</li>
                <li>Admin memvalidasi registrasi.</li>
                <li>Silakan mengecek email masing-masing secara berkala untuk melihat status penerimaan tim.</li>
                <li>Jika diterima, ketua tim dapat langsung login menggunakan email dan password yang dibuat, sedangkan anggota tim harus mengatur password terlebih dahulu melalui tautan di email untuk login.</li>
            </ol>
            </div>
        </section>
    </div>

    <main class="mx-auto max-w-3xl px-4 pb-8 sm:px-6 sm:pb-10">

        @if ($periods->count() > 1)
            {{-- Beberapa prodi membuka registrasi bersamaan: pilih periode/prodi, isian formulir mengikuti aturannya. --}}
            <form method="GET" action="{{ route('register') }}" x-data="autoSubmit" @change="submit" class="mt-5 flex flex-col gap-2 rounded-lg border border-rule bg-surface px-5 py-4 sm:flex-row sm:items-center sm:gap-3">
                <label for="pilih-periode" class="shrink-0 text-sm font-medium text-ink">Daftar ke prodi / periode</label>
                <select id="pilih-periode" name="periode" class="input min-h-[44px] w-full min-w-0 flex-1">
                    @foreach ($periods as $option)
                        <option value="{{ $option->id }}" @selected($option->id === $period?->id)>{{ $option->prodi_name }} - {{ $option->name }}</option>
                    @endforeach
                </select>
                <noscript><button class="btn btn-secondary btn-sm">Pilih</button></noscript>
            </form>
        @endif

        @if ($blocker)
            <p role="alert" class="mt-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $blocker }}</p>
        @endif
        @error('window')<p role="alert" class="mt-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $message }}</p>@enderror
        @if ($errors->any() && ! $errors->has('window'))
            <p role="alert" class="mt-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">Periksa kembali isian yang ditandai. KHS perlu dipilih ulang setelah formulir gagal dikirim.</p>
        @endif

        <form method="POST" action="{{ route('register.store') }}" enctype="multipart/form-data" class="panel mt-6 p-5 sm:p-6">
            @csrf
            <input type="hidden" name="period_id" value="{{ $period?->id }}">
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
