@props(['title' => 'Capstone', 'banner' => false, 'sidebar' => true])
@php
    $user = auth()->user();
    $nav = \App\Support\Navigation::for($user);
    $activePeriod = \App\Models\Period::active();
    // Dosen / mahasiswa di beberapa periode aktif (prodi paralel) mendapat menu ganti periode.
    $userPeriods = $user->isAdmin() ? collect() : \App\Support\PeriodContext::periodsFor($user);
    // Admin bekerja di dalam periode yang dipilih (dropdown); di luar itu = dashboard awal Kelola Periode.
    $workspace = $user->isAdmin() ? \App\Models\Period::workspace() : null;
    // Dashboard awal admin (Kelola Periode) tanpa sidebar: menunya hanya satu. :sidebar="false" = halaman fokus tanpa
    // sidebar (mis. form penilaian dosen; kembali lewat tombol Kembali).
    $withSidebar = $sidebar && ! ($user->isAdmin() && ! $workspace);
    // Status sidebar pilihan pengguna (cookie tanpa enkripsi, ditulis app.js), berlaku di semua ukuran layar — dirender di server
    // agar tidak berkedip. "auto" (belum pernah memilih): tampil di layar ≥ 768px, tersembunyi di layar sempit.
    $sidebarState = in_array(request()->cookie('sidebar'), ['open', 'collapsed'], true) ? request()->cookie('sidebar') : 'auto';
    $sidebarCollapsed = $withSidebar && $sidebarState === 'collapsed';
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Capstone' }} - Capstone FRI</title>
    <x-head-assets />
