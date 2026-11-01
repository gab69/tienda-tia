<?php
namespace App\Repository;

use PDO;

final class ProductRepository
{
    private const SELECT = 'SELECT p.*, c.nombre AS categoria, ca.nombre AS campania FROM productos p
        LEFT JOIN categorias c ON c.id=p.categoria_id LEFT JOIN campanias ca ON ca.id=p.campania_id';

    public function __construct(private PDO $db) {}

    /** Página del catálogo público. Devuelve [productos, total|null, hayMás]. El total solo se calcula en la página 1. */
    public function page(string $q, int $campaign, int $page, int $limit): array
    {
        $where = ['p.estado=1'];
        $params = [];
        if ($q !== '') {
            $like = '%' . addcslashes($q, '%_\\') . '%';
            $where[] = '(p.nombre LIKE ? OR p.codigo LIKE ?)';
            array_push($params, $like, $like);
        }
        if ($campaign > 0) { $where[] = 'p.campania_id=?'; $params[] = $campaign; }
        $w = implode(' AND ', $where);

        $total = null;
        if ($page === 1) {
            $s = $this->db->prepare("SELECT COUNT(*) FROM productos p WHERE $w");
            $s->execute($params);
            $total = (int)$s->fetchColumn();
        }
        $offset = ($page - 1) * $limit;
        $fetch = $limit + 1; // uno extra para saber si hay más páginas sin otra consulta
        $s = $this->db->prepare(self::SELECT . " WHERE $w ORDER BY p.id DESC LIMIT $fetch OFFSET $offset");
        $s->execute($params);
        $rows = $s->fetchAll();
        $more = count($rows) > $limit;
        if ($more) array_pop($rows);
        return [$this->withImages($rows), $total, $more];
    }

    public function all(): array
    {
        return $this->withImages($this->db->query(self::SELECT . ' ORDER BY p.id DESC')->fetchAll());
    }

    /** Adjunta las imágenes con UNA consulta (evita una consulta por producto). */
    private function withImages(array $rows): array
    {
        if (!$rows) return $rows;
        $ids = array_column($rows, 'id');
        $s = $this->db->prepare('SELECT id, producto_id, ruta FROM producto_imagenes WHERE producto_id IN (' . implode(',', array_fill(0, count($ids), '?')) . ') ORDER BY id');
        $s->execute($ids);
        $by = [];
        foreach ($s->fetchAll() as $img) $by[$img['producto_id']][] = ['id' => $img['id'], 'ruta' => $img['ruta']];
        foreach ($rows as &$r) $r['imagenes'] = $by[$r['id']] ?? [];
        return $rows;
    }

    public function nextCode(): string
    {
        $max = 0;
        foreach ($this->db->query('SELECT codigo FROM productos')->fetchAll(PDO::FETCH_COLUMN) as $c) {
            if (preg_match('/(\d+)$/', $c, $m)) $max = max($max, (int)$m[1]);
        }
        return 'PROD-' . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
    }

    /** Crea (id=0) o actualiza. $d = [codigo,nombre,desc,detalle,pu,pd,pc,categoria,campania,estado]. Lanza PDOException si el código se repite. */
    public function save(int $id, array $d): int
    {
        if ($id > 0) {
            $d[] = $id;
            $this->db->prepare('UPDATE productos SET codigo=?,nombre=?,descripcion=?,descripcion_detallada=?,precio_unidad=?,precio_docena=?,precio_caja=?,categoria_id=?,campania_id=?,estado=? WHERE id=?')->execute($d);
            return $id;
        }
        $this->db->prepare('INSERT INTO productos (codigo,nombre,descripcion,descripcion_detallada,precio_unidad,precio_docena,precio_caja,categoria_id,campania_id,estado) VALUES (?,?,?,?,?,?,?,?,?,?)')->execute($d);
        return (int)$this->db->lastInsertId();
    }

    public function toggle(int $id): void { $this->db->prepare('UPDATE productos SET estado=1-estado WHERE id=?')->execute([$id]); }

    public function addImage(int $productId, string $path): void
    {
        $this->db->prepare('INSERT INTO producto_imagenes (producto_id, ruta) VALUES (?,?)')->execute([$productId, $path]);
    }
    public function imagePath(int $id): ?string
    {
        $s = $this->db->prepare('SELECT ruta FROM producto_imagenes WHERE id=?');
        $s->execute([$id]);
        return $s->fetchColumn() ?: null;
    }
    public function deleteImage(int $id): void { $this->db->prepare('DELETE FROM producto_imagenes WHERE id=?')->execute([$id]); }
}
