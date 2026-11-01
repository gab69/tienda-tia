<?php
namespace App\Repository;

use PDO;

final class UserRepository
{
    public function __construct(private PDO $db) {}

    public function activeByUsername(string $username): ?array
    {
        $s = $this->db->prepare('SELECT * FROM usuarios WHERE usuario=? AND estado=1');
        $s->execute([$username]);
        return $s->fetch() ?: null;
    }
    public function isActiveAdmin(int $id): bool
    {
        $s = $this->db->prepare("SELECT 1 FROM usuarios WHERE id=? AND rol='admin' AND estado=1");
        $s->execute([$id]);
        return (bool)$s->fetchColumn();
    }
    public function all(): array { return $this->db->query('SELECT id,nombre,usuario,rol,estado FROM usuarios ORDER BY id DESC')->fetchAll(); }

    /** Lanza PDOException si el usuario ya existe. */
    public function save(int $id, string $name, string $username, string $role, int $state, string $password): void
    {
        if ($id > 0) {
            if ($password !== '') {
                $this->db->prepare('UPDATE usuarios SET nombre=?,usuario=?,rol=?,estado=?,password=? WHERE id=?')
                    ->execute([$name, $username, $role, $state, password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                $this->db->prepare('UPDATE usuarios SET nombre=?,usuario=?,rol=?,estado=? WHERE id=?')->execute([$name, $username, $role, $state, $id]);
            }
            return;
        }
        $this->db->prepare('INSERT INTO usuarios (nombre,usuario,password,rol,estado) VALUES (?,?,?,?,?)')
            ->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT), $role, $state]);
    }
    public function toggle(int $id): void { $this->db->prepare('UPDATE usuarios SET estado=1-estado WHERE id=?')->execute([$id]); }
}
