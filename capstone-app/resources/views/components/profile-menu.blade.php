{{-- Menu profil di topbar kanan: avatar + nama + peran; dropdown berisi identitas akun dan tombol keluar.
     Layar sempit hanya menampilkan avatar. --}}
@props(['user'])
<div x-data="popover" class="relative" @click.outside="close" @keydown.escape.window="close">
    <button type="button" @click="toggle" :aria-expanded="expanded" aria-controls="menu-profil" aria-label="Menu akun {{ $user->name }}"
            class="flex min-h-[44px] items-center gap-2.5 rounded-full py-1 pl-1 pr-1 hover:bg-sunken sm:pr-3">
        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-brand-tint text-sm font-semibold text-brand-deep ring-2 ring-surface" aria-hidden="true">{{ $user->initials() }}</span>
        <span class="hidden min-w-0 max-w-[11rem] text-left leading-tight sm:block">
            <span class="block truncate text-sm font-semibold text-ink">{{ $user->name }}</span>
            <span class="block truncate text-caption font-medium text-brand">{{ $user->roleLabel() }}</span>
        </span>
        <x-heroicon-o-chevron-down class="hidden h-4 w-4 shrink-0 text-muted transition-transform sm:block" ::class="chevronClass" aria-hidden="true" />
    </button>

    <div id="menu-profil" x-cloak x-show="open"
         class="absolute right-0 top-full z-30 mt-2 w-[min(20rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-rule bg-surface shadow-overlay">
        <div class="flex items-center gap-3 px-5 py-4">
            <span class="grid h-12 w-12 shrink-0 place-items-center rounded-full bg-brand-tint text-base font-semibold text-brand-deep" aria-hidden="true">{{ $user->initials() }}</span>
            <div class="min-w-0">
                <p class="truncate font-semibold text-ink">{{ $user->name }}</p>
                <p class="truncate text-sm text-muted" title="{{ $user->email }}">{{ $user->email }}</p>
                @if ($user->nim)
                    <p class="code truncate text-caption text-muted">{{ $user->nim }}</p>
                @elseif ($user->prodi_code)
                    <p class="truncate text-caption text-muted">{{ $user->roleLabel() }} · {{ $user->prodi_code }}</p>
                @endif
            </div>
        </div>
        <form method="POST" action="{{ route('logout') }}" class="border-t border-rule p-2">
            @csrf
            <button type="submit" class="flex min-h-[44px] w-full items-center gap-3 rounded px-3 text-sm font-medium text-bad-ink hover:bg-bad-tint">
                <x-heroicon-o-arrow-right-on-rectangle class="h-5 w-5" aria-hidden="true" />
                Keluar dari Akun
            </button>
        </form>
    </div>
</div>
