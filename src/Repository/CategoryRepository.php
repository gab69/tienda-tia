<?php
namespace App\Repository;

use PDO;

final class CategoryRepository
{
    public function __construct(private PDO $db) {}

    public function all(): array
    {
        return $this->db->query('SELECT c.*, COUNT(p.id) AS total FROM categorias c LEFT JOIN productos p ON p.categoria_id=c.id GROUP BY c.id ORDER BY c.id DESC')->fetchAll();
    }
    public function save(int $id, string $name, int $state): void
    {
        if ($id > 0) $this->db->prepare('UPDATE categorias SET nombre=?,estado=? WHERE id=?')->execute([$name, $state, $id]);
        else $this->db->prepare('INSERT INTO categorias (nombre,estado) VALUES (?,?)')->execute([$name, $state]);
    }
    public function toggle(int $id): void { $this->db->prepare('UPDATE categorias SET estado=1-estado WHERE id=?')->execute([$id]); }
}
