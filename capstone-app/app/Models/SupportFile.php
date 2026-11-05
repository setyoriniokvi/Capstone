<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportFile extends Model
{
    protected $fillable = ['title', 'description', 'category', 'visible_to', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    /** Role yang dapat dipilih sebagai pembaca file (admin selalu dapat melihat semua file). */
    public const VIEWER_ROLES = ['dosen' => 'Dosen', 'mahasiswa' => 'Mahasiswa'];

    /** Tipe yang bisa ditampilkan langsung oleh browser (ekstensi => Content-Type); DOCX/PPTX/XLSX/ZIP hanya diunduh. */
    public const PREVIEW_TYPES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    protected function casts(): array
    {
        return ['visible_to' => 'array'];
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function isPreviewable(): bool
    {
        return array_key_exists($this->extension(), self::PREVIEW_TYPES);
    }

    /** Admin melihat semua file; dosen / mahasiswa hanya file yang role-nya dicentang. */
    public function isVisibleTo(User $user): bool
    {
        return $user->isAdmin() || in_array($user->role, $this->visible_to ?? [], true);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
