{{-- Kata kunci terlarang untuk jenis topik pada tab aktif (topik mandiri / topik dosen). Butuh $tab, $keywords. --}}
@php $label = \App\Models\ForbiddenKeyword::SOURCES[$tab]; @endphp
<section class="panel mb-6" aria-labelledby="kata-kunci-judul">
    <div class="panel-head">
        <h2 id="kata-kunci-judul" class="panel-title">Kata kunci terlarang {{ $label }}</h2>
        <p class="text-sm text-muted">{{ ucfirst($label) }} yang mengandung kata kunci ini ditandai saat diajukan.</p>
    </div>
    <div class="panel-body">
        <form method="POST" action="{{ route('admin.keywords.store') }}" class="flex gap-2">
            @csrf
            <input type="hidden" name="source" value="{{ $tab }}">
            <label for="keyword" class="sr-only">Kata kunci terlarang {{ $label }}</label>
            <input id="keyword" name="keyword" class="input min-w-0 flex-1" placeholder="mis. sistem informasi akademik" required maxlength="100">
            <button class="btn btn-secondary shrink-0"><x-heroicon-o-plus class="h-5 w-5" /> Tambah</button>
        </form>
        <ul class="mt-3 flex flex-wrap gap-1.5">
            @forelse ($keywords as $k)
                <li class="flex items-center gap-1 rounded bg-sunken py-0.5 pl-2 text-sm">
                    {{ $k->keyword }}
                    <form method="POST" action="{{ route('admin.keywords.destroy', $k) }}" data-confirm="Hapus kata kunci “{{ $k->keyword }}”?">@csrf @method('DELETE')<button class="grid h-7 w-7 place-items-center text-muted hover:text-bad-ink" aria-label="Hapus {{ $k->keyword }}"><x-heroicon-o-x-mark class="h-4 w-4" /></button></form>
                </li>
            @empty
                <li class="text-sm text-muted">Belum ada kata kunci.</li>
            @endforelse
        </ul>
    </div>
</section>
