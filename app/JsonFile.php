<?php

declare(strict_types=1);

namespace App;

final class JsonFile
{
    public static function read(string $path): ?array
    {
        if (!is_file($path)) {
            return null;
        }
        $fp = fopen($path, 'rb');
        if ($fp === false) {
            return null;
        }
        flock($fp, LOCK_SH);
        $raw = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        if ($raw === false || $raw === '') {
            return null;
        }
        $data = json_decode($raw, true);
        return is_array($data) ? $data : null;
    }

    public static function mutate(string $path, callable $fn): array
    {
        $dir = dirname($path);
        if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
            throw new \RuntimeException('Cannot create directory');
        }
        $fp = fopen($path, 'c+b');
        if ($fp === false) {
            throw new \RuntimeException('Cannot open file');
        }
        flock($fp, LOCK_EX);
        $raw = stream_get_contents($fp);
        $data = ($raw !== false && $raw !== '') ? json_decode($raw, true) : [];
        if (!is_array($data)) {
            $data = [];
        }
        $data = $fn($data);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        rewind($fp);
        ftruncate($fp, 0);
        fwrite($fp, $json ?: '{}');
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);
        return $data;
    }
}
