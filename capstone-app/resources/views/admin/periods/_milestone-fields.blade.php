{{-- Isian milestone. Jenis milestone: Dokumen (jenis dokumen + tipe file; masuk pengumpulan mahasiswa) atau
     Lainnya (tahapan seperti registrasi tim; hanya tampil di dashboard & timeline). Alpine "milestoneCategory". --}}
@php
    $uid = $m->id ?? 'new';
    $category = old('category', $m->exists ? $m->category() : 'dokumen');
@endphp
<form method="POST" action="{{ $action }}" x-data="milestoneCategory" data-category="{{ $category }}" class="grid gap-3 sm:grid-cols-6">
    @csrf
    @if ($method === 'PUT') @method('PUT') @endif
    <div class="sm:col-span-3">
        <label class="field-label" for="m-name-{{ $uid }}">Nama milestone</label>
        <input id="m-name-{{ $uid }}" name="name" class="input" value="{{ $m->name }}" required placeholder="Pengumpulan Proposal">
    </div>
    <div class="sm:col-span-2">
        <label class="field-label" for="m-cat-{{ $uid }}">Jenis milestone</label>
        <select id="m-cat-{{ $uid }}" name="category" class="input" @change="change">
            @foreach (config('capstone.milestone_categories') as $k => $l)
                <option value="{{ $k }}" @selected($category === $k)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <div>
        <label class="field-label" for="m-week-{{ $uid }}">Minggu</label>
        <input id="m-week-{{ $uid }}" type="number" min="1" max="20" name="week_no" class="input tabular" value="{{ $m->week_no }}">
    </div>

    {{-- Dokumen: jenis dokumen + tipe file --}}
    <div class="sm:col-span-2" x-show="isDocument">
        <label class="field-label" for="m-doc-{{ $uid }}">Jenis dokumen</label>
        <select id="m-doc-{{ $uid }}" name="document_type" class="input" :disabled="isOther">
            @foreach (config('capstone.document_types') as $k => $l)
                <option value="{{ $k }}" @selected($m->document_type === $k)>{{ $l }}</option>
            @endforeach
        </select>
    </div>
    <fieldset class="sm:col-span-4" x-show="isDocument">
        <legend class="field-label">Tipe file yang diizinkan</legend>
        <div class="flex flex-wrap gap-x-5 gap-y-1">
            @foreach (config('capstone.document_file_types') as $ext => $typeLabel)
                <label class="flex min-h-[40px] items-center gap-2 text-sm">
                    <input type="checkbox" class="checkbox" name="allowed_types[]" value="{{ $ext }}" @checked(in_array($ext, $m->fileTypes(), true)) :disabled="isOther">{{ $typeLabel }}
                </label>
            @endforeach
        </div>
    </fieldset>

    {{-- Lainnya: tahapan (menentukan fitur yang dibuka, mis. registrasi tim) --}}
    <div class="sm:col-span-6" x-show="isOther" x-cloak>
        <label class="field-label" for="m-kind-{{ $uid }}">Tahapan</label>
        <select id="m-kind-{{ $uid }}" name="kind" class="input sm:max-w-sm" :disabled="isDocument">
            @foreach (collect(config('capstone.milestone_kinds'))->except('dokumen') as $k => $l)
                <option value="{{ $k }}" @selected($m->kind === $k)>{{ $l }}</option>
            @endforeach
        </select>
        <p class="field-hint">Hanya tampil di dashboard &amp; timeline, sekaligus membuka tahap tersebut (mis. registrasi tim) sesuai tanggalnya.</p>
    </div>

    <div class="sm:col-span-3">
        <label class="field-label" for="m-start-{{ $uid }}">Dibuka</label>
        <input id="m-start-{{ $uid }}" type="datetime-local" name="starts_at" class="input" value="{{ $m->starts_at?->format('Y-m-d\TH:i') }}" required>
    </div>
    <div class="sm:col-span-3">
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
