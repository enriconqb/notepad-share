<?php

declare(strict_types=1);

namespace App;

final class Slug
{
    public const PATTERN = '/^[a-z0-9][a-z0-9-]{1,62}$/';

    private const RESERVED = ['api', 'n', 'assets', 'index', 'public'];

    public static function normalize(string $slug): string
    {
        return strtolower(trim($slug));
    }

    public static function valid(string $slug): bool
    {
        $slug = self::normalize($slug);
        if (!preg_match(self::PATTERN, $slug)) {
            return false;
        }
        return !in_array($slug, self::RESERVED, true);
    }
}
