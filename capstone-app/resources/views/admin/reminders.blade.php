{{-- F50 / F52 / F55 — Reminder Otomatis. Kartu "Pengiriman Reminder": tabel aturan (Jenis deadline · Dikirim · Media · Pesan ·
     Status · Ubah / Nonaktifkan / Hapus) dengan tombol di kanan atas: Tambah (formulir modal, wajib semua), Kirim Manual Reminder
     (konfirmasi → WhatsApp & email ke semua dosen yang belum lengkap menilai), Uji Reminder (modal: nomor WA / email opsional,
     minimal salah satu; pesan wajib). Reminder aktif dikirim otomatis oleh scheduler saat ambang waktunya tercapai.
     Di bawahnya: Riwayat pengiriman. --}}
<x-layouts.app title="Reminder Otomatis">
    <x-page-head title="Reminder Otomatis" />

    @if ($appSender && ! $fonnteReady)
        <p class="mb-6 rounded-lg border border-warn-line bg-warn-tint px-4 py-3 text-sm text-warn-ink">
            <span class="font-medium">Token Fonnte belum diisi.</span> Isi <span class="code">FONNTE_TOKEN</span> di file <span class="code">.env</span> agar reminder WhatsApp dapat dikirim.
        </p>
    @elseif (! $appSender && ! $automationReady)
        <p class="mb-6 rounded-lg border border-warn-line bg-warn-tint px-4 py-3 text-sm text-warn-ink">
            <span class="font-medium">AUTOMATION_TOKEN belum diatur.</span> Endpoint untuk n8n menolak semua permintaan sampai token diisi di file <span class="code">.env</span>.
        </p>
    @endif

    <section data-sort-scope class="panel mb-6" aria-labelledby="judul-reminder">
        <div class="flex flex-col gap-3 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <h2 id="judul-reminder" class="text-lg font-semibold text-ink">Pengiriman Reminder</h2>
            <div class="flex flex-wrap gap-2">
                {{-- Tambah reminder otomatis --}}
                <div x-data="registrationForm" data-open="{{ old('_form') === 'tambah' ? 'true' : 'false' }}">
                    <button type="button" class="btn btn-primary" @click="open"><x-heroicon-o-plus class="h-5 w-5" />Tambah</button>
                    <x-modal>
                        <x-slot:heading>Tambah reminder otomatis</x-slot:heading>
                        <form method="POST" action="{{ route('admin.reminders.store') }}" class="space-y-5">
                            @csrf
                            @include('admin._reminder-rule-fields', ['rule' => null, 'form' => 'tambah'])
                            <div class="flex justify-end gap-3 border-t border-rule pt-4">
                                <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                <button class="btn btn-primary" data-once>Simpan</button>
                            </div>
                        </form>
                    </x-modal>
                </div>

                {{-- Kirim manual ke semua dosen yang belum lengkap menilai (konfirmasi dulu) --}}
                <form method="POST" action="{{ route('admin.reminders.manual') }}"
                      data-confirm="Kirim reminder manual (WhatsApp & email) ke semua dosen yang penilaiannya belum lengkap sekarang?" data-confirm-label="Kirim" data-confirm-tone="positive">
                    @csrf
                    <button class="btn btn-secondary"><x-heroicon-o-paper-airplane class="h-5 w-5" />Kirim Manual Reminder</button>
                </form>

                {{-- Uji reminder: WA dan/atau email (minimal salah satu), pesan wajib --}}
                <div x-data="registrationForm" data-open="{{ old('_form') === 'uji' ? 'true' : 'false' }}">
                    <button type="button" class="btn btn-secondary" @click="open"><x-heroicon-o-beaker class="h-5 w-5" />Uji Reminder</button>
                    <x-modal width="sm:max-w-lg">
                        <x-slot:heading>Uji reminder</x-slot:heading>
                        <form method="POST" action="{{ route('admin.reminders.test') }}" class="space-y-4" x-data="reminderTest">
                            @csrf
                            <input type="hidden" name="_form" value="uji">
                            <div>
                                <label class="field-label" for="test_phone">Nomor WhatsApp uji tujuan <span class="font-normal text-muted">(opsional)</span></label>
                                <input id="test_phone" x-ref="phone" @input="check" name="test_phone" value="{{ old('test_phone') }}" inputmode="tel" maxlength="20" class="input" placeholder="081234567890">
                                @error('test_phone')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="field-label" for="test_email">Alamat email uji tujuan <span class="font-normal text-muted">(opsional)</span></label>
                                <input id="test_email" x-ref="email" @input="check" type="email" name="test_email" value="{{ old('test_email') }}" maxlength="255" class="input" placeholder="nama@gmail.com">
                                @error('test_email')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div>
                                <label class="field-label" for="test_message">Pesan<span class="text-bad-ink" aria-hidden="true"> *</span></label>
                                <textarea id="test_message" name="test_message" rows="5" maxlength="2000" class="input" required>{{ old('test_message') }}</textarea>
                                @error('test_message')<p class="field-error">{{ $message }}</p>@enderror
                            </div>
                            <div class="flex justify-end gap-3 border-t border-rule pt-4">
                                <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                <button class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50" :disabled="! ready" :title="ready ? '' : 'Isi nomor WhatsApp atau alamat email uji'" data-once>Kirim pesan uji</button>
                            </div>
                        </form>
                    </x-modal>
                </div>
            </div>
        </div>

        @if ($rules->isEmpty())
            <div class="border-t border-rule"><x-empty title="Belum ada reminder otomatis" icon="clock">Tambah reminder agar dosen diingatkan sebelum deadline. Kirim Manual Reminder tetap dapat dipakai.</x-empty></div>
        @else
            <div class="flex justify-end border-t border-rule px-5 py-3 sm:px-6">
                <x-sort-by id="urut-aturan-reminder" default="jenis" :options="['jenis' => 'Jenis deadline', 'terbaru' => 'Tanggal', 'kategori' => 'Kategori (Media)']" />
            </div>
            <x-scroll-pager>
                <table class="data-table border-t border-rule">
                    <thead><tr><th>Jenis deadline</th><th>Dikirim</th><th>Media</th><th>Pesan</th><th>Status</th><th><span class="sr-only">Aksi</span></th></tr></thead>
                    <tbody data-sort-list>
                        @foreach ($rules as $rule)
                            <tr x-data="registrationForm" data-open="{{ old('_form') === 'ubah-'.$rule->id ? 'true' : 'false' }}" class="[&>td]:align-top"
                                data-sort-item data-sort-jenis="{{ $rule->deadlineLabel() }}" data-sort-terbaru="{{ $rule->created_at?->timestamp }}" data-sort-kategori="{{ $rule->channelLabel() }}">
                                <td class="min-w-[12rem] font-medium text-ink">{{ $rule->deadlineLabel() }}</td>
                                <td class="whitespace-nowrap tabular">{{ $rule->hours_before }} jam sebelum deadline</td>
                                <td class="whitespace-nowrap">{{ $rule->channelLabel() }}</td>
                                <td class="min-w-[16rem] max-w-[26rem] text-sm text-muted"><p class="line-clamp-3 whitespace-pre-line" title="{{ $rule->message }}">{{ $rule->message ?: '—' }}</p></td>
                                <td>@if ($rule->is_active)<x-badge status="aktif" />@else<x-badge status="nonaktif" />@endif</td>
                                <td>
                                    <div class="flex items-center justify-end gap-1 whitespace-nowrap">
                                        <button type="button" class="btn btn-secondary btn-sm" @click="open"><x-heroicon-o-pencil-square class="h-4 w-4" />Ubah</button>
                                        <form method="POST" action="{{ route('admin.reminders.toggle', $rule) }}">
                                            @csrf @method('PATCH')
                                            <button class="btn btn-ghost btn-sm">{{ $rule->is_active ? 'Nonaktifkan' : 'Aktifkan' }}</button>
                                        </form>
                                        <form method="POST" action="{{ route('admin.reminders.destroy', $rule) }}" data-confirm="Hapus reminder {{ $rule->deadlineLabel() }} ({{ $rule->hours_before }} jam sebelum deadline)?" data-confirm-label="Hapus">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-ghost btn-sm text-bad-ink"><x-heroicon-o-trash class="h-4 w-4" />Hapus</button>
                                        </form>
                                    </div>
                                    <x-modal>
                                        <x-slot:heading>Ubah reminder otomatis</x-slot:heading>
                                        <form method="POST" action="{{ route('admin.reminders.update', $rule) }}" class="space-y-5">
                                            @csrf @method('PUT')
                                            @include('admin._reminder-rule-fields', ['rule' => $rule, 'form' => 'ubah-'.$rule->id])
                                            <div class="flex justify-end gap-3 border-t border-rule pt-4">
                                                <button type="button" class="btn btn-ghost" @click="close">Batal</button>
                                                <button class="btn btn-primary" data-once>Simpan</button>
                                            </div>
                                        </form>
                                    </x-modal>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-scroll-pager>
        @endif
    </section>

    <section class="panel">
        <div class="panel-head">
            <h2 class="panel-title">Riwayat pengiriman</h2>
            {{-- Cari (Enter) · Sort · Status · Kanal sebaris --}}
            <x-filter-bar :action="route('admin.reminders.index')">
                <div class="min-w-[12rem] flex-1"><label class="sr-only" for="cari-pengiriman">Cari</label>
                    <input id="cari-pengiriman" type="search" name="q" value="{{ request('q') }}" class="input" placeholder="Nama dosen, penerima, pekerjaan"></div>
                <x-sort-by mode="server" id="urut-pengiriman" default="waktu" dir="desc" :options="['waktu' => 'Waktu', 'nama' => 'Dosen', 'kategori' => 'Kategori (Status)']" />
                <div><label class="sr-only" for="status">Status</label>
                    <select id="status" name="status" class="input"><option value="">Semua status</option>@foreach (['antre', 'diproses', 'terkirim', 'gagal', 'dibatalkan'] as $s)<option value="{{ $s }}" @selected(request('status') === $s)>{{ ucfirst($s) }}</option>@endforeach</select></div>
                <div><label class="sr-only" for="kanal">Kanal</label>
                    <select id="kanal" name="kanal" class="input"><option value="">Semua kanal</option><option value="email" @selected(request('kanal') === 'email')>Email</option><option value="whatsapp" @selected(request('kanal') === 'whatsapp')>WhatsApp</option></select></div>
            </x-filter-bar>
        </div>
        @if ($dispatches->isEmpty())
            <x-empty :title="request()->filled('q') || request()->filled('status') || request()->filled('kanal') ? 'Tidak ada pengiriman yang cocok' : 'Belum ada pengiriman'" icon="paper-airplane" />
        @else
            <x-scroll-pager>
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
            </x-scroll-pager>
            {{ $dispatches->links() }}
        @endif
    </section>
</x-layouts.app>
