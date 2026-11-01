<?php
namespace App;

use App\Controller\{AuthController, CatalogController};
use App\Controller\Admin\{ProductController, CampaignController, SystemController};
use App\Repository\UserRepository;

final class Router
{
    // acción => [Controlador, método, requiereAdmin]
    private const ROUTES = [
        'api_login'  => [AuthController::class, 'login'],
        'api_logout' => [AuthController::class, 'logout'],

        'api_config'           => [CatalogController::class, 'config'],
        'api_productos'        => [CatalogController::class, 'products'],
        'api_campanias'        => [CatalogController::class, 'campaigns'],
        'api_campania_default' => [CatalogController::class, 'defaultCampaign'],
        'api_registrar_venta'  => [CatalogController::class, 'registerSale'],

        'api_admin_productos'         => [ProductController::class, 'list', true],
        'api_admin_siguiente_codigo'  => [ProductController::class, 'nextCode', true],
        'api_admin_guardar_producto'  => [ProductController::class, 'save', true],
        'api_admin_toggle_producto'   => [ProductController::class, 'toggle', true],
        'api_subir_imagen'            => [ProductController::class, 'uploadImage', true],
        'api_eliminar_imagen'         => [ProductController::class, 'deleteImage', true],

        'api_admin_campanias'         => [CampaignController::class, 'campaigns', true],
        'api_admin_guardar_campania'  => [CampaignController::class, 'saveCampaign', true],
        'api_admin_toggle_campania'   => [CampaignController::class, 'toggleCampaign', true],
        'api_admin_campania_default'  => [CampaignController::class, 'setDefault', true],
        'api_admin_categorias'        => [CampaignController::class, 'categories', true],
        'api_admin_guardar_categoria' => [CampaignController::class, 'saveCategory', true],
        'api_admin_toggle_categoria'  => [CampaignController::class, 'toggleCategory', true],

        'api_admin_usuarios'          => [SystemController::class, 'users', true],
        'api_admin_guardar_usuario'   => [SystemController::class, 'saveUser', true],
        'api_admin_toggle_usuario'    => [SystemController::class, 'toggleUser', true],
        'api_admin_auditoria'         => [SystemController::class, 'audit', true],
        'api_admin_ajustes'           => [SystemController::class, 'settings', true],
        'api_admin_guardar_ajustes'   => [SystemController::class, 'saveSettings', true],
        'api_admin_dashboard'         => [SystemController::class, 'dashboard', true],
    ];

    // Únicas acciones que aceptan GET. Las demás escriben datos: solo por POST, que la cookie
    // SameSite=Lax no envía desde otros sitios (protege contra CSRF).
    private const READS = [
        'api_config', 'api_campanias', 'api_campania_default', 'api_admin_productos', 'api_admin_siguiente_codigo',
        'api_admin_campanias', 'api_admin_categorias', 'api_admin_usuarios', 'api_admin_auditoria', 'api_admin_ajustes', 'api_admin_dashboard',
    ];

    public static function run(string $action): void
    {
        if ($action === 'api_sesion') Json::send(Session::payload()); // sin base de datos: la consultan todas las pestañas

        $route = self::ROUTES[$action] ?? null;
        if (!$route) Json::fail('Acción no encontrada', 404);
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST' && !in_array($action, self::READS, true)) Json::fail('Método no permitido', 405);
        [$class, $method] = $route;

        $db = Database::pdo();
        if (($route[2] ?? false) && !self::isActiveAdmin($db)) Json::fail('No autorizado', 403);

        (new $class($db))->$method();
        Json::fail('Sin respuesta', 500);
    }

    // Se valida contra la base: un admin desactivado pierde el acceso aunque su sesión siga abierta.
    private static function isActiveAdmin(\PDO $db): bool
    {
        return Session::isAdmin() && (new UserRepository($db))->isActiveAdmin((int)Session::id());
    }
}
