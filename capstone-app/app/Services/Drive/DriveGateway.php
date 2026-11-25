<?php

namespace App\Services\Drive;

use Psr\Http\Message\StreamInterface;

interface DriveGateway
{
    public function createFolder(string $name, ?string $parentId): string;

    public function upload(string $folderId, string $name, string $mime, string $localPath): string;

    /** Mengganti konten file yang sama (ID tetap) agar tidak terjadi duplikasi (F37). */
    public function update(string $fileId, string $name, string $mime, string $localPath): string;

    public function trash(string $fileId): void;

    public function delete(string $fileId): void;

    public function shareWith(string $fileId, string $email): void;

    /** Isi file (stream) untuk diteruskan server ke pengguna; file lokal sudah dihapus setelah sinkron. */
    public function download(string $fileId): StreamInterface;
}
