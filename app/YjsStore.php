<?php

declare(strict_types=1);

namespace App;

final class YjsStore
{
    public function __construct(private string $root)
    {
    }

    public function path(string $slug): string
    {
        return $this->root . '/yjs/' . $slug . '.json';
    }

    public function load(string $slug): array
    {
        $data = JsonFile::read($this->path($slug));
        if (!is_array($data)) {
            return ['seq' => 0, 'updates' => []];
        }
        $data['seq'] = (int) ($data['seq'] ?? 0);
        $data['updates'] = is_array($data['updates'] ?? null) ? $data['updates'] : [];
        return $data;
    }

    public function since(string $slug, int $since): array
    {
        $data = $this->load($slug);
        $out = [];
        foreach ($data['updates'] as $row) {
            if (!is_array($row)) {
                continue;
            }
            if ((int) ($row['seq'] ?? 0) > $since) {
                $out[] = $row;
            }
        }
        return ['seq' => $data['seq'], 'updates' => $out];
    }

    public function append(string $slug, string $update, int $clientId, bool $snapshot = false): array
    {
        if ($update === '' || strlen($update) > 2_000_000) {
            throw new \InvalidArgumentException('Update tidak valid');
        }
        return JsonFile::mutate($this->path($slug), function (array $data) use ($update, $clientId, $snapshot) {
            if ($data === [] || !isset($data['seq'])) {
                $data = ['seq' => 0, 'updates' => []];
            }
            $seq = (int) $data['seq'] + 1;
            $row = ['seq' => $seq, 'update' => $update, 'client' => $clientId];
            if ($snapshot) {
                $data['updates'] = [$row];
            } else {
                $data['updates'][] = $row;
                if (count($data['updates']) > 250) {
                    $data['updates'] = array_slice($data['updates'], -120);
                }
            }
            $data['seq'] = $seq;
            return $data;
        });
    }

    public function seq(string $slug): int
    {
        return (int) ($this->load($slug)['seq'] ?? 0);
    }
}
