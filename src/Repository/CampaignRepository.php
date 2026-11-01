<?php
namespace App\Repository;

use PDO;

final class CampaignRepository
{
    public function __construct(private PDO $db) {}

    public function active(): array { return $this->db->query('SELECT id, nombre FROM campanias WHERE estado=1 ORDER BY id')->fetchAll(); }
    public function all(): array { return $this->db->query('SELECT * FROM campanias ORDER BY id DESC')->fetchAll(); }
    public function defaultId(): int
    {
        return (int)$this->db->query('SELECT id FROM campanias WHERE activa_default=1 AND estado=1 LIMIT 1')->fetchColumn();
    }
    public function save(int $id, string $name, string $desc, int $state): void
    {
        if ($id > 0) $this->db->prepare('UPDATE campanias SET nombre=?,descripcion=?,estado=? WHERE id=?')->execute([$name, $desc, $state, $id]);
        else $this->db->prepare('INSERT INTO campanias (nombre,descripcion,estado) VALUES (?,?,?)')->execute([$name, $desc, $state]);
    }
    public function toggle(int $id): void { $this->db->prepare('UPDATE campanias SET estado=1-estado WHERE id=?')->execute([$id]); }
    /** Una sola sentencia: quien abra la tienda justo en ese momento nunca se queda sin campaña principal. */
    public function setDefault(int $id): void
    {
        $this->db->prepare('UPDATE campanias SET activa_default=(id=?)')->execute([$id]);
    }
}
