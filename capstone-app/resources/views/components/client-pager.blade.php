{{-- Footer pembagian halaman di browser untuk tabel ber-Alpine "bulkApproval" (akar ber-data-page-size): kiri "Tampilkan [10] data",
     kanan ‹ 1 2 3 ›. Cari, filter & pilih tetap berjalan tanpa memuat ulang halaman.
     id    : id unik dropdown jumlah data   label : nama navigasi halaman (aksesibilitas)
     options / default : pilihan jumlah data dan pilihan awal (samakan default dengan data-page-size akar) --}}
@props(['id', 'label' => 'Halaman data', 'options' => [10, 25, 50, 100], 'default' => 10])
<div {{ $attributes->merge(['class' => 'flex flex-wrap items-center justify-between gap-3 border-t border-rule px-5 py-3 sm:px-6']) }}>
    <div class="flex items-center gap-2 text-sm text-muted">
        <label for="{{ $id }}">Tampilkan</label>
        <select id="{{ $id }}" class="input min-h-[36px] w-auto py-1" @change="setPerPage">
            @foreach ($options as $n)<option value="{{ $n }}" @selected($n === $default)>{{ $n }}</option>@endforeach
            <option value="0">Semua</option>
        </select>
        <span>data</span>
    </div>
    <nav class="flex items-center gap-1" aria-label="{{ $label }}">
        <button type="button" class="grid h-9 w-9 place-items-center rounded-md text-ink hover:bg-sunken disabled:cursor-not-allowed disabled:text-faint disabled:hover:bg-transparent" @click="prevPage" :disabled="onFirstPage" aria-label="Halaman sebelumnya"><x-heroicon-o-chevron-left class="h-4 w-4" /></button>
        <template x-for="number in pageList" :key="number">
            <button type="button" class="grid h-9 min-w-[2.25rem] place-items-center rounded-md px-2 text-sm tabular" :data-page="number" @click="pickPage"
                    :class="number === page ? 'bg-brand font-semibold text-white' : 'text-ink hover:bg-sunken'" :aria-current="number === page ? 'page' : null" x-text="number"></button>
        </template>
        <button type="button" class="grid h-9 w-9 place-items-center rounded-md text-ink hover:bg-sunken disabled:cursor-not-allowed disabled:text-faint disabled:hover:bg-transparent" @click="nextPage" :disabled="onLastPage" aria-label="Halaman berikutnya"><x-heroicon-o-chevron-right class="h-4 w-4" /></button>
    </nav>
</div>
