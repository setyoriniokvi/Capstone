{{-- Tombol "Detail tim" + modalnya; terbuka otomatis bila ?tim= menunjuk tim ini atau "Ubah tim" gagal validasi. Butuh $team, $openTeam; $buttonClass opsional (mis. w-full). --}}
<div x-data="registrationForm" data-open="{{ $openTeam === $team->id || ($errors->any() && old('_team') === (string) $team->id) ? 'true' : 'false' }}">
    <button type="button" class="btn btn-secondary btn-sm {{ $buttonClass ?? '' }}" @click="open" :aria-expanded="expanded">Detail tim<span class="sr-only"> {{ $team->name }}</span></button>
    @include('admin.teams._detail')
</div>
