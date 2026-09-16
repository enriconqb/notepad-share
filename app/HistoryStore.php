<?php

declare(strict_types=1);

namespace App;

final class HistoryStore
{
    public function __construct(private string $root)
    {
    }

    private function dir(string $slug): string
    {
        return $this->root . '/history/' . $slug;
    }

    public function add(string $slug, string $content, string $user, string $sessionId): ?array
    {
        if (trim($content) === '') {
            return null;
        }
        $list = $this->list($slug, 1);
        if ($list && ($list[0]['content'] ?? '') === $content) {
            return null;
        }
        $ts = (int) round(microtime(true) * 1000);
        $id = $ts . '_' . substr($sessionId, 0, 8);
        $rec = [
            'id' => $id,
            'slug' => $slug,
            'content' => $content,
            'user' => $user,
            'session_id' => $sessionId,
            'ts' => $ts,
            'len' => strlen($content),
        ];
        $path = $this->dir($slug) . '/' . $id . '.json';
        JsonFile::mutate($path, fn () => $rec);
        return $rec;
    }

    public function list(string $slug, int $limit = 50): array
    {
        $dir = $this->dir($slug);
        if (!is_dir($dir)) {
            return [];
        }
        $files = glob($dir . '/*.json') ?: [];
        rsort($files, SORT_STRING);
        $out = [];
        foreach ($files as $file) {
            $row = JsonFile::read($file);
            if (is_array($row)) {
                $out[] = $row;
            }
            if (count($out) >= $limit) {
                break;
            }
        }
        return $out;
    }

    public function get(string $slug, string $id): ?array
    {
        if (!preg_match('/^[0-9]+_[a-zA-Z0-9]+$/', $id)) {
            return null;
        }
        return JsonFile::read($this->dir($slug) . '/' . $id . '.json');
    }

    public function deleteAll(string $slug): int
    {
        $dir = $this->dir($slug);
        if (!is_dir($dir)) {
            return 0;
        }
        $n = 0;
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            if (unlink($file)) {
                $n++;
            }
        }
        return $n;
    }

    public function prune(string $slug, int $retentionMs): int
    {
        if ($retentionMs === 0) {
            return 0;
        }
        $cut = (int) round(microtime(true) * 1000) - $retentionMs;
        $n = 0;
        $dir = $this->dir($slug);
        if (!is_dir($dir)) {
            return 0;
        }
        foreach (glob($dir . '/*.json') ?: [] as $file) {
            $row = JsonFile::read($file);
            if (!is_array($row)) {
                continue;
            }
            if ((int) ($row['ts'] ?? 0) < $cut) {
                if (unlink($file)) {
                    $n++;
                }
            }
        }
        return $n;
    }

    public function pruneAll(NoteStore $notes): int
    {
        $n = 0;
        foreach (glob($this->root . '/notes/*.json') ?: [] as $file) {
            $note = JsonFile::read($file);
            if (!is_array($note)) {
                continue;
            }
            $slug = (string) ($note['slug'] ?? '');
            $ret = (int) ($note['retention_ms'] ?? 86400000);
            if ($slug !== '') {
                $n += $this->prune($slug, $ret);
            }
        }
        return $n;
    }
}
