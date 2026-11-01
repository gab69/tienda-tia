<?php
namespace App\Controller\Admin;

use App\{ImageUploader, Json, Request, Session};
use App\Controller\BaseController;
use App\Repository\{UserRepository, SettingRepository, SaleRepository, AuditLog};

final class SystemController extends BaseController
{
    public function users() { Json::send((new UserRepository($this->db))->all()); }

    public function saveUser()
    {
        $name = Request::text('nombre', 100);
        $username = Request::text('usuario', 50);
        if ($name === '' || $username === '') Json::fail('Nombre y usuario son obligatorios');
        $id = Request::toInt('id');
        $password = Request::get('password', '');
        $password = is_string($password) ? $password : '';
        if ($id === 0 && $password === '') Json::fail('Contraseña requerida');
        $role = Request::get('rol') === 'admin' ? 'admin' : 'usuario';
        $state = Request::flag('estado');
        if ($id === Session::id() && ($role !== 'admin' || $state === 0)) Json::fail('No puedes quitarte el rol de administrador ni desactivar tu propio usuario');
        try {
            (new UserRepository($this->db))->save($id, $name, $username, $role, $state, $password);
        } catch (\PDOException $e) {
            if (($e->errorInfo[1] ?? 0) === 1062) Json::fail('Ese usuario ya existe');
            throw $e;
        }
        (new AuditLog($this->db))->log($id > 0 ? 'Editar' : 'Crear', 'usuarios', $id > 0 ? "ID $id" : $username);
        Json::ok();
    }

    public function toggleUser()
    {
        $id = Request::toInt('id');
        if ($id === Session::id()) Json::fail('No puedes desactivar tu propio usuario');
        (new UserRepository($this->db))->toggle($id);
        (new AuditLog($this->db))->log('Toggle', 'usuarios', "ID $id");
        Json::ok();
    }

    public function audit() { Json::send((new AuditLog($this->db))->latest()); }

    public function settings() { Json::send((new SettingRepository($this->db))->rows()); }

    public function saveSettings()
    {
        $repo = new SettingRepository($this->db);
        if (Request::text('nombre_pagina') === '') Json::fail('El nombre de la tienda es obligatorio');

        // El logo va primero: si falla, no se guarda nada a medias.
        $logo = null;
        if (!empty($_FILES['logo']['name'])) {
            [$ok, $logo] = ImageUploader::store($_FILES['logo'], false);
            if (!$ok) Json::fail($logo);
        }
        $repo->save('nombre_pagina', Request::text('nombre_pagina'));
        $repo->save('whatsapp', preg_replace('/\D/', '', Request::text('whatsapp')));
        $repo->save('boleta_header', Request::text('boleta_header'));
        if ($logo !== null) {
            $previous = $repo->all()['logo'] ?? '';
            $repo->save('logo', $logo);
            if ($previous !== '') ImageUploader::delete($previous);
        }
        (new AuditLog($this->db))->log('Editar', 'ajustes', 'Actualizados');
        Json::ok();
    }

    public function dashboard() { Json::send((new SaleRepository($this->db))->dashboard()); }
}
