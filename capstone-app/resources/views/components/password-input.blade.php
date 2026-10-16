{{-- Field password dengan tombol mata untuk menampilkan / menyembunyikan isi. --}}
<div class="relative" x-data="passwordToggle">
    <input {{ $attributes->merge(['type' => 'password', 'class' => 'input pr-12']) }} x-bind:type="inputType">
    <button type="button" class="absolute inset-y-0 right-0 flex w-12 items-center justify-center text-muted hover:text-ink focus-visible:text-ink"
            x-on:click="toggle" x-bind:aria-label="label" x-bind:aria-pressed="pressed" aria-label="Tampilkan password" aria-pressed="false">
        {{-- mata terbuka --}}
        <svg x-show="hidden" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>
        </svg>
        {{-- mata dicoret --}}
        <svg x-show="visible" x-cloak class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <path d="M10.6 5.1A10.6 10.6 0 0 1 12 5c6.5 0 10 7 10 7a17.6 17.6 0 0 1-2.6 3.6M6.6 6.6C3.7 8.5 2 12 2 12s3.5 7 10 7c1.9 0 3.6-.6 5-1.5"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/><path d="m3 3 18 18"/>
        </svg>
    </button>
</div>
