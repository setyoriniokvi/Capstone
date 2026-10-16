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
                <p class="mt-2 text-muted">Gunakan akun Google yang terdaftar oleh admin program studi. Peran Anda — admin, mahasiswa, atau dosen — dikenali otomatis.</p>

                @if ($errors->any())
                    <div role="alert" class="mt-6 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $errors->first() }}</div>
                @endif

                <a href="{{ route('auth.google') }}"
                   class="btn btn-secondary mt-8 w-full gap-3 border-rule-strong text-base {{ $googleReady ? '' : 'pointer-events-none opacity-50' }}"
                   @unless ($googleReady) aria-disabled="true" @endunless>
                    <svg class="h-5 w-5" viewBox="0 0 48 48" aria-hidden="true">
                        <path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.7 29.2 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.4-.4-3.5z"/>
                        <path fill="#FF3D00" d="m6.3 14.7 6.6 4.8C14.7 15.1 19 12 24 12c3.1 0 5.8 1.2 7.9 3.1l5.7-5.7C34 6.1 29.3 4 24 4 16.3 4 9.7 8.3 6.3 14.7z"/>
                        <path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.2 35.1 26.7 36 24 36c-5.2 0-9.6-3.3-11.3-8l-6.5 5C9.5 39.6 16.2 44 24 44z"/>
                        <path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-.8 2.2-2.2 4.2-4.1 5.6l6.2 5.2C37 39.2 44 34 44 24c0-1.3-.1-2.4-.4-3.5z"/>
                    </svg>
                    Masuk dengan Google
                </a>
                @unless ($googleReady)
                    <p class="field-hint">Login Google belum dikonfigurasi pada server ini.</p>
                @endunless

                @if ($devUsers->isNotEmpty())
                    <form method="POST" action="{{ route('auth.dev') }}" class="mt-10 border-t border-dashed border-rule-strong pt-6">
                        @csrf
                        <p class="text-sm font-medium">Mode pengembangan</p>
                        <p class="field-hint">Hanya muncul bila <span class="code">APP_ENV=local</span> dan <span class="code">DEV_LOGIN=true</span>.</p>
                        <div class="mt-3 flex gap-2">
                            <label for="user_id" class="sr-only">Pilih akun</label>
                            <select id="user_id" name="user_id" class="input">
                                @foreach ($devUsers->groupBy('role') as $role => $users)
                                    <optgroup label="{{ ucfirst($role) }}">
                                        @foreach ($users as $u)
                                            <option value="{{ $u->id }}">{{ $u->name }}</option>
                                        @endforeach
                                    </optgroup>
                                @endforeach
                            </select>
                            <button class="btn btn-primary" type="submit">Masuk</button>
                        </div>
                    </form>
                @endif

                <img src="{{ asset('images/telu-horizontal.png') }}" alt="Telkom University" class="mt-12 h-9 w-auto md:hidden" width="103" height="36">
            </div>
        </main>
    </div>
</body>
</html>
