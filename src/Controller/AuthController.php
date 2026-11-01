<?php
namespace App\Controller;

use App\{Json, Request, Session};
use App\Repository\{UserRepository, AuditLog};

final class AuthController extends BaseController
{
    private const MAX_FAILED_LOGINS = 10;

    public function login()
    {
        $log = new AuditLog($this->db);
        $ip = (string)($_SERVER['REMOTE_ADDR'] ?? '');
        if ($log->failedLogins($ip) >= self::MAX_FAILED_LOGINS) Json::fail('Demasiados intentos fallidos. Espera 15 minutos.', 429);

        $user = (new UserRepository($this->db))->activeByUsername(Request::text('usuario'));
        $password = Request::get('password', '');
        if ($user && is_string($password) && password_verify($password, $user['password'])) {
            Session::login($user);
            $log->log('Login', 'usuarios', "Ingresó: {$user['usuario']}");
            Json::ok(['rol' => $user['rol'], 'nombre' => $user['nombre']]);
        }
        $log->log(AuditLog::FAILED_LOGIN, 'usuarios', $ip);
        Json::fail('Usuario o contraseña incorrectos');
    }

    public function logout()
    {
        (new AuditLog($this->db))->log('Logout', 'usuarios', 'Cerró sesión');
        Session::destroy();
        Json::ok();
    }
}
