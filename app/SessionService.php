<?php

declare(strict_types=1);

namespace App;

final class SessionService
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        ]);
        session_start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
        }
        if (empty($_SESSION['display_name'])) {
            $_SESSION['display_name'] = 'user-' . substr(bin2hex(random_bytes(2)), 0, 4);
        }
        if (empty($_SESSION['color'])) {
            $palette = ['#6366f1', '#ec4899', '#10b981', '#f59e0b', '#8b5cf6', '#06b6d4', '#ef4444', '#84cc16'];
            $h = 0;
            foreach (str_split($_SESSION['display_name']) as $c) {
                $h = ($h * 31 + ord($c)) & 0x7fffffff;
            }
            $_SESSION['color'] = $palette[$h % count($palette)];
        }
    }

    public static function id(): string
    {
        return session_id();
    }

    public static function displayName(): string
    {
        return (string) $_SESSION['display_name'];
    }

    public static function color(): string
    {
        return (string) $_SESSION['color'];
    }

    public static function csrf(): string
    {
        return (string) $_SESSION['csrf'];
    }

    public static function setDisplayName(string $name): void
    {
        $_SESSION['display_name'] = $name;
    }

    public static function payload(): array
    {
        return [
            'display_name' => self::displayName(),
            'color' => self::color(),
            'csrf' => self::csrf(),
        ];
    }
}