</head>
<body x-data="shell" data-sidebar="{{ $sidebarState }}" :data-sidebar="sidebarState" class="min-h-screen">
    <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-surface focus:px-4 focus:py-2">Lewati ke konten</a>

    @if ($withSidebar)
    {{-- Tampil / tersembunyi menurut body[data-sidebar] (lihat app.css), di semua ukuran layar; lebar = --sidebar-w.
         Sidebar yang tampil mendorong konten ke kanan sehingga halaman menyesuaikan sisa lebar layar. --}}
    <aside id="sidebar"
           class="fixed inset-y-0 left-0 z-40 flex flex-col border-r border-rule bg-surface">
        <div class="px-5 pb-4 pt-5">
            <a href="{{ route($user->homeRoute()) }}" class="block" aria-label="Beranda Capstone">
                <img src="{{ Vite::asset('resources/images/fri-horizontal.png') }}" alt="Fakultas Rekayasa Industri Telkom University" class="h-10 w-auto" width="178" height="40">
            </a>
            @if ($workspace)
                <x-admin-period-switcher :current="$workspace" />
            @elseif ($userPeriods->count() > 1 && $activePeriod)
                <x-user-period-switcher :current="$activePeriod" :periods="$userPeriods" />
            @else
                <div class="mt-4 flex items-baseline justify-between gap-2 border-t border-rule pt-3">
                    <span class="text-sm font-semibold tracking-tight text-ink">Capstone Design &amp; Project</span>
                </div>
                <p class="mt-0.5 text-caption text-muted">
                    @if ($user->isAdmin())
                        Kelola periode
                    @elseif ($activePeriod)
                        {{ $activePeriod->prodi_name }} · <span class="code">{{ $activePeriod->academic_year }}</span> · {{ ucfirst($activePeriod->semester) }}
                    @else
                        Belum ada periode aktif
                    @endif
                </p>
            @endif
        </div>

        <nav data-sidebar-scroll class="flex-1 overflow-y-auto px-3 pb-4" aria-label="Menu utama">
            @foreach ($nav as $group)
                @if ($group['label'])
                    <p class="mb-1 mt-5 px-3 text-caption font-semibold text-muted">{{ $group['label'] }}</p>
                @endif
                <ul class="space-y-0.5">
                    @foreach ($group['items'] as $item)
                        @php $active = request()->routeIs($item['match']); @endphp
                        <li>
                            <a href="{{ route($item['route']) }}"
                               @if ($active) aria-current="page" @endif
                               class="flex min-h-[40px] items-center gap-3 rounded px-3 text-[0.9375rem] {{ $active ? 'bg-brand-tint font-medium text-brand-deep' : 'text-ink hover:bg-sunken' }}">
                                <x-dynamic-component :component="'heroicon-o-'.$item['icon']" class="h-5 w-5 shrink-0 {{ $active ? 'text-brand' : 'text-muted' }}" />
                                <span class="truncate">{{ $item['label'] }}</span>
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endforeach
        </nav>
        {{-- Pulihkan posisi gulir menu sidebar sebelum halaman tampil (disimpan oleh app.js saat menggulir / meninggalkan halaman);
             belum ada posisi tersimpan → menu aktif digulir agar terlihat. --}}
        <script nonce="{{ Vite::cspNonce() }}">(function () { var nav = document.currentScript.previousElementSibling; try { var y = sessionStorage.getItem('sidebar-scroll'); if (y !== null) { nav.scrollTop = +y; return; } } catch (e) {} var active = nav.querySelector('[aria-current="page"]'); if (active) active.scrollIntoView({ block: 'nearest' }); })();</script>
    </aside>
    @endif

    <div class="{{ $withSidebar ? 'shell-main min-w-0' : '' }}">
      <div class="relative">
        {{-- Halaman dashboard ($banner): foto gedung kampus di belakang topbar & bagian atas kartu sambutan, memudar ke
             warna latar di bawahnya. Topbar transparan di atas foto, berlatar kembali setelah halaman digulir (shell.scrolled). --}}
        @if ($banner)
            <div class="page-banner" aria-hidden="true"></div>
        @endif
        {{-- Isi topbar memakai lebar & jarak samping yang sama dengan <main>, sehingga ikon menu dan menu profil sejajar
             dengan tepi kiri / kanan kartu di bawahnya (margin negatif mengimbangi ruang sentuh tombol) --}}
        <header @if ($banner) @scroll.window="trackScroll" :class="topbarClass" class="page-banner-header sticky top-0 z-20 border-b" @else class="sticky top-0 z-20 border-b border-rule bg-paper" @endif>
          <div class="mx-auto flex h-14 w-full max-w-[1440px] items-center gap-3 px-4 sm:px-6">
            @if ($withSidebar)
                {{-- Tampilkan / sembunyikan sidebar; pilihan diingat (cookie) dan berlaku di semua ukuran layar --}}
                <button type="button" @click="toggleSidebar" class="menu-toggle -ml-3 grid h-11 w-11 place-items-center rounded text-ink hover:bg-sunken"
                        aria-controls="sidebar" :aria-expanded="sidebarExpanded" :aria-label="sidebarLabel" :title="sidebarLabel"
                        aria-label="{{ $sidebarCollapsed ? 'Tampilkan menu' : 'Sembunyikan menu' }}">
                    {{-- Ikon garis bertingkat; saat kursor di atas tombol semua garis memanjang penuh --}}
                    <span class="menu-bars" aria-hidden="true">
                        <span></span><span></span><span></span>
                    </span>
                </button>
                <img src="{{ Vite::asset('resources/images/fri-horizontal.png') }}" alt="Fakultas Rekayasa Industri Telkom University" class="shell-mark h-8 w-auto md:hidden" width="143" height="32">
            @else
                <a href="{{ route($user->homeRoute()) }}" class="shrink-0" aria-label="{{ $user->isAdmin() && ! $workspace ? 'Kelola Periode' : 'Beranda Capstone' }}">
                    <img src="{{ Vite::asset('resources/images/fri-horizontal.png') }}" alt="Fakultas Rekayasa Industri Telkom University" class="h-8 w-auto" width="143" height="32">
                </a>
            @endif
            <div class="flex-1"></div>
            {{-- Topbar kanan: notifikasi, pemisah, menu profil --}}
            <div class="flex items-center gap-1 sm:gap-3">
                <x-notification-menu :user="$user" />
                <span class="h-7 w-px bg-rule" aria-hidden="true"></span>
                <div class="-mr-1 sm:-mr-3"><x-profile-menu :user="$user" /></div>
            </div>
          </div>
        </header>

        {{-- Jarak konten ke sidebar & topbar dibuat rapat (±24px) dan kartu boleh melebar sampai 1440px --}}
        <main id="konten" class="relative mx-auto w-full max-w-[1440px] px-4 pb-12 sm:px-6 {{ $banner ? 'pt-16 sm:pt-24' : 'pt-5' }}">
            <x-flash />
            {{ $slot }}
        </main>
      </div>
    </div>

    <x-confirm-dialog />
    <x-page-loader />
</body>
</html>
