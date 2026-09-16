<?php

declare(strict_types=1);

namespace App;

final class UploadStore
{
    private const MAX = 3 * 1024 * 1024;

    private const MIME = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/gif' => 'gif',
        'image/webp' => 'webp',
    ];

    public function __construct(private string $root)
    {
    }

    public function save(string $slug, array $file, string $basePath): array
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new \InvalidArgumentException('Unggahan gagal');
        }
        if (($file['size'] ?? 0) > self::MAX) {
            throw new \InvalidArgumentException('Maksimal 3MB');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($tmp) ?: '';
        if (!isset(self::MIME[$mime])) {
            throw new \InvalidArgumentException('Tipe gambar tidak didukung');
        }
        $ext = self::MIME[$mime];
        $id = bin2hex(random_bytes(8));
        $dir = $this->root . '/uploads/' . $slug;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Tidak dapat menyimpan berkas');
        }
        $dest = $dir . '/' . $id . '.' . $ext;
        if (!move_uploaded_file($tmp, $dest)) {
            throw new \RuntimeException('Gagal memindahkan berkas');
        }
        $indexPath = $dir . '/index.json';
        JsonFile::mutate($indexPath, function (array $idx) use ($id, $ext, $mime, $file) {
            $idx['files'] = $idx['files'] ?? [];
            $idx['files'][] = [
                'id' => $id,
                'ext' => $ext,
                'mime' => $mime,
                'bytes' => (int) ($file['size'] ?? 0),
                'original' => basename((string) ($file['name'] ?? 'image')),
            ];
            return $idx;
        });
        $url = $basePath . '/n/' . $slug . '/img/' . $id;
        $name = preg_replace('/[^\w.-]/', '_', (string) ($file['name'] ?? 'image')) ?: 'image';
        return [
            'id' => $id,
            'url' => $url,
            'markdown' => '![' . $name . '](' . $url . ')',
        ];
    }

    public function find(string $slug, string $id): ?array
    {
        if (!preg_match('/^[a-f0-9]{16}$/', $id)) {
            return null;
        }
        $matches = glob($this->root . '/uploads/' . $slug . '/' . $id . '.*') ?: [];
        $matches = array_values(array_filter($matches, fn ($p) => !str_ends_with($p, 'index.json')));
        if ($matches === []) {
            return null;
        }
        $path = $matches[0];
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $mime = array_search($ext, self::MIME, true) ?: 'application/octet-stream';
        return ['path' => $path, 'mime' => $mime];
    }

    public function deleteSlug(string $slug): void
    {
        $dir = $this->root . '/uploads/' . $slug;
        if (!is_dir($dir)) {
            return;
        }
        foreach (glob($dir . '/*') ?: [] as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        @rmdir($dir);
    }
}
