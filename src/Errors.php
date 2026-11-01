<?php
namespace App;

/** Convierte cualquier fallo en una respuesta clara (JSON en la API, página en la vista) y lo deja en el log. */
final class Errors
{
    private const FATAL = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR;
    private const GENERIC = 'Ocurrió un error interno. Inténtalo de nuevo.';

    public static function register(): void
    {
        set_exception_handler(function (\Throwable $e): void {
            error_log((string)$e);
            [$status, $msg] = self::describe($e);
            self::respond($status, $msg);
        });
        register_shutdown_function(function (): void {
            $err = error_get_last();
            if ($err && ($err['type'] & self::FATAL)) self::respond(500, self::GENERIC);
        });
    }

    /** @return array{int, string} [código HTTP, mensaje para el usuario] */
    private static function describe(\Throwable $e): array
    {
        if (!$e instanceof \PDOException) return [500, self::GENERIC];
        $code = (int)($e->errorInfo[1] ?? $e->getCode());
        switch ($code) {
            case 1040: case 1203: // demasiadas conexiones
                return [503, 'La tienda tiene muchas visitas en este momento. Inténtalo en unos segundos.'];
            case 1045: case 2002: case 2006: case 2013: // MySQL apagado o inaccesible
                return [503, 'No se pudo conectar con la base de datos. Revisa que MySQL esté encendido y los datos de config/config.php.'];
            case 1146: // la base existe pero faltan tablas
                try { Installer::install(true); } catch (\Throwable $t) { error_log((string)$t); return [503, self::GENERIC]; }
                return [503, 'La base de datos se acaba de reparar. Vuelve a intentarlo.'];
            case 1264: case 1406: case 1452: // valor fuera de rango, texto largo o referencia inexistente
                return [422, 'Revisa los datos: alguno es demasiado largo o ya no existe.'];
        }
        return [500, self::GENERIC];
    }

    private static function respond(int $status, string $msg): void
    {
        while (ob_get_level() > 0) ob_end_clean();
        if (headers_sent()) return;
        http_response_code($status);
        $action = $_GET['action'] ?? '';
        if (is_string($action) && str_starts_with($action, 'api_')) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'msg' => $msg], JSON_UNESCAPED_UNICODE);
            return;
        }
        header('Content-Type: text/html; charset=utf-8');
        echo '<!DOCTYPE html><html lang="es"><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>Tienda no disponible</title><body style="font-family:system-ui,sans-serif;text-align:center;padding:64px 16px;color:#17171c">'
            . '<h1 style="font-size:22px">No pudimos abrir la tienda</h1><p>' . htmlspecialchars($msg, ENT_QUOTES, 'UTF-8') . '</p>'
            . '<p><a href="">Reintentar</a></p></body></html>';
    }
}
