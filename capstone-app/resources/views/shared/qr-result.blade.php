@php $team = $result['team'] ?? null; @endphp
<x-layouts.app title="QR Penilaian" section="QR penilaian">
    <div class="mx-auto max-w-lg pt-6">
        <div class="panel">
            <div class="panel-body">
                @if ($result['status'] === 'choose')
                    <h1 class="text-h2">Pilih tahap penilaian</h1>
                    <p class="mt-1 text-muted">{{ $team->name }}</p>
                    <ul class="mt-5 space-y-2">
                        @foreach ($components as $componentKey)
                            <li><a class="btn btn-secondary w-full justify-between" href="{{ route('dosen.assessments.edit', [$team, $componentKey, 'via' => 'qr']) }}">{{ config("capstone.components.$componentKey.label") }}<x-heroicon-o-arrow-right class="h-5 w-5" /></a></li>
                        @endforeach
                    </ul>
                @else
                    <x-heroicon-o-qr-code class="h-10 w-10 text-bad-ink" />
                    <h1 class="mt-3 text-h2">QR tidak dapat dipakai</h1>
                    <p class="mt-2">{{ $result['message'] }}</p>
                    @if ($team)
                        <p class="mt-3 text-sm text-muted">Tim terdeteksi: {{ $team->name }}</p>
                    @endif
                    <a href="{{ route(auth()->user()->homeRoute()) }}" class="btn btn-secondary mt-6">Kembali ke dashboard</a>
                @endif
            </div>
        </div>
    </div>
</x-layouts.app>
