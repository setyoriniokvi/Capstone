{{-- Instruksi admin untuk jenis topik (teks); tidak tampil bila kosong. --}}
@props(['text'])
@if (filled($text))
    <section {{ $attributes->merge(['class' => 'mb-6 flex gap-3 rounded-lg border border-brand-tint bg-brand-wash px-4 py-3 text-sm text-ink']) }} aria-label="Instruksi">
        <x-heroicon-o-information-circle class="mt-0.5 h-5 w-5 shrink-0 text-brand" />
        <div class="min-w-0"><p class="font-semibold">Instruksi</p><p class="mt-0.5 whitespace-pre-line break-words">{{ $text }}</p></div>
    </section>
@endif
