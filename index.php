<?php
// Punto de entrada único: enruta la API y sirve la vista.
require __DIR__ . '/src/bootstrap.php';

$action = $_GET['action'] ?? 'home';
if (is_string($action) && str_starts_with($action, 'api_')) {
    App\Router::run($action);
}
require ROOT . '/views/layout.php';
