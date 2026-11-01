<?php
namespace App\Repository;

use App\Session;
use PDO;

final class AuditLog
{
    public const FAILED_LOGIN = 'Login fallido';

    public function __construct(private PDO $db) {}

    public function log(string $action, string $table, string $details = ''): void
    {
        $this->db->prepare('INSERT INTO auditoria (usuario_id, usuario_nombre, accion, tabla, detalles) VALUES (?,?,?,?,?)')
            ->execute([Session::id(), Session::name(), $action, $table, $details]);
    }

    /** Intentos fallidos de esta IP en los últimos 15 minutos. */
    public function failedLogins(string $ip): int
    {
        $s = $this->db->prepare('SELECT COUNT(*) FROM auditoria WHERE accion=? AND detalles=? AND fecha > NOW() - INTERVAL 15 MINUTE');
        $s->execute([self::FAILED_LOGIN, $ip]);
        return (int)$s->fetchColumn();
    }

    public function latest(int $limit = 500): array
    {
        return $this->db->query('SELECT * FROM auditoria ORDER BY id DESC LIMIT ' . (int)$limit)->fetchAll();
    }
}
