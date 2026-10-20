<x-layouts.app title="Resume CLO" section="Admin · Master penilaian">
    <x-page-head title="Resume penilaian per CLO" lede="Kode, deskripsi, skema penilaian, dan pembagian bobot CLO ke setiap bentuk penilaian. Bobot CLO dihitung dari jumlah pembagiannya." />
    @include('admin.master._weights-summary')

    <div class="space-y-4">
        @foreach ($clos as $clo)
            <section x-data="disclosure" class="panel">
                <div class="flex flex-wrap items-start gap-4 px-4 py-4 sm:px-6">
                    <span class="code w-14 shrink-0 pt-0.5 text-base font-medium text-brand-deep">{{ $clo->code }}</span>
                    <div class="min-w-0 flex-1">
                        <p>{{ $clo->description }}</p>
                        <p class="mt-1 text-sm text-muted">{{ $clo->assessment_scheme ?: 'Skema penilaian belum diisi' }}</p>
                        <div class="mt-2 flex flex-wrap gap-1.5">
                            @foreach ($clo->componentWeights as $w)
                                <span class="rounded bg-sunken px-2 py-0.5 text-caption">{{ config('capstone.components.'.$w->component.'.label') }} <span class="tabular font-medium">{{ $w->weight + 0 }}</span></span>
                            @endforeach
                        </div>
                    </div>
                    <p class="tabular text-2xl font-medium">{{ $clo->weight + 0 }}</p>
                    <button type="button" class="btn btn-ghost btn-sm" @click="toggle">Ubah</button>
                </div>
                <x-modal width="sm:max-w-3xl">
                    <x-slot:heading>Ubah {{ $clo->code }}</x-slot:heading>
                    @include('admin.master._clo-form', ['clo' => $clo, 'action' => route('admin.clos.update', $clo), 'method' => 'PUT'])
                    <form method="POST" action="{{ route('admin.clos.destroy', $clo) }}" data-confirm="Hapus CLO {{ $clo->code }}?" class="mt-4 border-t border-rule pt-4">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-bad-ink">Hapus {{ $clo->code }}</button></form>
                </x-modal>
            </section>
        @endforeach
        <section class="panel">
            <div class="panel-head"><h2 class="panel-title">Tambah CLO</h2></div>
            <div class="panel-body">@include('admin.master._clo-form', ['clo' => new \App\Models\Clo, 'action' => route('admin.clos.store'), 'method' => 'POST'])</div>
        </section>
    </div>
</x-layouts.app>
