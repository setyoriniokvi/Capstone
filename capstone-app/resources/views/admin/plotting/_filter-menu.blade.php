{{-- Tombol "Filter" + panel kotak centang per kelompok (di dalam Alpine "tableFilter"). Dalam satu kelompok centang
     digabung "atau", antar kelompok "dan". Butuh $panelId dan $groups: [nama dataset baris => [judul, [nilai => label]]]. --}}
<div class="relative" @click.outside="closeMenu" @keydown.escape="closeMenu">
    <button type="button" class="btn btn-secondary min-h-[44px]" @click="toggleMenu" :aria-expanded="menuOpen" aria-controls="{{ $panelId }}">
        <x-heroicon-o-funnel class="h-5 w-5" /> Filter
        <span x-show="hasActive" x-cloak class="rounded-full bg-brand px-2 text-caption font-semibold leading-5 text-white tabular" x-text="active"></span>
        <x-heroicon-o-chevron-down class="h-4 w-4" />
    </button>
    <div id="{{ $panelId }}" x-show="menuOpen" x-cloak x-transition.opacity
         class="absolute right-0 top-full z-20 mt-2 w-[calc(100vw-2.5rem)] rounded-xl border border-rule bg-surface p-4 shadow-overlay {{ count($groups) > 3 ? 'max-w-[56rem] sm:w-[56rem]' : 'max-w-[44rem] sm:w-[44rem]' }}">
        <div class="grid gap-5 {{ count($groups) > 3 ? 'sm:grid-cols-2 lg:grid-cols-4' : 'sm:grid-cols-3' }}">
            @foreach ($groups as $group => [$title, $choices])
                <fieldset class="min-w-0">
                    <legend class="mb-2 text-caption font-semibold uppercase tracking-wide text-muted">{{ $title }}</legend>
                    <div class="space-y-1">
                        @forelse ($choices as $value => $text)
                            <label class="flex min-h-[36px] cursor-pointer items-center gap-2.5 rounded-md px-2 text-sm text-ink hover:bg-sunken">
                                <input type="checkbox" class="checkbox" value="{{ $value }}" data-filter-group="{{ $group }}" @change="pick">
                                <span>{{ $text }}</span>
                            </label>
                        @empty
                            <p class="px-2 text-sm text-muted">Belum ada pilihan</p>
                        @endforelse
                    </div>
                </fieldset>
            @endforeach
        </div>
        <div class="mt-4 flex justify-end gap-2 border-t border-rule pt-3">
            <button type="button" class="btn btn-ghost btn-sm disabled:cursor-not-allowed disabled:opacity-50" @click="reset" :disabled="! hasActive">Reset filter</button>
            <button type="button" class="btn btn-primary btn-sm" @click="closeMenu">Selesai</button>
        </div>
    </div>
</div>
