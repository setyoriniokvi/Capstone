<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk - Capstone FRI</title>
    <x-head-assets />
</head>
{{-- Tanpa animasi intro: halaman login langsung tampil utuh saat dibuka. --}}
<body class="bg-surface">
    {{-- Desktop: foto kampus 70% + panel login 30%. Layar sempit: hanya panel login.
         Ukuran & jarak panel diatur kelas .login-* di app.css agar selalu muat satu layar. --}}
    <div class="login-shell grid overflow-hidden md:grid-cols-[minmax(0,1fr)_minmax(26rem,30%)]">
        <section class="login-hero relative hidden md:flex md:items-center" aria-label="Capstone Design and Project">
            <div class="login-hero__bg" aria-hidden="true"></div>
            <div class="login-hero__shade" aria-hidden="true"></div>
            {{-- Bulatan hijau melayang (gaya .login-hero__orbs di app.css) --}}
            <div class="login-hero__orbs" aria-hidden="true">@for ($i = 0; $i < 10; $i++)<span></span>@endfor</div>
            <div class="login-hero__content relative max-w-[52rem] px-12 text-white lg:px-16">
{{-- Ilustrasi di atas judul, selebar "Capstone Design" (≈8,4em ukuran huruf judul). Tinggi dibatasi 52vh
                     (object-contain, rata kiri) agar kolom foto tetap muat satu layar di laptop. --}}
                <img src="{{ Vite::asset('resources/images/login-illustration.png') }}" alt="" width="404" height="404"
                     class="mb-4 aspect-square max-h-[52vh] w-[8.4em] object-contain object-left text-[2.5rem] drop-shadow-xl lg:text-[3rem]" aria-hidden="true">
                <p class="text-[2.5rem] font-bold leading-[1.1] tracking-[-0.025em] lg:text-[3rem]">Capstone Design and Project</p>
                <p class="mt-4 max-w-[34rem] text-lg leading-relaxed text-white/85">Platform terintegrasi untuk mengelola Capstone Design &amp; Project di Fakultas Rekayasa Industri.</p>
            </div>
        </section>

        <main class="login-main">
            <div class="login-panel">
                <div class="login-intro">
                    <div class="login-logos">
                        <img src="{{ Vite::asset('resources/images/telu-horizontal.png') }}" alt="Telkom University" width="114" height="40">
                        <span class="h-8 w-px bg-rule-strong" aria-hidden="true"></span>
                        <img src="{{ Vite::asset('resources/images/fri-horizontal.png') }}" alt="Fakultas Rekayasa Industri" width="178" height="40">
                    </div>
                    <h1 class="login-title">Masuk ke Capstone</h1>

                    @if (session('status'))
                        <div role="status" class="mt-4 rounded-lg border border-ok-line bg-ok-tint px-4 py-3 text-sm text-ok-ink">{{ session('status') }}</div>
                    @endif
                    @if ($errors->any())
                        <div role="alert" class="mt-4 rounded-lg border border-bad-line bg-bad-tint px-4 py-3 text-sm text-bad-ink">{{ $errors->first() }}</div>
                    @endif
                </div>

                <form method="POST" action="{{ route('login.attempt') }}" class="login-form">
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
                    <p class="text-center text-sm text-muted">Belum punya akun? <a href="{{ route('register') }}" class="link">Registrasi akun &amp; tim</a></p>
                </form>

                {{-- DATA DEMO — daftar akun dummy. Hapus blok ini saat implementasi riil (atau set DEV_LOGIN=false). --}}
                @if ($demoAccounts)
                    <div class="login-demo">
                        <dl class="login-demo-list">
                            @foreach ($demoAccounts as $role => $emails)
                                <div class="flex gap-3">
                                    <dt class="w-28 shrink-0 text-muted">{{ $role }}</dt>
                                    <dd class="flex min-w-0 flex-wrap gap-x-3">
                                        @foreach ($emails as $email)
                                            <span class="code">{{ $email }}</span>
                                        @endforeach
                                    </dd>
                                </div>
                            @endforeach
                        </dl>
                    </div>
                @endif
            </div>
        </main>
    </div>
    <x-page-loader />
</body>
</html>
