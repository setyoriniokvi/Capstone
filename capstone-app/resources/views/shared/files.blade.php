<x-layouts.app title="File Pendukung" section="File pendukung">
    <x-page-head title="File pendukung" lede="Panduan Capstone, slide sosialisasi, dan materi pendukung dari admin." />
    @forelse ($groups as $category => $files)
        <section class="panel mb-4">
            <div class="panel-head"><h2 class="panel-title">{{ $category }}</h2></div>
            <ul class="divide-y divide-rule">
                @foreach ($files as $file)
                    <li class="flex flex-wrap items-center gap-3 px-4 py-3 sm:px-6">
                        <x-heroicon-o-document-text class="h-6 w-6 shrink-0 text-muted" />
                        <div class="min-w-0 flex-1">
                            <p class="font-medium">{{ $file->title }}</p>
                            @if ($file->description)<p class="text-sm text-muted">{{ $file->description }}</p>@endif
                            <p class="text-caption text-muted">{{ strtoupper(pathinfo($file->original_name, PATHINFO_EXTENSION)) }} · {{ \App\Support\Format::bytes($file->size) }} · {{ $file->created_at->translatedFormat('j M Y') }}</p>
                        </div>
                        <a href="{{ route('files.download', $file) }}" class="btn btn-secondary btn-sm"><x-heroicon-o-arrow-down-tray class="h-4 w-4" />Unduh</a>
                    </li>
                @endforeach
            </ul>
        </section>
    @empty
        <div class="panel"><x-empty title="Belum ada file pendukung" icon="folder" /></div>
    @endforelse
</x-layouts.app>
