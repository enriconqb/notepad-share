<?php

declare(strict_types=1);

namespace App;

final class UploadStore
{
    public const MAX_BATCH = 20 * 1024 * 1024;

    private const MAX = 20 * 1024 * 1024;

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
            throw new \InvalidArgumentException('Maksimal 20MB');
        }
        $tmp = (string) ($file['tmp_name'] ?? '');
        if ($tmp === '' || !is_file($tmp)) {
            throw new \InvalidArgumentException('Unggahan gagal');
        }
        $mime = $this->detectMime($tmp);
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
                'rel' => basename((string) ($file['name'] ?? 'image')),
                'uploaded_at' => gmdate('c'),
                'kind' => 'image',
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

    public function saveMany(string $slug, array $files, array $paths, string $basePath): array
    {
        $batch = $this->normalizeBatch($files, $paths);
        $total = 0;
        foreach ($batch as $item) {
            $total += $item['size'];
        }
        if ($total > self::MAX_BATCH) {
            throw new \InvalidArgumentException('Maksimal 20MB per kiriman');
        }
        $saved = [];
        foreach ($batch as $item) {
            $saved[] = $this->storeBlob($slug, $item, $basePath);
        }
        return ['files' => $saved];
    }

    public function listFiles(string $slug, string $basePath): array
    {
        $idx = JsonFile::read($this->root . '/uploads/' . $slug . '/index.json');
        $out = [];
        foreach (($idx['files'] ?? []) as $row) {
            if (!is_array($row) || empty($row['id'])) {
                continue;
            }
            $id = (string) $row['id'];
            $out[] = [
                'id' => $id,
                'name' => (string) ($row['rel'] ?? $row['original'] ?? $id),
                'bytes' => (int) ($row['bytes'] ?? 0),
                'uploaded_at' => (string) ($row['uploaded_at'] ?? ''),
                'kind' => (string) ($row['kind'] ?? 'file'),
                'url' => $basePath . '/n/' . $slug . (($row['kind'] ?? '') === 'image' ? '/img/' : '/file/') . $id,
            ];
        }
        return $out;
    }

    public function findDownload(string $slug, string $id): ?array
    {
        $file = $this->find($slug, $id);
        if ($file === null) {
            return null;
        }
        $idx = JsonFile::read($this->root . '/uploads/' . $slug . '/index.json');
        $name = 'berkas';
        foreach (($idx['files'] ?? []) as $row) {
            if (is_array($row) && ($row['id'] ?? '') === $id) {
                $name = basename(str_replace('\\', '/', (string) ($row['original'] ?? $row['rel'] ?? $name)));
                break;
            }
        }
        $file['name'] = $name !== '' ? $name : 'berkas';
        return $file;
    }

    public function pruneExpired(string $slug, int $retentionMs): int
    {
        if ($retentionMs <= 0) {
            return 0;
        }
        $dir = $this->root . '/uploads/' . $slug;
        $indexPath = $dir . '/index.json';
        if (!is_file($indexPath)) {
            return 0;
        }
        $removed = 0;
        $cutoff = (int) (microtime(true) * 1000) - $retentionMs;
        JsonFile::mutate($indexPath, function (array $idx) use ($dir, $cutoff, &$removed) {
            $keep = [];
            foreach ($idx['files'] ?? [] as $row) {
                if (!is_array($row)) {
                    continue;
                }
                $ts = strtotime((string) ($row['uploaded_at'] ?? '')) ?: 0;
                if ($ts > 0 && ($ts * 1000) < $cutoff) {
                    $id = (string) ($row['id'] ?? '');
                    foreach (glob($dir . '/' . $id . '.*') ?: [] as $path) {
                        if (is_file($path)) {
                            unlink($path);
                        }
                    }
                    $removed++;
                    continue;
                }
                $keep[] = $row;
            }
            $idx['files'] = $keep;
            return $idx;
        });
        return $removed;
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

    private function normalizeBatch(array $files, array $paths): array
    {
        $names = $files['name'] ?? null;
        if (!is_array($names)) {
            if (($files['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
                throw new \InvalidArgumentException('Tidak ada berkas');
            }
            $files = [
                'name' => [$files['name'] ?? 'berkas'],
                'tmp_name' => [$files['tmp_name'] ?? ''],
                'error' => [$files['error'] ?? UPLOAD_ERR_NO_FILE],
                'size' => [$files['size'] ?? 0],
            ];
            $names = $files['name'];
        }
        $batch = [];
        foreach ($names as $i => $name) {
            $err = (int) ($files['error'][$i] ?? UPLOAD_ERR_NO_FILE);
            if ($err !== UPLOAD_ERR_OK) {
                throw new \InvalidArgumentException('Unggahan gagal');
            }
            $rel = (string) ($paths[$i] ?? $name);
            $rel = str_replace('\\', '/', $rel);
            $rel = ltrim($rel, '/');
            if ($rel === '' || str_contains($rel, '..') || str_contains($rel, "\0")) {
                throw new \InvalidArgumentException('Jalur berkas tidak valid');
            }
            $batch[] = [
                'name' => basename(str_replace('\\', '/', (string) $name)) ?: 'berkas',
                'rel' => $rel,
                'tmp' => (string) ($files['tmp_name'][$i] ?? ''),
                'size' => (int) ($files['size'][$i] ?? 0),
            ];
        }
        if ($batch === []) {
            throw new \InvalidArgumentException('Tidak ada berkas');
        }
        return $batch;
    }

    private function storeBlob(string $slug, array $item, string $basePath): array
    {
        $tmp = $item['tmp'];
        if ($tmp === '' || !is_file($tmp)) {
            throw new \InvalidArgumentException('Unggahan gagal');
        }
        if ($item['size'] > self::MAX) {
            throw new \InvalidArgumentException('Maksimal 20MB');
        }
        $id = bin2hex(random_bytes(8));
        $dir = $this->root . '/uploads/' . $slug;
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Tidak dapat menyimpan berkas');
        }
        $dest = $dir . '/' . $id . '.bin';
        if (!move_uploaded_file($tmp, $dest)) {
            throw new \RuntimeException('Gagal memindahkan berkas');
        }
        $mime = 'application/octet-stream';
        JsonFile::mutate($dir . '/index.json', function (array $idx) use ($id, $mime, $item) {
            $idx['files'] = $idx['files'] ?? [];
            $idx['files'][] = [
                'id' => $id,
                'ext' => 'bin',
                'mime' => $mime,
                'bytes' => (int) $item['size'],
                'original' => $item['name'],
                'rel' => $item['rel'],
                'uploaded_at' => gmdate('c'),
                'kind' => 'file',
            ];
            return $idx;
        });
        $safe = preg_replace('/[^\w.\-\/ ]/u', '_', $item['rel']) ?: 'berkas';
        return [
            'id' => $id,
            'name' => $item['rel'],
            'url' => $basePath . '/n/' . $slug . '/file/' . $id,
            'markdown' => '[' . $safe . '](' . $basePath . '/n/' . $slug . '/file/' . $id . ')',
        ];
    }

    private function detectMime(string $path): string
    {
        if (extension_loaded('fileinfo')) {
            try {
                $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path) ?: '';
                if (isset(self::MIME[$mime])) {
                    return $mime;
                }
            } catch (\Throwable) {
                // Fallback if fileinfo is listed but the class is unavailable.
            }
        }
        if (function_exists('getimagesize')) {
            $info = @getimagesize($path);
            $mime = is_array($info) ? (string) ($info['mime'] ?? '') : '';
            if (isset(self::MIME[$mime])) {
                return $mime;
            }
        }
        $head = (string) file_get_contents($path, false, null, 0, 16);
        if (str_starts_with($head, "\xFF\xD8\xFF")) {
            return 'image/jpeg';
        }
        if (str_starts_with($head, "\x89PNG\r\n\x1A\n")) {
            return 'image/png';
        }
        if (str_starts_with($head, 'GIF87a') || str_starts_with($head, 'GIF89a')) {
            return 'image/gif';
        }
        if (strlen($head) >= 12 && str_starts_with($head, 'RIFF') && substr($head, 8, 4) === 'WEBP') {
            return 'image/webp';
        }
        return '';
    }
}
