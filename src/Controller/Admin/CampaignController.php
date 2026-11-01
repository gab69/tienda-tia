<?php
namespace App\Controller\Admin;

use App\{Json, Request};
use App\Controller\BaseController;
use App\Repository\{CampaignRepository, CategoryRepository, AuditLog};

final class CampaignController extends BaseController
{
    public function campaigns() { Json::send((new CampaignRepository($this->db))->all()); }

    public function saveCampaign()
    {
        $name = Request::text('nombre', 100);
        if ($name === '') Json::fail('El nombre es obligatorio');
        $id = Request::toInt('id');
        (new CampaignRepository($this->db))->save($id, $name, Request::text('descripcion'), Request::flag('estado'));
        (new AuditLog($this->db))->log($id > 0 ? 'Editar' : 'Crear', 'campanias', $id > 0 ? "ID $id" : $name);
        Json::ok();
    }

    public function toggleCampaign()
    {
        $id = Request::toInt('id');
        (new CampaignRepository($this->db))->toggle($id);
        (new AuditLog($this->db))->log('Toggle', 'campanias', "ID $id");
        Json::ok();
    }

    public function setDefault()
    {
        $id = Request::toInt('id');
        (new CampaignRepository($this->db))->setDefault($id);
        (new AuditLog($this->db))->log('Default', 'campanias', "ID $id");
        Json::ok();
    }

    public function categories() { Json::send((new CategoryRepository($this->db))->all()); }

    public function saveCategory()
    {
        $name = Request::text('nombre', 100);
        if ($name === '') Json::fail('El nombre es obligatorio');
        $id = Request::toInt('id');
        (new CategoryRepository($this->db))->save($id, $name, Request::flag('estado'));
        (new AuditLog($this->db))->log($id > 0 ? 'Editar' : 'Crear', 'categorias', $id > 0 ? "ID $id" : $name);
        Json::ok();
    }

    public function toggleCategory()
    {
        $id = Request::toInt('id');
        (new CategoryRepository($this->db))->toggle($id);
        (new AuditLog($this->db))->log('Toggle', 'categorias', "ID $id");
        Json::ok();
    }
}
