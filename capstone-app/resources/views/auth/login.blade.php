<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · Capstone FRI</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400..700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen">
    @php
        // Alur utama Capstone (dokumen "Alur Capstone Project Apps" §5)
        $steps = [
            'Registrasi tim & screening prasyarat',
            'Pengajuan topik mandiri atau topik dosen',
            'Penetapan pembimbing & penguji',
            'Proposal, bimbingan, dan laporan progress',
            'Laporan akhir, sidang, dan pameran',
            'Penilaian berbasis rubrik & peer review',
            'Rekap dan ekspor nilai akhir',
        ];
    @endphp
    <div class="grid min-h-screen md:grid-cols-[minmax(0,1.1fr)_minmax(0,1fr)]">
        <section class="order-2 border-t border-rule bg-brand-wash px-6 py-10 sm:px-12 md:order-1 md:border-r md:border-t-0 md:py-14">
            <div class="mx-auto flex h-full max-w-[34rem] flex-col">
                <img src="{{ asset('images/telu-horizontal.png') }}" alt="Telkom University" class="hidden h-11 w-auto self-start md:block" width="126" height="44">
                <div class="md:mt-auto">
                    <h2 class="text-h2 text-ink">Satu tempat untuk seluruh siklus Capstone</h2>
                    <p class="mt-2 text-muted">Mata kuliah Capstone Design &amp; Project Fakultas Rekayasa Industri berjalan selama 16 minggu. Setiap tahap tercatat di sini.</p>
                    <ol class="mt-8 border-l border-rule-strong">
                        @foreach ($steps as $i => $step)
                            <li class="relative pb-4 pl-6 last:pb-0">
                                <span class="absolute -left-[5px] top-[0.55rem] h-[9px] w-[9px] rounded-full border-2 border-brand bg-brand-wash" aria-hidden="true"></span>
                                <span class="code mr-2 text-muted">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                                <span class="text-ink">{{ $step }}</span>
                            </li>
                        @endforeach
                    </ol>
                </div>
            </div>
        </section>

        <main class="order-1 flex items-center px-6 py-12 sm:px-12 md:order-2">
            <div class="mx-auto w-full max-w-[24rem]">
                <img src="{{ asset('images/fri-horizontal.png') }}" alt="Fakultas Rekayasa Industri Telkom University" class="h-12 w-auto" width="214" height="48">
                <h1 class="mt-10 text-h1">Masuk ke Capstone</h1>
                <p class="mt-2 text-muted">Gunakan email dan password akun yang didaftarkan admin program studi. Peran Anda — admin, mahasiswa, atau dosen — dikenali otomatis.</p>

                @if ($errors->any())
                    <div role="alert" class="mt-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $errors->first() }}</div>
                @endif

                <form method="POST" action="{{ route('login.attempt') }}" class="mt-8 space-y-4">
                    @csrf
                    <div>
                        <label for="email" class="field-label">Email</label>
                        <input id="email" type="email" name="email" class="input" value="{{ old('email') }}" required autofocus autocomplete="username">
                    </div>
                    <div>
                        <label for="password" class="field-label">Password</label>
                        <x-password-input id="password" name="password" required autocomplete="current-password" />
                    </div>
                    <label class="flex items-center gap-3 text-sm">
                        <input type="checkbox" class="checkbox" name="remember" value="1" @checked(old('remember'))>
                        <span>Ingat saya</span>
                    </label>
                    <button class="btn btn-primary w-full text-base" type="submit">Masuk</button>
                </form>

                {{-- DATA DEMO — daftar akun dummy. Hapus blok ini saat implementasi riil (atau set DEV_LOGIN=false). --}}
                @if ($demoAccounts)
                    <div class="mt-10 border-t border-dashed border-rule-strong pt-6">
                        <p class="text-sm font-medium">Akun demo</p>
                        <p class="field-hint">Password semua akun: <span class="code">{{ config('capstone.demo_password') }}</span></p>
                        <dl class="mt-3 space-y-2 text-sm">
                            @foreach ($demoAccounts as $role => $emails)
                                <div class="flex gap-3">
                                    <dt class="w-20 shrink-0 text-muted">{{ $role }}</dt>
                                    <dd class="flex flex-wrap gap-x-3 gap-y-1">
                                        @foreach ($emails as $email)
                                            <span class="code">{{ $email }}</span>
                                        @endforeach
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif

                <img src="{{ asset('images/telu-horizontal.png') }}" alt="Telkom University" class="mt-12 h-9 w-auto md:hidden" width="103" height="36">
            </div>
        </main>
    </div>
</body>
</html>
