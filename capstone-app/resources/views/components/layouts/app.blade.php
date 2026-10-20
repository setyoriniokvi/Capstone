@props(['title' => 'Capstone', 'section' => null])
@php
    $user = auth()->user();
    $nav = \App\Support\Navigation::for($user);
    $activePeriod = \App\Models\Period::active();
    // Admin bekerja di dalam periode yang dipilih (dropdown); di luar itu = dashboard awal Kelola Periode.
    $workspace = $user->isAdmin() ? \App\Models\Period::workspace() : null;
    // Dashboard awal admin (Kelola Periode) tanpa sidebar: menunya hanya satu.
    $withSidebar = ! ($user->isAdmin() && ! $workspace);
@endphp
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ $title ?? 'Capstone' }} · Capstone FRI</title>
    <x-head-assets />
</head>
<body x-data="shell" class="min-h-screen">
    <a href="#konten" class="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded focus:bg-surface focus:px-4 focus:py-2">Lewati ke konten</a>

    @if ($withSidebar)
    {{-- Overlay navigasi untuk layar sempit --}}
    <div x-cloak x-show="navOpen" @click="closeNav"
         x-transition:enter="motion-fade" x-transition:enter-start="opacity-0"
         x-transition:leave="motion-fade" x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-30 bg-scrim md:hidden" aria-hidden="true"></div>

    <aside id="sidebar"
           :class="navClass"
           class="fixed inset-y-0 left-0 z-40 flex w-[17rem] -translate-x-full flex-col border-r border-rule bg-surface transition-transform duration-200 ease-out md:translate-x-0">
        <div class="px-5 pb-4 pt-5">
            <a href="{{ route($user->homeRoute()) }}" class="block" aria-label="Beranda Capstone">
                <img src="{{ Vite::asset('resources/images/fri-horizontal.png') }}" alt="Fakultas Rekayasa Industri Telkom University" class="h-10 w-auto" width="178" height="40">
            </a>
            @if ($workspace)
                <x-admin-period-switcher :current="$workspace" />
            @else
                <div class="mt-4 flex items-baseline justify-between gap-2 border-t border-rule pt-3">
                    <span class="text-sm font-semibold tracking-tight text-ink">Capstone Design &amp; Project</span>
                </div>
                <p class="mt-0.5 text-caption text-muted">
                    @if ($user->isAdmin())
                        Kelola periode
                    @elseif ($activePeriod)
                        <span class="code">{{ $activePeriod->academic_year }}</span> · {{ ucfirst($activePeriod->semester) }}
                    @else
                        Belum ada periode aktif
                    @endif
                </p>
            @endif
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
    </aside>
    @endif

    <div class="{{ $withSidebar ? 'md:pl-[17rem]' : '' }}">
        <header class="sticky top-0 z-20 flex h-14 items-center gap-3 border-b border-rule bg-paper px-4 sm:px-8">
            @if ($withSidebar)
                <button type="button" @click="toggleNav" class="-ml-2 grid h-11 w-11 place-items-center rounded text-ink hover:bg-sunken md:hidden" aria-controls="sidebar" aria-label="Buka menu">
                    <x-heroicon-o-bars-3 class="h-6 w-6" />
                </button>
                <img src="{{ Vite::asset('resources/images/telu-mark.png') }}" alt="" class="h-7 w-7 md:hidden" width="28" height="28">
            @else
                <a href="{{ route($user->homeRoute()) }}" class="shrink-0" aria-label="Kelola Periode">
                    <img src="{{ Vite::asset('resources/images/fri-horizontal.png') }}" alt="Fakultas Rekayasa Industri Telkom University" class="h-8 w-auto" width="143" height="32">
                </a>
            @endif
            <p class="min-w-0 flex-1 truncate text-sm text-muted">{{ $section ?? '' }}</p>
            {{-- Topbar kanan: notifikasi, pemisah, menu profil --}}
            <div class="flex items-center gap-1 sm:gap-3">
                <x-notification-menu :user="$user" />
                <span class="h-7 w-px bg-rule" aria-hidden="true"></span>
                <x-profile-menu :user="$user" />
            </div>
        </header>

        <main id="konten" class="mx-auto w-full max-w-[1240px] px-4 pb-16 pt-6 sm:px-8">
            <x-flash />
            {{ $slot }}
        </main>
    </div>

    <x-confirm-dialog />
</body>
</html>
