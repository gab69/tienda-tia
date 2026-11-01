<?php
namespace App\Repository;

use PDO;

final class SaleRepository
{
    private const TYPES = ['unidad', 'docena', 'caja'];
    private const MAX_QTY = 9999;

    public function __construct(private PDO $db) {}

    /** Registra las líneas del carrito. Los precios se toman de la base de datos, nunca del navegador. Devuelve cuántas se guardaron. */
    public function register(array $items): int
    {
        $lines = [];
        foreach (array_slice($items, 0, 100) as $it) {
            $pid = (int)($it['id'] ?? 0);
            $type = $it['tipo'] ?? '';
            $qty = (int)($it['cantidad'] ?? 0);
            if ($pid > 0 && in_array($type, self::TYPES, true) && $qty > 0 && $qty <= self::MAX_QTY) $lines[] = [$pid, $type, $qty];
        }
        if (!$lines) return 0;

        $ids = array_values(array_unique(array_column($lines, 0)));
        $s = $this->db->prepare('SELECT id, precio_unidad, precio_docena, precio_caja FROM productos WHERE estado=1 AND id IN (' . implode(',', array_fill(0, count($ids), '?')) . ')');
        $s->execute($ids);
        $prices = [];
        foreach ($s->fetchAll() as $r) $prices[(int)$r['id']] = $r;

        $ins = $this->db->prepare('INSERT INTO ventas (producto_id,cantidad,tipo,total) VALUES (?,?,?,?)');
        $saved = 0;
        $this->db->beginTransaction();
        try {
            foreach ($lines as [$pid, $type, $qty]) {
                $price = (float)($prices[$pid]['precio_' . $type] ?? 0);
                if ($price <= 0) continue; // producto retirado o sin precio para ese tipo
                $ins->execute([$pid, $qty, $type, round($price * $qty, 2)]);
                $saved++;
            }
            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }
        return $saved;
    }

    public function dashboard(): array
    {
        $counts = $this->db->query('SELECT (SELECT COUNT(*) FROM productos) AS tp, (SELECT COUNT(*) FROM categorias) AS tc,
            (SELECT COUNT(*) FROM campanias) AS tca, (SELECT COUNT(*) FROM usuarios) AS tu')->fetch();
        $counts['mv'] = $this->db->query('SELECT p.nombre, p.codigo, SUM(v.cantidad) AS vendidos, SUM(v.total) AS ingresos
            FROM productos p JOIN ventas v ON v.producto_id=p.id GROUP BY p.id ORDER BY vendidos DESC LIMIT 10')->fetchAll();
        return $counts;
    }
}
