<x-layouts.app title="Sinkronisasi Dokumen" section="Admin · Sinkronisasi dokumen">
    <x-page-head title="Sinkronisasi Google Drive" lede="Setiap unggahan mahasiswa disalin otomatis ke folder Drive miliknya. Status hanya berhasil setelah Drive mengonfirmasi." />

    @unless ($driveEnabled)
        <p class="mb-6 rounded-lg border border-warn-line bg-warn-tint px-4 py-3 text-sm text-warn-ink">
            <span class="font-medium">Integrasi Google Drive nonaktif.</span> Dokumen tersimpan di server aplikasi. Atur <span class="code">GOOGLE_DRIVE_ENABLED=true</span> dan kredensial service account untuk mengaktifkan sinkronisasi.
        </p>
    @endunless

    <nav class="mb-4 flex flex-wrap gap-2">
        @foreach (['' => 'Semua', 'menunggu' => 'Menunggu', 'proses' => 'Proses', 'berhasil' => 'Berhasil', 'gagal' => 'Gagal', 'dilewati' => 'Dilewati'] as $k => $l)
            <a href="{{ route('admin.documents.index', array_filter(['status' => $k])) }}" class="btn btn-sm {{ (string) request('status') === $k ? 'btn-primary' : 'btn-secondary' }}">{{ $l }}@if ($k !== '') <span class="tabular">{{ $counts[$k] ?? 0 }}</span>@endif</a>
        @endforeach
    </nav>

    <div class="panel">
        @if ($versions->isEmpty())
            <x-empty title="Belum ada dokumen" icon="cloud-arrow-up" />
        @else
            <div class="table-wrap">
                <table class="data-table">
                    <thead><tr><th>Diunggah</th><th>Pemilik</th><th>Tim</th><th>Dokumen</th><th>Revisi</th><th>Aksi Drive</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                        @foreach ($versions as $v)
                            @php $doc = $v->document; @endphp
                            <tr>
                                <td class="whitespace-nowrap text-sm">{{ $v->created_at->translatedFormat('j M H:i') }}</td>
                                <td><p class="font-medium">{{ $doc->owner->name }}</p><p class="text-caption text-muted">{{ $doc->owner_email }}</p></td>
                                <td class="code">{{ $doc->team->code }}</td>
                                <td class="text-sm"><a class="link" href="{{ route('documents.download', $v) }}">{{ $doc->typeLabel() }}</a><p class="text-caption text-muted">{{ $v->stored_name }}</p></td>
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
            </div>
            {{ $versions->links() }}
        @endif
    </div>
</x-layouts.app>
