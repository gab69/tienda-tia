<?php
namespace App\Controller\Admin;

use App\{ImageUploader, Json, Request};
use App\Controller\BaseController;
use App\Repository\{ProductRepository, AuditLog};

final class ProductController extends BaseController
{
    public function list() { Json::send((new ProductRepository($this->db))->all()); }

    public function nextCode() { Json::send(['codigo' => (new ProductRepository($this->db))->nextCode()]); }

    public function save()
    {
        $code = Request::text('codigo', 50);
        $name = Request::text('nombre', 150);
        if ($code === '' || $name === '') Json::fail('Código y nombre son obligatorios');

        $id = Request::toInt('id');
        $cat = Request::get('categoria_id');
        $camp = Request::get('campania_id');
        $data = [
            $code, $name, Request::text('descripcion'), Request::text('descripcion_detallada'),
            Request::money('precio_unidad'), Request::money('precio_docena'), Request::money('precio_caja'),
            is_numeric($cat) ? (int)$cat : null, is_numeric($camp) ? (int)$camp : null, Request::flag('estado'),
        ];
        $repo = new ProductRepository($this->db);
        for ($try = 0; ; $try++) {
            try {
                $saved = $repo->save($id, $data);
                break;
            } catch (\PDOException $e) {
                if (($e->errorInfo[1] ?? 0) !== 1062) throw $e;
                if ($id > 0 || $try >= 20) Json::fail('El código de producto ya existe');
                $data[0] = $repo->nextCode(); // otra pestaña tomó ese código automático: se usa el siguiente
            }
        }
        (new AuditLog($this->db))->log($id > 0 ? 'Editar' : 'Crear', 'productos', "ID $saved");
        Json::ok(['id' => $saved, 'codigo' => $data[0]]);
    }

    public function toggle()
    {
        $id = Request::toInt('id');
        (new ProductRepository($this->db))->toggle($id);
        (new AuditLog($this->db))->log('Toggle', 'productos', "ID $id");
        Json::ok();
    }

    public function uploadImage()
    {
        $pid = (int)($_POST['producto_id'] ?? 0);
        if ($pid <= 0 || empty($_FILES['imagen']['name'])) Json::fail('Datos incompletos');
        [$ok, $pathOrMsg] = ImageUploader::store($_FILES['imagen']);
        if (!$ok) Json::fail($pathOrMsg);
        (new ProductRepository($this->db))->addImage($pid, $pathOrMsg);
        (new AuditLog($this->db))->log('Subir imagen', 'productos', "ID $pid");
        Json::ok(['ruta' => $pathOrMsg]);
    }

    public function deleteImage()
    {
        $id = Request::toInt('id');
        $repo = new ProductRepository($this->db);
        $path = $repo->imagePath($id);
        if ($path !== null) {
            ImageUploader::delete($path);
            $repo->deleteImage($id);
            (new AuditLog($this->db))->log('Eliminar imagen', 'productos', "img ID $id");
        }
        Json::ok();
    }
}
