@php
    $rows = $data['rows'];
    $valid = collect($rows)->where('errors', [])->count();
    $columns = \App\Services\Import\ImportService::TEMPLATES[$type];
@endphp
<x-layouts.app :title="'Pratinjau impor '.$label" section="Admin · Impor">
    <x-page-head :title="'Pratinjau impor '.strtolower($label)" :lede="$data['file'].' · '.count($rows).' baris. Baris bermasalah tidak akan disimpan.'">
        <x-slot:actions>
            <a href="{{ url()->previous() }}" class="btn btn-ghost">Batal</a>
            <form method="POST" action="{{ route('admin.imports.commit', $type) }}">
                @csrf
                <input type="hidden" name="token" value="{{ $data['token'] }}">
                <button class="btn btn-primary" @disabled($valid === 0) data-once>Simpan {{ $valid }} baris valid</button>
            </form>
        </x-slot:actions>
    </x-page-head>

    <div class="panel">
        <div class="panel-head">
            <p class="text-sm"><span class="tabular font-medium text-ok-ink">{{ $valid }}</span> valid · <span class="tabular font-medium text-bad-ink">{{ count($rows) - $valid }}</span> bermasalah</p>
        </div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Baris</th><th>Status</th>@foreach ($columns as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="{{ $row['errors'] ? 'bg-bad-tint' : '' }}">
                            <td class="tabular">{{ $row['line'] }}</td>
                            <td class="min-w-[14rem]">
                                @if ($row['errors'])
                                    <ul class="space-y-0.5 text-bad-ink">@foreach ($row['errors'] as $e)<li>{{ $e }}</li>@endforeach</ul>
                                @else
                                    <x-badge status="diterima" :label="$row['action'] === 'perbarui' ? 'Perbarui' : 'Baru'" />
                                @endif
                            </td>
                            @foreach ($columns as $c)
                                <td class="whitespace-nowrap">{{ $row['data'][$c] ?? '' }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</x-layouts.app>
