{{-- Isian reminder otomatis (Tambah / Ubah), wajib semua: jenis deadline · jam sebelum deadline · media · pesan.
     $rule = null untuk Tambah; $form = kunci formulir ("tambah" / "ubah-{id}") agar nilai lama & error hanya tampil di formulir yang dikirim. --}}
@php
    $mine = old('_form') === $form;
    $value = fn ($key, $default) => $mine ? old($key, $default) : $default;
    $channels = $value('channels', $rule?->channels ?? []);
    $star = '<span class="text-bad-ink" aria-hidden="true"> *</span>';
@endphp
<input type="hidden" name="_form" value="{{ $form }}">
<div class="grid gap-4 sm:grid-cols-2">
    <div class="sm:col-span-2">
        <label class="field-label" for="deadline-{{ $form }}">Jenis deadline{!! $star !!}</label>
        <select id="deadline-{{ $form }}" name="deadline" class="input" required>
            <option value="" disabled @selected(! $value('deadline', $rule?->deadlineKey()))>Pilih jenis deadline</option>
            @foreach ($deadlineOptions as $key => $label)
                <option value="{{ $key }}" @selected($value('deadline', $rule?->deadlineKey()) === $key)>{{ $label }}</option>
            @endforeach
        </select>
        @if ($mine) @error('deadline')<p class="field-error">{{ $message }}</p>@enderror @endif
    </div>
    <div>
        <label class="field-label" for="hours-{{ $form }}">Dikirim (jam sebelum deadline){!! $star !!}</label>
        <input id="hours-{{ $form }}" type="number" name="hours_before" min="1" max="720" class="input tabular" value="{{ $value('hours_before', $rule?->hours_before) }}" placeholder="mis. 24" required>
        @if ($mine) @error('hours_before')<p class="field-error">{{ $message }}</p>@enderror @endif
    </div>
    <fieldset>
        <legend class="field-label">Media{!! $star !!}</legend>
        <div class="flex flex-wrap gap-x-5">
            <label class="flex min-h-[44px] items-center gap-2"><input type="checkbox" class="checkbox" name="channels[]" value="whatsapp" @checked(in_array('whatsapp', $channels, true))>WhatsApp</label>
            <label class="flex min-h-[44px] items-center gap-2"><input type="checkbox" class="checkbox" name="channels[]" value="email" @checked(in_array('email', $channels, true))>Email</label>
        </div>
        @if ($mine) @error('channels')<p class="field-error">{{ $message }}</p>@enderror @endif
    </fieldset>
    <div class="sm:col-span-2">
        <label class="field-label" for="message-{{ $form }}">Pesan{!! $star !!}</label>
        <textarea id="message-{{ $form }}" name="message" rows="7" maxlength="2000" class="input" required placeholder="Tulis pesan reminder untuk dosen">{{ $value('message', $rule?->message) }}</textarea>
        <p class="field-hint">Pesan dikirim apa adanya. Opsional, sisipkan variabel berikut agar terisi otomatis per dosen:
            @foreach (\App\Models\ReminderRule::PLACEHOLDERS as $code => $meaning)<span class="code">{{ $code }}</span> ({{ $meaning }}){{ $loop->last ? '.' : ', ' }}@endforeach
        </p>
        @if ($mine) @error('message')<p class="field-error">{{ $message }}</p>@enderror @endif
    </div>
</div>
