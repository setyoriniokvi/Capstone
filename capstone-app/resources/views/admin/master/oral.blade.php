<x-layouts.app title="Rubrik Lisan" section="Admin · Master penilaian">
    <x-page-head title="Rubrik penilaian lisan" lede="Level 1–5 dengan faktor pemahaman 0,25–1,00. Faktor dikalikan ke nilai indikator berskema Lisan atau Dokumen + Lisan." />
    <div class="space-y-4">
        @foreach ($levels as $level)
            <form method="POST" action="{{ route('admin.oral.update', $level) }}" class="panel grid gap-3 px-4 py-4 sm:grid-cols-[5rem_7rem_minmax(0,12rem)_minmax(0,1fr)_auto] sm:items-start sm:px-6">
                @csrf @method('PUT')
                <div><label class="field-label" for="lv-{{ $level->id }}">Level</label><input id="lv-{{ $level->id }}" type="number" min="1" max="5" name="level" class="input tabular" value="{{ $level->level }}" required></div>
                <div><label class="field-label" for="f-{{ $level->id }}">Faktor</label><input id="f-{{ $level->id }}" type="number" step="0.01" min="0.25" max="1" name="factor" class="input tabular" value="{{ $level->factor }}" required></div>
                <div><label class="field-label" for="lb-{{ $level->id }}">Sebutan</label><input id="lb-{{ $level->id }}" name="label" class="input" value="{{ $level->label }}" required></div>
                <div><label class="field-label" for="b-{{ $level->id }}">Perilaku yang teramati</label><textarea id="b-{{ $level->id }}" name="behavior" rows="2" class="input" required>{{ $level->behavior }}</textarea></div>
                <div class="flex gap-1 sm:pt-7"><button class="btn btn-secondary btn-sm">Simpan</button></div>
            </form>
        @endforeach
        <form method="POST" action="{{ route('admin.oral.store') }}" class="panel grid gap-3 border-dashed px-4 py-4 sm:grid-cols-[5rem_7rem_minmax(0,12rem)_minmax(0,1fr)_auto] sm:items-start sm:px-6">
            @csrf
            <div><label class="field-label" for="lv-new">Level</label><input id="lv-new" type="number" min="1" max="5" name="level" class="input tabular" required></div>
            <div><label class="field-label" for="f-new">Faktor</label><input id="f-new" type="number" step="0.01" min="0.25" max="1" name="factor" class="input tabular" required></div>
            <div><label class="field-label" for="lb-new">Sebutan</label><input id="lb-new" name="label" class="input" required></div>
            <div><label class="field-label" for="b-new">Perilaku yang teramati</label><textarea id="b-new" name="behavior" rows="2" class="input" required></textarea></div>
            <div class="sm:pt-7"><button class="btn btn-primary btn-sm">Tambah</button></div>
        </form>
        @foreach ($levels as $level)
            <form method="POST" action="{{ route('admin.oral.destroy', $level) }}" data-confirm="Hapus level {{ $level->level }} dari rubrik lisan?" class="inline">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-bad-ink">Hapus level {{ $level->level }}</button></form>
        @endforeach
    </div>
</x-layouts.app>
