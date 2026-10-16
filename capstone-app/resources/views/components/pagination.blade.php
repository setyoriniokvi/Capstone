@if ($paginator->hasPages())
    <nav class="flex items-center justify-between gap-3 border-t border-rule px-4 py-3 text-sm sm:px-5" aria-label="Navigasi halaman">
        <p class="text-muted tabular">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }} dari {{ $paginator->total() }}</p>
        <div class="flex gap-2">
            @if ($paginator->onFirstPage())
                <span class="btn btn-secondary btn-sm" aria-disabled="true">Sebelumnya</span>
            @else
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->previousPageUrl() }}" rel="prev">Sebelumnya</a>
            @endif
            @if ($paginator->hasMorePages())
                <a class="btn btn-secondary btn-sm" href="{{ $paginator->nextPageUrl() }}" rel="next">Berikutnya</a>
            @else
                <span class="btn btn-secondary btn-sm" aria-disabled="true">Berikutnya</span>
            @endif
        </div>
    </nav>
@endif
