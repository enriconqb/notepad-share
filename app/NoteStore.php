<?php

declare(strict_types=1);

namespace App;

final class NoteStore
{
    public const RETENTION_OPTIONS = [
        3600000,
        21600000,
        43200000,
        86400000,
        259200000,
        604800000,
        0,
    ];

    public function __construct(private string $root)
    {
    }

    public function path(string $slug): string
    {
        return $this->root . '/notes/' . $slug . '.json';
    }

    public function defaultNote(string $slug): array
    {
        $now = gmdate('c');
        return [
            'slug' => $slug,
            'title' => $slug,
            'format' => 'md',
            'content' => "",
            'rev' => 1,
            'updated_at' => $now,
            'updated_by' => '',
            'retention_ms' => 86400000,
            'encrypted' => false,
            'presence' => [],
            'owner_session_id' => SessionService::id(),
            'locked' => false,
            'password_hash' => null,
            'access_gen' => 1,
            'created_at' => $now,
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

    public function exists(string $slug): bool
    {
        return is_file($this->path($slug));
    }

    public function destroy(string $slug): void
    {
        $path = $this->path($slug);
        if (is_file($path)) {
            unlink($path);
        }
    }

    public function isLegacy(array $note): bool
    {
        return ($note['owner_session_id'] ?? '') === '';
    }

    public function isLocked(array $note): bool
    {
        if ($this->isLegacy($note)) {
            return false;
        }
        return (bool) ($note['locked'] ?? false);
    }

    public function isOwner(array $note): bool
    {
        $oid = (string) ($note['owner_session_id'] ?? '');
        return $oid !== '' && hash_equals($oid, SessionService::id());
    }

    public function hasPassword(array $note): bool
    {
        return is_string($note['password_hash'] ?? null) && $note['password_hash'] !== '';
    }

    public function sessionUnlocked(string $slug, array $note): bool
    {
        $gen = (int) ($note['access_gen'] ?? 1);
        return (int) (($_SESSION['room_unlock'][$slug] ?? -1)) === $gen;
    }

    public function grantUnlock(string $slug, array $note): void
    {
        if (!isset($_SESSION['room_unlock']) || !is_array($_SESSION['room_unlock'])) {
            $_SESSION['room_unlock'] = [];
        }
        $_SESSION['room_unlock'][$slug] = (int) ($note['access_gen'] ?? 1);
    }

    public function canAccess(array $note): bool
    {
        if ($this->isOwner($note)) {
            return true;
        }
        if (!$this->isLocked($note)) {
            return true;
        }
        if (!$this->hasPassword($note)) {
            return false;
        }
        return $this->sessionUnlocked((string) $note['slug'], $note);
    }

    public function isExpired(array $note): bool
    {
        $ret = (int) ($note['retention_ms'] ?? 86400000);
        if ($ret === 0) {
            return false;
        }
        $created = strtotime((string) ($note['created_at'] ?? $note['updated_at'] ?? '')) ?: 0;
        if ($created <= 0) {
            return false;
        }
        return ((int) (microtime(true) * 1000) - ($created * 1000)) >= $ret;
    }

    public static function normalizeRetention(mixed $value): int
    {
        $ms = (int) $value;
        return in_array($ms, self::RETENTION_OPTIONS, true) ? $ms : 86400000;
    }

    public function accessMeta(array $note): array
    {
        return [
            'exists' => true,
            'locked' => $this->isLocked($note),
            'has_password' => $this->hasPassword($note),
            'is_owner' => $this->isOwner($note),
        ];
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
        $note['locked'] = $this->isLocked($note);
        $note['has_password'] = $this->hasPassword($note);
        $note['is_owner'] = $this->isOwner($note);
        unset($note['_internal'], $note['_conflict'], $note['password_hash'], $note['owner_session_id']);
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
