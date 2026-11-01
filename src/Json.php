<?php
namespace App;

final class Json
{
    public static function send(mixed $data, int $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        exit;
    }
    public static function ok(array $extra = []) { self::send(['ok' => true] + $extra); }
    public static function fail(string $msg, int $code = 200) { self::send(['ok' => false, 'msg' => $msg], $code); }
}
