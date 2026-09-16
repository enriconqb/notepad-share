<?php

declare(strict_types=1);

namespace App;

final class NoteStore
{
    public function __construct(private string $root)
    {
    }

    public function path(string $slug): string
    {
        return $this->root . '/notes/' . $slug . '.json';
    }

    public function defaultNote(string $slug): array
    {
        return [
            'slug' => $slug,
            'title' => $slug,
            'format' => 'md',
            'content' => "",
            'rev' => 1,
            'updated_at' => gmdate('c'),
            'updated_by' => '',
            'retention_ms' => 86400000,
            'encrypted' => false,
            'presence' => [],
        ];
    }

    public function get(string $slug, bool $create = false): ?array
    {
        $path = $this->path($slug);
        $note = JsonFile::read($path);
        if ($note === null && $create) {
            $note = JsonFile::mutate($path, fn () => $this->defaultNote($slug));
        }
        if ($note === null) {
            return null;
        }
        $note['presence'] = $this->freshPresence($note['presence'] ?? []);
        return $note;
    }

    public function ensure(string $slug): array
    {
        return $this->get($slug, true);
    }

    public function save(string $slug, callable $fn): array
    {
        return JsonFile::mutate($this->path($slug), function (array $data) use ($slug, $fn) {
            if ($data === [] || !isset($data['slug'])) {
                $data = $this->defaultNote($slug);
            }
            return $fn($data);
        });
    }

    public function publicNote(array $note): array
    {
        $note['presence'] = array_map(static function (array $p) {
            return [
                'display_name' => $p['display_name'] ?? '',
                'color' => $p['color'] ?? '#6366f1',
                'last_seen' => $p['last_seen'] ?? '',
            ];
        }, $this->freshPresence($note['presence'] ?? []));
        unset($note['_internal'], $note['_conflict']);
        return $note;
    }

    public function freshPresence(array $presence): array
    {
        $cut = time() - 25;
        $out = [];
        foreach ($presence as $p) {
            if (!is_array($p) || empty($p['last_seen'])) {
                continue;
            }
            $ts = strtotime((string) $p['last_seen']) ?: 0;
            if ($ts >= $cut) {
                $out[] = $p;
            }
        }
        return $out;
    }
}
