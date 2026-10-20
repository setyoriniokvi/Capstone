{{-- Formulir sebagai jendela modal di atas halaman: layar belakang digelapkan (overlay), konten utama tidak bergeser.
     Memakai state komponen Alpine induk: "expanded" (tampil) dan "close()" — mis. registrationForm, disclosure,
     applyForm, topicForm. Tutup dengan tombol ×, tombol Batal, Escape, atau klik di area gelap.
     heading (slot) : judul modal; boleh berisi elemen ber-x-text untuk judul dinamis.
     width          : lebar maksimum di layar ≥ sm (bawaan sm:max-w-2xl). --}}
@props(['heading', 'width' => 'sm:max-w-2xl'])
@php $titleId = 'modal-'.\Illuminate\Support\Str::random(8); @endphp
<div x-cloak x-show="expanded" class="dialog-scrim" @click.self="close" @keydown.escape.window="close"
     x-transition:enter="motion-fade" x-transition:enter-start="opacity-0"
     x-transition:leave="motion-fade" x-transition:leave-end="opacity-0">
    <div {{ $attributes->merge(['class' => "dialog-sheet motion-rise flex max-h-[92vh] flex-col {$width}"]) }} role="dialog" aria-modal="true" aria-labelledby="{{ $titleId }}">
        <div class="flex shrink-0 items-center justify-between gap-3 border-b border-rule px-5 py-4 sm:px-6">
            <h2 id="{{ $titleId }}" class="min-w-0 text-lg font-semibold text-ink">{{ $heading }}</h2>
            <button type="button" class="-mr-2 grid h-10 w-10 shrink-0 place-items-center rounded-full text-muted hover:bg-sunken hover:text-ink" @click="close" aria-label="Tutup">
                <x-heroicon-o-x-mark class="h-5 w-5" />
            </button>
        </div>
        <div class="min-h-0 overflow-y-auto overscroll-contain px-5 py-5 sm:px-6">
            {{ $slot }}
        </div>
    </div>
</div>
