<?php
namespace App;

use PDO;

/**
 * Instala/actualiza la base de datos y carpetas UNA sola vez (marca storage/installed.lock).
 * Así las peticiones normales no repiten consultas ni escrituras de archivos.
 * Para forzar una reinstalación o migración, borra storage/installed.lock.
 */
final class Installer
{
    private const TABLES = [
        "CREATE TABLE IF NOT EXISTS usuarios (
            id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, usuario VARCHAR(50) UNIQUE NOT NULL,
            password VARCHAR(255) NOT NULL, rol ENUM('admin','usuario') DEFAULT 'usuario', estado TINYINT(1) DEFAULT 1,
            creado_en DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS categorias (
            id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, estado TINYINT(1) DEFAULT 1,
            creado_en DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS campanias (
            id INT AUTO_INCREMENT PRIMARY KEY, nombre VARCHAR(100) NOT NULL, descripcion TEXT, estado TINYINT(1) DEFAULT 1,
            activa_default TINYINT(1) DEFAULT 0, creado_en DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS productos (
            id INT AUTO_INCREMENT PRIMARY KEY, codigo VARCHAR(50) UNIQUE NOT NULL, nombre VARCHAR(150) NOT NULL,
            descripcion TEXT, descripcion_detallada TEXT, precio_unidad DECIMAL(10,2) DEFAULT 0, precio_docena DECIMAL(10,2) DEFAULT 0,
            precio_caja DECIMAL(10,2) DEFAULT 0, categoria_id INT NULL, campania_id INT NULL, estado TINYINT(1) DEFAULT 1,
            creado_en DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (categoria_id) REFERENCES categorias(id) ON DELETE SET NULL,
            FOREIGN KEY (campania_id) REFERENCES campanias(id) ON DELETE SET NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS producto_imagenes (
            id INT AUTO_INCREMENT PRIMARY KEY, producto_id INT NOT NULL, ruta VARCHAR(255) NOT NULL,
            FOREIGN KEY (producto_id) REFERENCES productos(id) ON DELETE CASCADE) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS auditoria (
            id INT AUTO_INCREMENT PRIMARY KEY, fecha DATETIME DEFAULT CURRENT_TIMESTAMP, usuario_id INT NULL,
            usuario_nombre VARCHAR(100), accion VARCHAR(100), tabla VARCHAR(100), detalles TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS ajustes (clave VARCHAR(50) PRIMARY KEY, valor TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
        "CREATE TABLE IF NOT EXISTS ventas (
            id INT AUTO_INCREMENT PRIMARY KEY, producto_id INT, cantidad DECIMAL(10,2), tipo VARCHAR(20), total DECIMAL(10,2),
            fecha DATETIME DEFAULT CURRENT_TIMESTAMP) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
    ];

    // Índices que aceleran el catálogo, las ventas y el historial.
    private const INDEXES = [
        ['productos', 'idx_prod_lista', 'estado, campania_id, id'],
        ['productos', 'idx_prod_nombre', 'nombre'],
        ['producto_imagenes', 'idx_img_producto', 'producto_id'],
        ['ventas', 'idx_ventas_producto', 'producto_id'],
    ];

    private const LOCK = ROOT . '/storage/installed.lock';

    public static function ensure(): void
    {
        if (!is_file(self::LOCK)) self::install();
    }

    /** Solo una petición instala a la vez: las demás esperan aquí y evitan duplicar los datos iniciales. */
    public static function install(bool $repair = false): void
    {
        if (!is_dir(ROOT . '/storage')) @mkdir(ROOT . '/storage', 0755, true);
        $mutex = @fopen(ROOT . '/storage/install.mutex', 'c');
        if ($mutex) flock($mutex, LOCK_EX);
        try {
            if ($repair || !is_file(self::LOCK)) {
                if (!$repair) self::folders();
                self::database();
                @file_put_contents(self::LOCK, date('c'));
            }
        } finally {
            if ($mutex) { flock($mutex, LOCK_UN); fclose($mutex); }
        }
    }

    private static function database(): void
    {
        Database::connect(false)->exec('CREATE DATABASE IF NOT EXISTS `' . Config::get('db')['name'] . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $pdo = Database::pdo();
        foreach (self::TABLES as $sql) $pdo->exec($sql);
        foreach (self::INDEXES as [$table, $name, $cols]) {
            try { $pdo->exec("CREATE INDEX $name ON $table ($cols)"); } catch (\PDOException $e) { /* ya existe */ }
        }
        if ((int)$pdo->query('SELECT COUNT(*) FROM usuarios')->fetchColumn() > 0) return;

        $pdo->prepare('INSERT INTO usuarios (nombre, usuario, password, rol) VALUES (?,?,?,?)')
            ->execute(['Administrador', 'admin', password_hash('admin123', PASSWORD_DEFAULT), 'admin']);
        $aj = $pdo->prepare('INSERT IGNORE INTO ajustes (clave, valor) VALUES (?,?)');
        foreach ([['nombre_pagina', 'Mi Tienda Online'], ['logo', ''], ['whatsapp', '51987654321'],
                  ['boleta_header', "MI TIENDA ONLINE\nRUC: 12345678901\nDireccion: Av. Ejemplo 123"]] as $row) $aj->execute($row);
        foreach (['General', 'Bebidas', 'Abarrotes'] as $cat) $pdo->prepare('INSERT INTO categorias (nombre) VALUES (?)')->execute([$cat]);
        $pdo->prepare('INSERT INTO campanias (nombre, descripcion, activa_default) VALUES (?,?,1)')->execute(['Campaña General', 'Productos por defecto']);
    }

    private static function folders(): void
    {
        $deny = "<IfModule mod_authz_core.c>\n    Require all denied\n</IfModule>\n<IfModule !mod_authz_core.c>\n    Order allow,deny\n    Deny from all\n</IfModule>\n";
        foreach (['src', 'config', 'views', 'storage'] as $dir) {
            $path = ROOT . "/$dir";
            if (!is_dir($path)) @mkdir($path, 0755, true);
            @file_put_contents("$path/.htaccess", $deny);
        }

        $up = Config::get('upload_dir');
        if (!is_dir($up)) @mkdir($up, 0755, true);
        // Sin scripts en uploads + caché largo para imágenes (sus nombres son aleatorios y no cambian).
        @file_put_contents($up . '.htaccess',
            "<IfModule mod_php.c>\n    php_flag engine off\n</IfModule>\n<IfModule mod_php7.c>\n    php_flag engine off\n</IfModule>\n<IfModule mod_php8.c>\n    php_flag engine off\n</IfModule>\n\n"
            . "<FilesMatch \"\\.(php|phtml|php3|php4|php5|php7|pl|py|cgi|asp|aspx|sh|exe)$\">\n$deny</FilesMatch>\n\n"
            . "<IfModule mod_expires.c>\n    ExpiresActive On\n    ExpiresByType image/webp \"access plus 1 year\"\n    ExpiresByType image/jpeg \"access plus 1 year\"\n    ExpiresByType image/png \"access plus 1 year\"\n    ExpiresByType image/gif \"access plus 1 year\"\n</IfModule>\n\n<IfModule mod_headers.c>\n    Header set Cache-Control \"public, max-age=31536000, immutable\"\n</IfModule>\n");

        @file_put_contents(ROOT . '/.user.ini', "output_buffering = 4096\n");

        $perf = "Options -Indexes\n\n# tienda-perf\n<IfModule mod_deflate.c>\n    AddOutputFilterByType DEFLATE text/html text/css application/javascript application/json image/svg+xml\n</IfModule>\n"
            . "<IfModule mod_headers.c>\n    Header always set X-Content-Type-Options \"nosniff\"\n    Header always set X-Frame-Options \"SAMEORIGIN\"\n    <FilesMatch \"\\.(css|js)$\">\n        Header set Cache-Control \"public, max-age=31536000, immutable\"\n    </FilesMatch>\n</IfModule>\n";
        $ht = ROOT . '/.htaccess';
        $actual = is_file($ht) ? (string)file_get_contents($ht) : '';
        if (!str_contains($actual, '# tienda-perf')) {
            if ($actual === '') {
                $actual = "<IfModule mod_php.c>\n    php_value output_buffering 4096\n</IfModule>\n<IfModule mod_php7.c>\n    php_value output_buffering 4096\n</IfModule>\n<IfModule mod_php8.c>\n    php_value output_buffering 4096\n</IfModule>\n";
            }
            @file_put_contents($ht, rtrim($actual) . "\n\n" . $perf);
        }
    }
}
