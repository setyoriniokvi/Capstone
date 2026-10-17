@php $uid = $m->id ?? 'new'; @endphp
<form method="POST" action="{{ $action }}" class="grid gap-3 sm:grid-cols-6">
    @csrf
    @if ($method === 'PUT') @method('PUT') @endif
    <div class="sm:col-span-3">
        <label class="field-label" for="m-name-{{ $uid }}">Nama</label>
        <input id="m-name-{{ $uid }}" name="name" class="input" value="{{ $m->name }}" required placeholder="Pengumpulan Proposal">
    </div>
    <div class="sm:col-span-2">
        <label class="field-label" for="m-kind-{{ $uid }}">Tahapan</label>
        <select id="m-kind-{{ $uid }}" name="kind" class="input">
            @foreach (config('capstone.milestone_kinds') as $k => $l)
                <option value="{{ $k }}" @selected($m->kind === $k)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="field-label" for="m-week-{{ $uid }}">Minggu</label>
        <input id="m-week-{{ $uid }}" type="number" min="1" max="20" name="week_no" class="input tabular" value="{{ $m->week_no }}">
    </div>
    <div class="sm:col-span-2">
        <label class="field-label" for="m-doc-{{ $uid }}">Jenis dokumen</label>
        <select id="m-doc-{{ $uid }}" name="document_type" class="input">
            <option value="">— bukan pengumpulan dokumen —</option>
            @foreach (config('capstone.document_types') as $k => $l)
                <option value="{{ $k }}" @selected($m->document_type === $k)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div class="sm:col-span-2">
        <label class="field-label" for="m-start-{{ $uid }}">Mulai</label>
        <input id="m-start-{{ $uid }}" type="datetime-local" name="starts_at" class="input" value="{{ $m->starts_at?->format('Y-m-d\TH:i') }}" required>
    </div>
    <div class="sm:col-span-2">
        <label class="field-label" for="m-due-{{ $uid }}">Tenggat</label>
        <input id="m-due-{{ $uid }}" type="datetime-local" name="due_at" class="input" value="{{ $m->due_at?->format('Y-m-d\TH:i') }}" required>
    </div>
    <div class="sm:col-span-5">
        <label class="field-label" for="m-desc-{{ $uid }}">Keterangan</label>
        <input id="m-desc-{{ $uid }}" name="description" class="input" value="{{ $m->description }}">
    </div>
    <div class="flex items-end sm:col-span-1">
        <button class="btn btn-primary w-full">{{ $submit }}</button>
    </div>
</form>
