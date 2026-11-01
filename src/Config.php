<?php
namespace App;

final class Config
{
    private static array $data = [];
    public static function load(string $file): void { self::$data = require $file; }
    public static function get(string $key, mixed $default = null): mixed { return self::$data[$key] ?? $default; }
}
