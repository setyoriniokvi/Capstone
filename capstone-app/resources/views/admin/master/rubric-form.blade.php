@php $r = $indicator->rubric; @endphp
<x-layouts.app :title="'Rubrik '.$indicator->code" section="Admin · Master penilaian">
    <x-page-head :title="'Rubrik '.$indicator->code" :lede="$indicator->clo->code.' · '.$indicator->name.' · bobot '.($indicator->weight + 0)" />
    <form method="POST" action="{{ route('admin.rubrics.update', $indicator) }}" class="panel max-w-4xl">
        @csrf @method('PUT')
        <div class="space-y-4 panel-body">
            <x-field label="Poin penilaian" name="assessment_points" required><textarea id="assessment_points" name="assessment_points" rows="3" class="input" required>{{ old('assessment_points', $r?->assessment_points) }}</textarea></x-field>
            @foreach (config('capstone.score_levels') as $level => $meta)
                <x-field :label="$level.' · '.$meta['label'].' ('.$meta['value'].')'" :name="'level_'.$level" required>
                    <textarea id="level_{{ $level }}" name="level_{{ $level }}" rows="2" class="input" required>{{ old('level_'.$level, $r?->{'level_'.$level} ?? ($level === 0 ? 'Tidak mengumpulkan.' : '')) }}</textarea>
                </x-field>
            @endforeach
            <x-field label="Contoh pemenuhan tingkat 5" name="example_level_5"><textarea id="example_level_5" name="example_level_5" rows="3" class="input">{{ old('example_level_5', $r?->example_level_5) }}</textarea></x-field>
        </div>
        <div class="flex justify-between gap-2 border-t border-rule px-4 py-3 sm:px-6">
            <a href="{{ route('admin.rubrics.index') }}" class="btn btn-ghost">Kembali</a>
            <button class="btn btn-primary" data-once>Simpan rubrik</button>
        </div>
    </form>
</x-layouts.app>
