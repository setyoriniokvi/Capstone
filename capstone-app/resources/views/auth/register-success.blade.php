<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tim Diajukan · Capstone FRI</title>
    <x-head-assets />
</head>
{{-- F21 — konfirmasi setelah registrasi akun + tim berhasil dikirim (hanya tampil sekali, dari flash "registered"). --}}
<body class="bg-sunken">
    <main class="mx-auto flex min-h-screen max-w-lg flex-col justify-center px-4 py-10">
        <div class="panel p-6 text-center sm:p-8" role="status">
            <span class="mx-auto grid h-14 w-14 place-items-center rounded-full bg-ok-tint text-ok-ink">
                <x-heroicon-s-check-circle class="h-8 w-8" />
            </span>
            <h1 class="mt-5 text-2xl font-bold tracking-tight text-ink">Tim Anda berhasil diajukan</h1>
            <p class="mt-1 text-sm font-medium text-muted">{{ $teamName }}</p>
            <p class="mt-4 text-body text-muted">
                Silakan tunggu status tim Anda melalui email masing-masing anggota, dan atur password anggota melalui email masing-masing.
            </p>
            <a href="{{ route('login') }}" class="btn btn-primary mt-6 w-full text-base">Ke halaman masuk</a>
        </div>
    </main>
    <x-page-loader />
</body>
</html>
