<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SupportFile extends Model
{
    protected $fillable = ['title', 'description', 'category', 'path', 'original_name', 'mime', 'size', 'uploaded_by'];

    /** Tipe yang bisa ditampilkan langsung oleh browser (ekstensi => Content-Type); DOCX/PPTX/XLSX/ZIP hanya diunduh. */
    public const PREVIEW_TYPES = ['pdf' => 'application/pdf', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png'];

    public function extension(): string
    {
        return strtolower(pathinfo($this->original_name, PATHINFO_EXTENSION));
    }

    public function isPreviewable(): bool
    {
        return array_key_exists($this->extension(), self::PREVIEW_TYPES);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
