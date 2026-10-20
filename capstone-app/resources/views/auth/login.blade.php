<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Masuk · Capstone FRI</title>
    <x-head-assets />
</head>
<body class="bg-surface">
    {{-- Desktop: foto kampus 70% + panel login 30%. Layar sempit: hanya panel login.
         Ukuran & jarak panel diatur kelas .login-* di app.css agar selalu muat satu layar. --}}
    <div class="login-shell grid md:grid-cols-[minmax(0,1fr)_minmax(26rem,30%)]">
        <section class="login-hero relative hidden md:flex md:items-center" aria-label="Capstone Design and Project">
            <div class="max-w-[52rem] px-12 text-white lg:px-16">
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
                    <p class="login-lede">Gunakan email dan password akun yang didaftarkan admin program studi.</p>

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
                </form>

                {{-- DATA DEMO — daftar akun dummy. Hapus blok ini saat implementasi riil (atau set DEV_LOGIN=false). --}}
                @if ($demoAccounts)
                    <div class="login-demo">
                        <p class="text-sm font-medium">Akun demo <span class="font-normal text-muted">· password: <span class="code">{{ config('capstone.demo_password') }}</span></span></p>
                        <dl class="login-demo-list">
                            @foreach ($demoAccounts as $role => $emails)
                                <div class="flex gap-3">
                                    <dt class="w-20 shrink-0 text-muted">{{ $role }}</dt>
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
</body>
</html>
