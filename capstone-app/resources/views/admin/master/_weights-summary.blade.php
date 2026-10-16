{{-- Ringkasan validasi bobot F13/F14: total seluruh CLO = 100. --}}
<div class="panel mb-6">
    <div class="panel-head">
        <h2 class="panel-title">Validasi bobot</h2>
        <p class="text-sm">Total bobot CLO: <span class="font-mono font-medium {{ abs($summary['clo_total'] - 100) < 0.01 ? 'text-ok-ink' : 'text-bad-ink' }}">{{ rtrim(rtrim(number_format($summary['clo_total'], 2, ',', ''), '0'), ',') }}</span> / 100</p>
    </div>
    <div class="table-wrap">
        <table class="data-table">
            <thead><tr><th>Bentuk penilaian</th>@foreach ($components as $k => $c)<th class="text-right">{{ $c['label'] }}</th>@endforeach</tr></thead>
            <tbody>
                <tr><td>Bobot CLO</td>@foreach ($components as $k => $c)<td class="text-right font-mono">{{ $summary['components'][$k]['clo'] + 0 }}</td>@endforeach</tr>
                <tr><td>Σ bobot indikator</td>@foreach ($components as $k => $c)@php $ok = abs($summary['components'][$k]['clo'] - $summary['components'][$k]['indicator']) < 0.01; @endphp<td class="text-right font-mono {{ $ok ? '' : 'font-medium text-bad-ink' }}">{{ $summary['components'][$k]['indicator'] + 0 }}</td>@endforeach</tr>
            </tbody>
        </table>
    </div>
    <p class="border-t border-rule px-4 py-2 text-caption text-muted sm:px-5">Angka merah: jumlah bobot indikator belum sama dengan bobot CLO pada bentuk penilaian tersebut.</p>
</div>
