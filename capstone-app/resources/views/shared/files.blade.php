<x-layouts.app title="File Pendukung">
    <x-page-head title="File pendukung" lede="Panduan Capstone, slide sosialisasi, dan materi pendukung dari admin." />
    @forelse ($groups as $category => $files)
        <section data-sort-scope class="panel mb-4">
            <div class="panel-head"><h2 class="panel-title">{{ $category }}</h2>
                <x-sort-by :id="'urut-file-'.$loop->iteration" default="judul" :options="['judul' => 'Judul', 'terbaru' => 'Tanggal']" /></div>
            <ul data-sort-list class="divide-y divide-rule">
                @foreach ($files as $file)
                    <li data-sort-item data-sort-judul="{{ $file->title }}" data-sort-terbaru="{{ $file->created_at?->timestamp }}" class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                        <x-heroicon-o-document-text class="h-6 w-6 shrink-0 text-muted" />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ $file->title }}</p>
                            @if ($file->description)<p class="text-sm text-muted">{{ $file->description }}</p>@endif
                            <p class="flex flex-wrap gap-x-3 text-caption text-muted"><span>{{ strtoupper(pathinfo($file->original_name, PATHINFO_EXTENSION)) }}</span><span>{{ \App\Support\Format::bytes($file->size) }}</span><span>{{ $file->created_at->translatedFormat('j M Y') }}</span></p>
                        </div>
                        @if ($file->isPreviewable())
                            <a href="{{ route('files.show', $file) }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><x-heroicon-o-eye class="h-4 w-4" />Lihat<span class="sr-only"> {{ $file->title }}</span></a>
                        @else
                            <button type="button" class="btn btn-secondary btn-sm disabled:cursor-not-allowed disabled:opacity-50" disabled title="Pratinjau hanya untuk PDF & gambar - silakan unduh"><x-heroicon-o-eye class="h-4 w-4" />Lihat</button>
                        @endif
                        <a download href="{{ route('files.download', $file) }}" class="btn btn-secondary btn-sm"><x-heroicon-o-arrow-down-tray class="h-4 w-4" />Unduh</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="panel"><x-empty title="Belum ada file pendukung" icon="folder" /></div>
    @endforelse
</x-layouts.app>
