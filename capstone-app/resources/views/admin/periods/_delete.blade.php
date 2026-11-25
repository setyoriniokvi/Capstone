{{-- Tombol "Hapus Periode" (kartu Kelola Periode). Butuh $period, $teamCount.
     Periode kosong: konfirmasi biasa. Periode yang sudah punya tim: harus dinonaktifkan dulu (tombol tetap tampil tetapi
     nonaktif), lalu admin mengetik nama periode di dialog konfirmasi (confirm_name). --}}
@php $locked = $teamCount > 0 && $period->is_active; @endphp
<form method="POST" action="{{ route('admin.periods.destroy', $period) }}"
      data-confirm="{{ $teamCount > 0
          ? "Hapus {$period->name} secara permanen? {$teamCount} tim beserta topik, dokumen, log bimbingan, dan nilainya ikut terhapus dan tidak dapat dikembalikan. Berkas di Google Drive tidak ikut terhapus."
          : "Hapus {$period->name}? Milestone dan pengaturan periode ini ikut terhapus." }}"
      data-confirm-label="Hapus periode"
      @if ($teamCount > 0) data-confirm-type="{{ $period->name }}" data-confirm-type-field="confirm_name" @endif>
    @csrf @method('DELETE')
    <button class="btn btn-ghost btn-sm w-full text-bad-ink disabled:cursor-not-allowed disabled:opacity-50" @disabled($locked)
            @if ($locked) title="Nonaktifkan periode ini terlebih dahulu sebelum menghapusnya" @endif>
        <x-heroicon-o-trash class="h-4 w-4" /> Hapus Periode
    </button>
</form>
