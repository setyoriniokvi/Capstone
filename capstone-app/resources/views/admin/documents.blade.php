<x-layouts.app title="Sinkronisasi Dokumen">
    <x-page-head title="Sinkronisasi Google Drive" lede="Setiap unggahan mahasiswa disalin otomatis ke folder Drive miliknya. Status hanya berhasil setelah Drive mengonfirmasi." />

    @unless ($driveEnabled)
        <p class="mb-6 rounded-lg border border-warn-line bg-warn-tint px-4 py-3 text-sm text-warn-ink">
            <span class="font-medium">Integrasi Google Drive nonaktif.</span> Dokumen tersimpan di server aplikasi. Atur <span class="code">GOOGLE_DRIVE_ENABLED=true</span> beserta kredensial (service account atau OAuth) untuk mengaktifkan sinkronisasi.
        </p>
    @endunless

    {{-- Koneksi Google Drive: mode OAuth = akun Google yang dihubungkan admin (Hubungkan / Hubungkan ulang / Putuskan);
         mode service account = file JSON di server. --}}
    <section class="panel mb-6" aria-labelledby="drive-koneksi">
        <div class="flex flex-col gap-4 px-5 py-4 sm:flex-row sm:items-center sm:justify-between sm:px-6">
            <div class="min-w-0">
                <h2 id="drive-koneksi" class="font-semibold text-ink">Koneksi Google Drive</h2>
                @if ($driveAuth === 'oauth')
                    @php $connection = $oauth->status(); @endphp
                    @if (! $oauth->configured())
                        <p class="mt-0.5 text-sm text-bad-ink">Mode OAuth aktif, tetapi <span class="code">GOOGLE_DRIVE_CLIENT_ID</span> / <span class="code">GOOGLE_DRIVE_CLIENT_SECRET</span> belum diisi di .env.</p>
                    @elseif ($connection)
                        <p class="mt-0.5 text-sm text-muted">Mode OAuth · terhubung dengan <span class="font-medium text-ink">{{ $connection['email'] ?? 'akun Google' }}</span>@if ($connection['connected_at']) sejak {{ $connection['connected_at']->translatedFormat('j M Y, H:i') }}@endif. Dokumen tersimpan di Drive akun ini.</p>
                    @else
                        <p class="mt-0.5 text-sm text-warn-ink">Mode OAuth · belum ada akun Google yang terhubung.</p>
                    @endif
                    <p class="mt-0.5 text-caption text-muted">Redirect URI: <span class="code">{{ $oauth->redirectUri() }}</span></p>
                @else
                    <p class="mt-0.5 text-sm text-muted">Mode service account · kredensial dari file JSON di server (<span class="code">GOOGLE_DRIVE_CREDENTIALS</span>).</p>
                @endif
            </div>
            @if ($driveAuth === 'oauth')
                <div class="flex shrink-0 flex-wrap gap-2">
                    @if ($connection)
                        <form method="POST" action="{{ route('admin.drive.disconnect') }}" data-confirm="Putuskan Google Drive {{ $connection['email'] }}? Sinkronisasi berikutnya gagal sampai akun dihubungkan lagi." data-confirm-label="Putuskan">
                            @csrf @method('DELETE')
                            <button class="btn btn-ghost text-bad-ink">Putuskan</button>
                        </form>
                    @endif
                    @if ($oauth->configured())
                        <a href="{{ route('admin.drive.connect') }}" class="btn {{ $connection ? 'btn-secondary' : 'btn-primary' }}"><x-heroicon-o-link class="h-5 w-5" />{{ $connection ? 'Hubungkan ulang' : 'Hubungkan akun Google' }}</a>
                    @else
                        <button type="button" class="btn btn-primary disabled:cursor-not-allowed disabled:opacity-50" disabled title="Isi client ID & secret di .env"><x-heroicon-o-link class="h-5 w-5" />Hubungkan akun Google</button>
                    @endif
                </div>
            @endif
        </div>
    </section>

    <nav class="mb-4 flex flex-wrap gap-2">
        @foreach (['' => 'Semua', 'menunggu' => 'Menunggu', 'proses' => 'Proses', 'berhasil' => 'Berhasil', 'gagal' => 'Gagal', 'dilewati' => 'Dilewati'] as $k => $l)
            <a href="{{ route('admin.documents.index', array_filter(['status' => $k])) }}" class="btn btn-sm {{ (string) request('status') === $k ? 'btn-primary' : 'btn-secondary' }}">{{ $l }}@if ($k !== '') <span class="tabular">{{ $counts[$k] ?? 0 }}</span>@endif</a>
        @endforeach
    </nav>

    <div class="panel">
        @if ($versions->isEmpty())
            <x-empty title="Belum ada dokumen" icon="cloud-arrow-up" />
        @else
            <x-scroll-pager>
                <table class="data-table">
                    <thead><tr><th>Diunggah</th><th>Pemilik</th><th>Tim</th><th>Dokumen</th><th>Revisi</th><th>Aksi Drive</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($versions as $v)
                            @php $doc = $v->document; @endphp
                            <tr>
                                <td class="whitespace-nowrap text-sm">{{ $v->created_at->translatedFormat('j M H:i') }}</td>
                                <td><p class="font-medium">{{ $doc->owner->name }}</p><p class="text-caption text-muted">{{ $doc->owner_email }}</p></td>
                                <td>{{ $doc->team->name }}</td>
                                <td class="text-sm"><a download class="link" href="{{ route('documents.download', $v) }}">{{ $doc->typeLabel() }}</a><p class="text-caption text-muted">{{ $v->stored_name }}</p></td>
                                <td class="tabular">r{{ $v->revision_no }}</td>
                                <td class="text-sm">{{ $v->sync_action === 'update' ? 'Perbarui file' : 'Buat file' }}@if ($doc->drive_file_id)<p class="code text-caption text-muted">{{ \Illuminate\Support\Str::limit($doc->drive_file_id, 14) }}</p>@endif</td>
                                <td><x-badge :status="$v->sync_status" />@if ($v->sync_error)<p class="mt-1 max-w-[16rem] text-caption text-muted">{{ $v->sync_error }}</p>@endif<p class="text-caption text-muted">{{ $v->sync_attempts }} percobaan</p></td>
                                <td class="text-right">
                                    @if ($driveEnabled && $v->sync_status === 'gagal')
                                        <form method="POST" action="{{ route('admin.documents.retry', $v) }}">@csrf<button class="btn btn-ghost btn-sm">Coba ulang</button></form>
                                    @endif
                                    @if ($doc->state === 'trash')
                                        <form method="POST" action="{{ route('admin.documents.destroy', $doc) }}" data-confirm="Hapus permanen dokumen ini? Tindakan ini tidak dapat dibatalkan." data-confirm-label="Hapus permanen">@csrf @method('DELETE')<button class="btn btn-ghost btn-sm text-bad-ink">Hapus permanen</button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </x-scroll-pager>
            {{ $versions->links() }}
        @endif
    </div>
</x-layouts.app>
