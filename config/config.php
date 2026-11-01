<?php
// Configuración central. ¡Ajusta la base de datos a tu hosting!
return [
    'db' => ['host' => 'localhost', 'name' => 'tienda_db', 'user' => 'root', 'pass' => ''],
    'upload_dir'       => dirname(__DIR__) . '/uploads/',
    'upload_url'       => 'uploads/',
    'img_max_bytes'    => 5 * 1024 * 1024,
    'img_max_width'    => 1600,
    'img_thumb_width'  => 480,
    'img_webp_quality' => 82,
    'page_size'        => 40,
];
