<x-layouts.app title="Notifikasi Otomatis" section="Admin · Notifikasi otomatis">
    <x-page-head title="Notifikasi otomatis" lede="Sistem memantau pekerjaan dosen yang belum selesai. Saat ambang waktu tercapai, pengingat dikirim melalui workflow n8n ke email dan WhatsApp dosen, lalu hasilnya dicatat di sini." />

    @unless ($automationReady)
        <p class="mb-6 rounded-lg border border-warn-line bg-warn-tint px-4 py-3 text-sm text-warn-ink">
            <span class="font-medium">AUTOMATION_TOKEN belum diatur.</span> Endpoint untuk n8n menolak semua permintaan sampai token diisi di file <span class="code">.env</span>.
        </p>
    @endunless

    <section class="panel mb-6">
        <div class="panel-head"><h2 class="panel-title">Ambang waktu pengingat</h2></div>
        <div class="table-wrap">
            <table class="data-table">
                <thead><tr><th>Jenis pekerjaan</th><th>Dikirim</th><th>Kanal</th><th>Aktif</th><th></th></tr></thead>
                <tbody>
                    @forelse ($rules as $rule)
                        <tr x-data="disclosure">
                            <td class="font-medium">{{ config('capstone.reminder_job_types.'.$rule->job_type) }}</td>
                            <td class="tabular">{{ $rule->hours_before }} jam sebelum tenggat</td>
                            <td>{{ collect($rule->channels)->map(fn ($c) => $c === 'email' ? 'Email' : 'WhatsApp')->implode(', ') }}</td>
                            <td>@if ($rule->is_active)<x-badge status="aktif" />@else<x-badge status="nonaktif" />@endif</td>
                            <td class="text-right">
                                <form method="POST" action="{{ route('admin.reminders.update', $rule) }}" class="inline">
                                    @csrf @method('PUT')
                                    <input type="hidden" name="job_type" value="{{ $rule->job_type }}">
                                    <input type="hidden" name="hours_before" value="{{ $rule->hours_before }}">
                                    @foreach ($rule->channels as $c)<input type="hidden" name="channels[]" value="{{ $c }}">@endforeach
                                    <input type="hidden" name="is_active" value="{{ $rule->is_active ? 0 : 1 }}">
                                    <button class="btn btn-ghost btn-sm">{{ $rule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                </form>
                                <form method="POST" action="{{ route('admin.reminders.destroy', $rule) }}" data-confirm="Hapus aturan reminder ini?" class="inline">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-bad-ink">Hapus</button></form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5"><x-empty title="Belum ada ambang pengingat" icon="clock">Tanpa aturan, pengingat otomatis tidak berjalan. Reminder manual tetap dapat dikirim.</x-empty></td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <form method="POST" action="{{ route('admin.reminders.store') }}" class="grid gap-3 border-t border-rule bg-sunken px-4 py-4 sm:grid-cols-[minmax(0,1fr)_10rem_auto_auto] sm:items-end sm:px-6">
            @csrf
            <x-field label="Jenis pekerjaan" name="job_type">
                <select id="job_type" name="job_type" class="input">@foreach (config('capstone.reminder_job_types') as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select>
            </x-field>
            <x-field label="Jam sebelum tenggat" name="hours_before"><input id="hours_before" type="number" min="1" max="720" name="hours_before" class="input tabular" value="{{ old('hours_before', 48) }}" required></x-field>
            <fieldset class="flex items-center gap-4">
                <legend class="sr-only">Kanal</legend>
                <label class="flex min-h-[44px] items-center gap-2"><input type="checkbox" class="checkbox" name="channels[]" value="email" checked>Email</label>
                <label class="flex min-h-[44px] items-center gap-2"><input type="checkbox" class="checkbox" name="channels[]" value="whatsapp" checked>WA</label>
                <input type="hidden" name="is_active" value="1">
            </fieldset>
            <button class="btn btn-primary">Tambah</button>
        </form>
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Riwayat pengiriman</h2>
            <x-filter-bar :action="route('admin.reminders.index')">
                <div><label class="sr-only" for="status">Status</label>
                    <select id="status" name="status" class="input"><option value="">Semua status</option>@foreach (['antre', 'diproses', 'terkirim', 'gagal', 'dibatalkan'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
                <div><label class="sr-only" for="kanal">Kanal</label>
                    <select id="kanal" name="kanal" class="input"><option value="">Semua kanal</option><option value="email" @selected(request('kanal') === 'email')>Email</option><option value="whatsapp" @selected(request('kanal') === 'whatsapp')>WhatsApp</option></select></div>
            </x-filter-bar>
        </div>
        @if ($dispatches->isEmpty())
            <x-empty title="Belum ada pengiriman" icon="paper-airplane" />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Waktu</th><th>Dosen</th><th>Pekerjaan</th><th>Kanal</th><th>Pemicu</th><th>Status</th><th>Percobaan</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($dispatches as $d)
                            <tr>
                                <td class="whitespace-nowrap text-sm">{{ $d->created_at->translatedFormat('j M H:i') }}@if ($d->sent_at)<p class="text-caption text-muted">terkirim {{ $d->sent_at->translatedFormat('j M H:i') }}</p>@endif</td>
                                <td><p class="font-medium">{{ $d->lecturer?->name }}</p><p class="code text-muted">{{ $d->recipient ?: '—' }}</p></td>
                                <td class="max-w-[16rem] text-sm">{{ $d->payload['job_title'] ?? config('capstone.reminder_job_types.'.$d->job_type) }}<p class="text-caption text-muted">{{ implode(', ', array_slice($d->payload['items'] ?? [], 0, 3)) }}</p></td>
                                <td>{{ $d->channel === 'email' ? 'Email' : 'WhatsApp' }}</td>
                                <td class="capitalize">{{ $d->trigger }}</td>
                                <td><x-badge :status="$d->status" />@if ($d->last_error)<p class="mt-1 max-w-[14rem] text-caption text-muted">{{ $d->last_error }}</p>@endif</td>
                                <td class="tabular">{{ $d->attempts }}</td>
                                <td>@if (in_array($d->status, ['gagal', 'dibatalkan']) && $d->recipient)<form method="POST" action="{{ route('admin.reminders.retry', $d) }}">@csrf<button class="btn btn-ghost btn-sm">Kirim ulang</button></form>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            {{ $dispatches->links() }}
        @endif
    </section>
</x-layouts.app>
