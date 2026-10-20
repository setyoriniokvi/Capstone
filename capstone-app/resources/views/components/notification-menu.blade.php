{{-- F48 — notifikasi sebagai popover di tombol lonceng (bukan halaman terpisah).
     Titik berwarna menurut isi notifikasi bila belum dibaca; notifikasi yang sudah dibaca bertitik kosong.
     Klik notifikasi menandainya dibaca lalu membuka halaman terkait. Hanya 5 terbaru agar kartu pas tanpa scroll. --}}
@props(['user'])
@php
    $unread = $user->unreadNotifications()->count();
    $items = $user->notifications()->limit(5)->get();
    $tone = function ($n) {
        $text = \Illuminate\Support\Str::lower(($n->data['title'] ?? '').' '.($n->data['body'] ?? ''));

        return match (true) {
            $n->read_at !== null => 'border-2 border-rule-strong',
            \Illuminate\Support\Str::contains($text, ['gagal', 'ditolak', 'terlewat', 'terlambat']) => 'bg-bad',
            \Illuminate\Support\Str::contains($text, ['revisi', 'menunggu', 'tenggat']) => 'bg-warn',
            default => 'bg-brand',
        };
    };
@endphp
<div x-data="popover" class="relative" @click.outside="close" @keydown.escape.window="close">
    <button type="button" @click="toggle" :aria-expanded="expanded" aria-controls="menu-notifikasi"
            class="relative grid h-11 w-11 place-items-center rounded text-ink hover:bg-sunken" aria-label="Notifikasi{{ $unread ? ", {$unread} belum dibaca" : '' }}">
        <x-heroicon-o-bell class="h-6 w-6" />
        @if ($unread)
            <span class="absolute right-1.5 top-1.5 min-w-[1.125rem] rounded-full bg-bad-ink px-1 text-center text-[0.6875rem] font-semibold leading-[1.125rem] text-white tabular">{{ $unread > 9 ? '9+' : $unread }}</span>
        @endif
    </button>

    <div id="menu-notifikasi" x-cloak x-show="open"
         class="absolute right-0 top-full z-30 mt-2 w-[min(24rem,calc(100vw-2rem))] overflow-hidden rounded-2xl border border-rule bg-surface shadow-overlay">
        <div class="mx-5 flex items-center justify-between gap-3 border-b border-rule py-3">
            <h2 class="text-base font-semibold text-ink">Notifikasi</h2>
            <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
                <button class="link -mr-1 inline-flex min-h-[44px] items-center px-1 text-sm disabled:cursor-default disabled:text-faint disabled:no-underline" @disabled(! $unread)>Tandai semua dibaca</button>
            </form>
        </div>

        @if ($items->isEmpty())
            <p class="px-5 py-8 text-center text-sm text-muted">Belum ada notifikasi.</p>
        @else
            <ul class="divide-y divide-rule px-3 pb-2">
                @foreach ($items as $n)
                    @php
                        $title = $n->data['title'] ?? 'Notifikasi';
                        $body = $n->data['body'] ?? '';
                    @endphp
                    <li>
                        <a href="{{ route('notifications.open', $n->id) }}" class="flex gap-3 rounded-lg px-2 py-3 hover:bg-sunken focus-visible:bg-sunken">
                            <span class="mt-[0.4375rem] h-2 w-2 shrink-0 rounded-full {{ $tone($n) }}" aria-hidden="true"></span>
                            <span class="min-w-0 flex-1">
                                <span class="block text-sm text-ink">{{ $title }}{{ $body ? '. '.$body : '' }}@unless ($n->read_at)<span class="sr-only"> (belum dibaca)</span>@endunless</span>
                                <time class="mt-0.5 block text-caption text-muted tabular" datetime="{{ $n->created_at->toIso8601String() }}">{{ $n->created_at->translatedFormat($n->created_at->gt(now()->subWeek()) ? 'j M, H.i' : 'j M') }}</time>
                            </span>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
