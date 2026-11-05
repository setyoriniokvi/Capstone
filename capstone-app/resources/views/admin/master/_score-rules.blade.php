{{-- F14 — Kartu informasi "Cara nilai dihitung" (di atas tabel Indikator): baris Rumus / Skor / Faktor Pemahaman dari
     berkas rubrik, hanya untuk dibaca (tidak dapat ditambah, diubah, atau dihapus). Kolom kanan menampilkan aturan faktor
     pemahaman yang dipakai sistem. Butuh $scoreRules. --}}
@php
    $schemes = config('capstone.schemes');
@endphp
<section class="panel mb-6 overflow-hidden" aria-labelledby="cara-nilai-judul">
    <div class="px-5 py-4 sm:px-6">
        <h2 id="cara-nilai-judul" class="text-lg font-semibold text-ink">Cara nilai dihitung</h2>
        <p class="mt-0.5 text-sm text-muted">Dipakai sistem: <span class="font-medium text-ink">{{ \App\Models\ScoreRule::formulaText() }}</span></p>
    </div>
    <x-scroll-pager class="border-t border-rule">
        <table class="data-table w-full min-w-[44rem] table-fixed [&_td]:align-top [&_td]:text-sm [&_td+td]:border-l [&_td]:border-rule [&_tbody_tr:hover_td]:bg-transparent">
            <colgroup><col class="w-[11rem]"><col><col class="w-[18rem]"></colgroup>
            <thead class="bg-brand-tint [&>tr>th]:border-0 [&>tr>th]:py-3 [&>tr>th]:text-sm [&>tr>th]:font-semibold [&>tr>th]:text-brand-deep">
                <tr><th>Komponen</th><th>Keterangan</th><th>Aturan yang dipakai sistem</th></tr>
            </thead>
            <tbody>
                @foreach ($scoreRules as $rule)
                    <tr>
                        <td class="font-semibold text-ink">{{ $rule->label }}</td>
                        <td class="whitespace-pre-line text-ink">{{ $rule->description }}</td>
                        <td>
                            @if ($rule->key === 'oral_factor')
                                <p class="text-ink">Dari Rubrik Lisan untuk:
                                    <span class="font-medium">{{ collect($rule->settings['schemes'] ?? [])->map(fn ($s) => $schemes[$s]['label'] ?? $s)->implode(', ') ?: 'tidak ada skema' }}</span></p>
                                <p class="mt-1 text-muted">Tanpa verifikasi lisan: <span class="tabular font-medium text-ink">{{ number_format((float) ($rule->settings['default_factor'] ?? 1), 2, ',', '') }}</span></p>
                            @else
                                <span class="text-muted">—</span>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </x-scroll-pager>
</section>
