{{-- Semua tim mahasiswa yang dibimbing/diuji dosen, urut aktivitas terbaru. --}}
<x-layouts.app title="Tim Mahasiswa" section="Dosen · Tim mahasiswa">
    <x-page-head title="Tim Mahasiswa" />
    <section class="panel overflow-hidden" aria-label="Daftar tim mahasiswa">
        @include('dosen._team-table')
    </section>
</x-layouts.app>
