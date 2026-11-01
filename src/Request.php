<?php
namespace App;

final class Request
{
    private static ?array $input = null;

    public static function input(): array
    {
        if (self::$input === null) {
            $raw = json_decode((string)file_get_contents('php://input'), true);
            self::$input = is_array($raw) ? $raw : $_POST;
        }
        return self::$input;
    }

    public static function get(string $key, mixed $default = null): mixed { return self::input()[$key] ?? $default; }

    /** Texto sin espacios sobrantes, recortado a $max caracteres. Si no es texto (p. ej. un arreglo), devuelve ''. */
    public static function text(string $key, int $max = 0): string
    {
        $v = self::get($key, '');
        $v = is_scalar($v) ? trim((string)$v) : '';
        return $max > 0 ? mb_substr($v, 0, $max) : $v;
    }

    public static function toInt(string $key): int
    {
        $v = self::get($key, 0);
        return is_numeric($v) ? (int)$v : 0;
    }

    /** Estado 0/1. Si no viene, se asume activo. */
    public static function flag(string $key): int
    {
        return self::text($key) === '0' ? 0 : 1;
    }

    /** Precio válido para DECIMAL(10,2): nunca negativo ni fuera de rango. */
    public static function money(string $key): float
    {
        $v = self::get($key, 0);
        return is_numeric($v) ? min(99999999.99, max(0, round((float)$v, 2))) : 0.0;
    }
}
