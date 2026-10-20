{{-- Sidebar admin: dropdown ganti periode. Daftar periode dapat digulir; di bawahnya bagian tetap
     "Keluar dari Dashboard Periode ini" untuk kembali ke dashboard awal (Kelola Periode).
     current: periode yang sedang dibuka (Period::workspace()). --}}
@props(['current'])
@php $periods = \App\Models\Period::query()->latest('starts_on')->get(); @endphp
<div x-data="popover" class="relative mt-4 border-t border-rule pt-3" @click.outside="close" @keydown.escape.window="close">
    <button type="button" @click="toggle" :aria-expanded="expanded" aria-controls="ganti-periode" aria-haspopup="true"
            class="-mx-2 flex w-[calc(100%+1rem)] items-center gap-2 rounded-lg px-2 py-1.5 text-left hover:bg-sunken">
        <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold tracking-tight text-ink">Capstone Design &amp; Project</span>
            <span class="mt-0.5 block truncate text-caption text-muted"><span class="code">{{ $current->academic_year }}</span> · {{ ucfirst($current->semester) }}</span>
        </span>
        <x-heroicon-o-chevron-up-down class="h-4 w-4 shrink-0 text-muted" aria-hidden="true" />
        <span class="sr-only">Ganti periode</span>
    </button>

    <div id="ganti-periode" x-cloak x-show="open" class="absolute inset-x-0 top-full z-50 mt-2 overflow-hidden rounded-xl border border-rule bg-surface shadow-overlay">
        <p class="px-3 pb-1 pt-3 text-caption font-semibold text-muted">Ganti periode</p>
        <ul class="max-h-64 overflow-y-auto overscroll-contain px-1.5 pb-1.5">
            @foreach ($periods as $period)
                @php $isCurrent = $period->id === $current->id; @endphp
                <li>
                    <form method="POST" action="{{ route('admin.workspace.enter', $period) }}">
                        @csrf
                        <button class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm {{ $isCurrent ? 'bg-brand-tint text-brand-deep' : 'text-ink hover:bg-sunken' }}" @if ($isCurrent) aria-current="true" @endif>
                            <span class="min-w-0 flex-1">
                                <span class="block truncate font-medium">{{ $period->academic_year }} · {{ ucfirst($period->semester) }}</span>
                                <span class="block truncate text-caption {{ $isCurrent ? 'text-brand-deep' : 'text-muted' }}">{{ $period->name }}@if ($period->is_active) · aktif @endif</span>
                            </span>
                            @if ($isCurrent)<x-heroicon-o-check class="h-4 w-4 shrink-0" aria-hidden="true" />@endif
                        </button>
                    </form>
                </li>
            @endforeach
        </ul>
        {{-- Bagian tetap di bawah daftar --}}
        <form method="POST" action="{{ route('admin.workspace.leave') }}" class="border-t border-rule p-1.5">
            @csrf
            <button class="flex w-full items-center gap-2 rounded-lg px-2.5 py-2 text-left text-sm font-medium text-bad-ink hover:bg-bad-tint">
                <x-heroicon-o-arrow-left-on-rectangle class="h-4 w-4 shrink-0" aria-hidden="true" />
                Keluar dari Dashboard Periode ini
            </button>
        </form>
    </div>
</div>
