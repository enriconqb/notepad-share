<?php

declare(strict_types=1);

namespace App;

final class Csrf
{
    public static function verify(): bool
    {
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        if ($token === '' && isset($_POST['_csrf'])) {
            $token = (string) $_POST['_csrf'];
        }
        return $token !== '' && hash_equals(SessionService::csrf(), $token);
    }

    public static function requireValid(): void
    {
        if (!self::verify()) {
            Http::json(['error' => ['code' => 'csrf', 'message' => 'Token CSRF tidak valid']], 403);
        }
    }
}
