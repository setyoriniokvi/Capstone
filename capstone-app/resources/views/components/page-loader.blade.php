{{-- Animasi loading saat berpindah halaman: logo FRI memantul seperti bola, dengan bayangan di bawahnya
     yang mengecil & memudar saat logo di atas dan melebar saat logo mendarat.
     Ditampilkan resources/js/app.js (klik tautan / kirim form) setelah jeda singkat agar tidak berkedip.
     Tautan unduhan (atribut download), tab baru, dan data-no-loader dikecualikan. --}}
<div id="page-loader" class="page-loader" role="status" aria-live="polite" aria-hidden="true">
    <div class="flex flex-col items-center" aria-hidden="true">
        <img src="{{ Vite::asset('resources/images/fri-horizontal.png') }}" alt="" class="page-loader__logo h-10 w-auto" width="178" height="40">
        <span class="page-loader__shadow"></span>
    </div>
    <span class="sr-only">Memuat halaman…</span>
</div>
