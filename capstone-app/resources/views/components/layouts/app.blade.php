@props(['title' => 'Capstone', 'section' => null])
@php
    $user = auth()->user();
    $nav = \App\Support\Navigation::for($user);
    $activePeriod = \App\Models\Period::active();
    $unread = $user->unreadNotifications()->count();
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Capstone' }} · Capstone FRI</title>
    <link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
    <link rel="apple-touch-icon" href="{{ asset('images/apple-touch-icon.png') }}">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:opsz,wght@14..32,400..700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body x-data="shell" class="min-h-screen">
    <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-surface focus:px-4 focus:py-2">Lewati ke konten</a>

    {{-- Overlay navigasi untuk layar sempit --}}
    <div x-cloak x-show="navOpen" @click="closeNav" class="fixed inset-0 z-30 bg-scrim md:hidden" aria-hidden="true"></div>

    <aside id="sidebar"
           :class="navClass"
           class="fixed inset-y-0 left-0 z-40 flex w-[17rem] -translate-x-full flex-col border-r border-rule bg-surface transition-transform duration-200 ease-out md:translate-x-0">
        <div class="px-5 pb-4 pt-5">
            <a href="{{ route($user->homeRoute()) }}" class="block" aria-label="Beranda Capstone">
                <img src="{{ asset('images/fri-horizontal.png') }}" alt="Fakultas Rekayasa Industri Telkom University" class="h-10 w-auto" width="178" height="40">
            </a>
            <div class="mt-4 flex items-baseline justify-between gap-2 border-t border-rule pt-3">
                <span class="text-sm font-semibold tracking-tight text-ink">Capstone Design &amp; Project</span>
            </div>
            <p class="mt-0.5 text-caption text-muted">
                @if ($activePeriod)
                    <span class="code">{{ $activePeriod->academic_year }}</span> · {{ ucfirst($activePeriod->semester) }}
                @else
                    Belum ada periode aktif
                @endif
            </p>
        </div>

        <nav class="flex-1 overflow-y-auto px-3 pb-4" aria-label="Menu utama">
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

        <div class="border-t border-rule px-4 py-3">
            <div class="flex items-center gap-3">
                <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-tint text-sm font-semibold text-brand-deep" aria-hidden="true">{{ $user->initials() }}</span>
                <div class="min-w-0 flex-1">
                    <p class="truncate text-sm font-medium">{{ $user->name }}</p>
                    <p class="truncate text-caption text-muted">{{ $user->roleLabel() }}@if ($user->prodi_code) · {{ $user->prodi_code }}@endif</p>
                </div>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit" class="grid h-10 w-10 place-items-center rounded text-muted hover:bg-sunken hover:text-ink" title="Keluar" aria-label="Keluar">
                        <x-heroicon-o-arrow-right-on-rectangle class="h-5 w-5" />
                    </button>
                </form>
            </div>
        </div>
    </aside>

    <div class="md:pl-[17rem]">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-rule bg-paper px-4 sm:px-8">
            <button type="button" @click="toggleNav" class="-ml-2 grid h-11 w-11 place-items-center rounded text-ink hover:bg-sunken md:hidden" aria-controls="sidebar" aria-label="Buka menu">
                <x-heroicon-o-bars-3 class="h-6 w-6" />
            </button>
            <img src="{{ asset('images/favicon.png') }}" alt="" class="h-7 w-7 md:hidden" width="28" height="28">
            <p class="min-w-0 flex-1 truncate text-sm text-muted">{{ $section ?? '' }}</p>
            <a href="{{ route('notifications.index') }}" class="relative grid h-11 w-11 place-items-center rounded text-ink hover:bg-sunken" aria-label="Notifikasi{{ $unread ? ", {$unread} belum dibaca" : '' }}">
                <x-heroicon-o-bell class="h-6 w-6" />
                @if ($unread)
                    <span class="absolute right-1.5 top-1.5 min-w-[1.125rem] rounded-full bg-bad-ink px-1 text-center text-[0.6875rem] font-semibold leading-[1.125rem] text-white tabular">{{ $unread > 9 ? '9+' : $unread }}</span>
                @endif
            </a>
        </header>

        <main id="konten" class="mx-auto w-full max-w-[1240px] px-4 pb-16 pt-6 sm:px-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>
</body>
</html>
