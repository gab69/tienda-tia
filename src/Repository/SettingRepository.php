<?php
namespace App\Repository;

use PDO;

final class SettingRepository
{
    public function __construct(private PDO $db) {}

    public function all(): array { return $this->db->query('SELECT clave, valor FROM ajustes')->fetchAll(PDO::FETCH_KEY_PAIR); }

    public function rows(): array { return $this->db->query('SELECT * FROM ajustes')->fetchAll(); }

    /** Ajustes públicos de la tienda (una sola consulta). */
    public function config(): array
    {
        $a = $this->all();
        return [
            'nombre_pagina' => $a['nombre_pagina'] ?? 'Mi Tienda',
            'logo' => $a['logo'] ?? '',
            'whatsapp' => $a['whatsapp'] ?? '51987654321',
            'boleta_header' => $a['boleta_header'] ?? '',
        ];
    }

    public function save(string $key, string $value): void
    {
        $this->db->prepare('INSERT INTO ajustes (clave, valor) VALUES (?,?) ON DUPLICATE KEY UPDATE valor=VALUES(valor)')->execute([$key, $value]);
    }
}
