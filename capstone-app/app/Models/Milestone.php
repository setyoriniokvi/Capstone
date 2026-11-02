<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Milestone extends Model
{
    protected $fillable = ['period_id', 'name', 'description', 'kind', 'document_type', 'allowed_types', 'drive_folder_id', 'starts_at', 'due_at', 'sort_order'];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'due_at' => 'datetime',
            'allowed_types' => 'array',
        ];
    }

    /** Ekstensi yang diizinkan admin untuk milestone dokumen ini (bawaan: config upload.document_mimes). */
    public function fileTypes(): array
    {
        return $this->allowed_types ?: config('capstone.upload.document_mimes');
    }

    /** Untuk aturan validasi "mimes": JPG juga menerima .jpeg. */
    public function uploadMimes(): array
    {
        $types = $this->fileTypes();

        return in_array('jpg', $types, true) ? [...$types, 'jpeg'] : $types;
    }

    /** Untuk atribut accept pada input file. */
    public function acceptAttribute(): string
    {
        return collect($this->uploadMimes())->map(fn ($t) => '.'.$t)->implode(',');
    }

    /** Tautan folder Google Drive Database milestone dokumen (disimpan sebagai ID folder). */
    public function driveFolderUrl(): ?string
    {
        return $this->drive_folder_id ? 'https://drive.google.com/drive/folders/'.$this->drive_folder_id : null;
    }

    /** ID folder dari tautan folder Google Drive (…/folders/{id} atau ?id={id}) atau ID itu sendiri; null bila tidak dikenali. */
    public static function parseDriveFolder(string $value): ?string
    {
        $value = trim($value);
        if (preg_match('~/folders/([A-Za-z0-9_-]+)~', $value, $m) || preg_match('~[?&]id=([A-Za-z0-9_-]+)~', $value, $m)) {
            return $m[1];
        }

        return preg_match('~^[A-Za-z0-9_-]{10,128}$~', $value) ? $value : null;
    }

    public function fileTypesLabel(): string
    {
        return collect($this->fileTypes())->map(fn ($t) => config("capstone.document_file_types.{$t}", strtoupper($t)))->implode(', ');
    }

    public function period(): BelongsTo
    {
        return $this->belongsTo(Period::class);
    }

    public function isOpen(): bool
    {
        return now()->between($this->starts_at, $this->due_at);
    }

    public function hasStarted(): bool
    {
        return now()->greaterThanOrEqualTo($this->starts_at);
    }

    /** Dokumen yang baru boleh dikumpulkan setelah minimal log bimbingan disetujui (aturan tambahan periode, Period::minLogbooks). */
    public const LOGBOOK_GATED_TYPES = ['laporan_akhir', 'materi_presentasi'];

    public function requiresLogbookMinimum(): bool
    {
        return $this->kind === 'dokumen' && in_array($this->document_type, self::LOGBOOK_GATED_TYPES, true);
    }

    public function isPast(): bool
    {
        return now()->greaterThan($this->due_at);
    }

    /** "dokumen" (pengumpulan mahasiswa) atau "lainnya" (hanya dashboard & timeline). */
    public function category(): string
    {
        return $this->kind === 'dokumen' ? 'dokumen' : 'lainnya';
    }

    public function kindLabel(): string
    {
        return config("capstone.milestone_kinds.{$this->kind}", $this->kind);
    }
}
