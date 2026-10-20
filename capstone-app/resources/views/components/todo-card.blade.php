{{-- Kartu "To Do" dashboard (mahasiswa & dosen): daftar tindakan urut prioritas + proses yang menunggu pihak lain.
     Dropdown kanan atas menyaring tenggat 7 / 30 hari ke depan langsung di browser (Alpine "todoFilter");
     tindakan tanpa tenggat dan yang sudah terlambat selalu tampil.
     actions: list of [title, detail, url, tone (bad|warn|neutral), icon (nama komponen ikon menu asal), due (Carbon|null), urgency (late|soon|null)]
     waiting: list of string --}}
@use('App\Support\Format')
@props(['actions', 'waiting' => []])
@php
    $urgencyClass = ['late' => 'text-bad-ink', 'soon' => 'text-warn-ink'];
    // Warna kotak ikon menurut prioritas: merah = terlambat/ditolak, kuning = perlu segera, abu = biasa
    $toneIcon = ['bad' => 'bg-bad-tint text-bad-ink', 'warn' => 'bg-warn-tint text-warn-ink', 'neutral' => 'bg-sunken text-muted'];
    $uid = 'todo-'.\Illuminate\Support\Str::random(6);
@endphp
<section x-data="todoFilter" data-range="7" {{ $attributes->merge(['class' => 'panel']) }} aria-labelledby="{{ $uid }}">
    <div class="panel-head">
        <h2 id="{{ $uid }}" class="panel-title">To Do</h2>
        <label class="sr-only" for="{{ $uid }}-range">Rentang tenggat</label>
        <select id="{{ $uid }}-range" class="input min-h-[40px] w-auto py-1.5 pl-3 pr-9 text-sm" @change="change">
            <option value="7" selected>7 hari ke depan</option>
            <option value="30">30 hari ke depan</option>
        </select>
    </div>
    @if (collect($actions)->isNotEmpty())
        <ul class="divide-y divide-rule">
            @foreach ($actions as $action)
                @php $days = $action['due'] ? (int) floor(now()->diffInDays($action['due'], false)) : ''; @endphp
                <li data-due-days="{{ $days }}">
                    <a href="{{ $action['url'] }}" class="group flex items-start gap-4 px-4 py-3 hover:bg-sunken focus-visible:bg-sunken sm:px-6">
                        <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg {{ $toneIcon[$action['tone']] ?? $toneIcon['neutral'] }}" aria-hidden="true">
                            <x-dynamic-component :component="$action['icon'] ?? 'heroicon-o-check-circle'" class="h-5 w-5" />
                        </span>
                        <span class="min-w-0 flex-1">
                            <span class="block pt-1.5 font-medium leading-snug text-ink">{{ $action['title'] }}</span>
                            @if ($action['detail'])<span class="mt-0.5 block text-sm text-muted">{{ $action['detail'] }}</span>@endif
                        </span>
                        @if ($action['due'])
                            <span class="hidden shrink-0 text-right text-sm xs:block">
                                <span class="block font-medium {{ $urgencyClass[$action['urgency']] ?? 'text-ink' }}">{{ Format::dueIn($action['due']) }}</span>
                                <span class="block text-caption text-muted">{{ $action['due']->translatedFormat('j M, H:i') }}</span>
                            </span>
                        @endif
                        <x-heroicon-o-chevron-right class="mt-1 h-4 w-4 shrink-0 text-faint group-hover:text-ink" aria-hidden="true" />
                    </a>
                    @if ($action['due'])
                        {{-- Layar sempit: tenggat di bawah judul agar tidak berdesakan --}}
                        <p class="-mt-2 px-4 pb-3 pl-[4.25rem] text-caption xs:hidden {{ $urgencyClass[$action['urgency']] ?? 'text-muted' }}">{{ Format::dueIn($action['due']) }} · {{ $action['due']->translatedFormat('j M, H:i') }}</p>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
    <p x-show="empty" @if (collect($actions)->isNotEmpty()) x-cloak @endif class="px-4 py-4 text-sm text-muted sm:px-6">Tidak ada yang perlu dilakukan dalam rentang ini.</p>
    @foreach ($waiting as $note)
        <p class="flex items-center gap-2 border-t border-rule px-4 py-3 text-sm text-muted sm:px-6"><x-heroicon-o-clock class="h-4 w-4 shrink-0" />{{ $note }}</p>
    @endforeach
</section>
