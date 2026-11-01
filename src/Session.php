<?php
namespace App;

/**
 * Sesión sin bloqueos: PHP bloquea el archivo de sesión mientras está abierta, y eso obliga a las
 * pestañas del mismo navegador a esperarse unas a otras. Por eso se lee y se cierra de inmediato,
 * y solo se reabre un instante cuando hay que escribir (ingresar, salir).
 */
final class Session
{
    private const LIFETIME = 7200;   // 2 horas de inactividad
    private const REFRESH  = 300;    // cada cuánto se renueva la caducidad

    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) return;
        ini_set('session.use_strict_mode', '1');
        ini_set('session.gc_maxlifetime', (string)self::LIFETIME);
        session_cache_limiter('');
        if (!headers_sent()) {
            session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS'])]);
        }
        // Un visitante sin cookie nunca ingresó: no hace falta tocar el disco.
        if (!isset($_COOKIE[session_name()])) return;

        session_start(['read_and_close' => true]);
        // read_and_close no renueva la caducidad: sin esto, un admin activo perdería la sesión.
        if (self::logged() && time() - (int)($_SESSION['visto'] ?? 0) > self::REFRESH) {
            session_start();
            $_SESSION['visto'] = time();
            session_write_close();
        }
    }

    public static function id(): ?int { return isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null; }
    public static function name(): string { return $_SESSION['user_nombre'] ?? 'Anónimo'; }
    public static function role(): ?string { return $_SESSION['rol'] ?? null; }
    public static function logged(): bool { return self::id() !== null; }
    public static function isAdmin(): bool { return self::logged() && self::role() === 'admin'; }

    public static function login(array $user): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['user_nombre'] = $user['nombre'];
        $_SESSION['rol'] = $user['rol'];
        $_SESSION['visto'] = time();
        session_write_close();
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        $_SESSION = [];
        session_destroy();
        $p = session_get_cookie_params();
        setcookie(session_name(), '', ['expires' => time() - 3600, 'path' => $p['path'], 'domain' => $p['domain'],
            'secure' => $p['secure'], 'httponly' => $p['httponly'], 'samesite' => $p['samesite']]);
    }

    public static function payload(): array
    {
        return ['logged' => self::logged(), 'rol' => self::role(), 'nombre' => self::logged() ? self::name() : null];
    }
}
