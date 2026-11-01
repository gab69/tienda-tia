<?php
use App\{Database, Session};
use App\Repository\{SettingRepository, CampaignRepository};

// Todo lo que la portada necesita se incrusta en el HTML: el navegador arranca sin peticiones extra.
$db = Database::pdo();
$campaigns = new CampaignRepository($db);
$boot = [
    'config' => (new SettingRepository($db))->config(),
    'session' => Session::payload(),
    'campanias' => $campaigns->active(),
    'default' => ['id' => $campaigns->defaultId()],
];
header('Cache-Control: no-cache, private'); // incrusta la sesión: siempre se revalida

$e = fn($s) => htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
$nombre = $boot['config']['nombre_pagina'];
$logo = $boot['config']['logo'];
$v = fn($f) => filemtime(ROOT . "/assets/$f"); // versión = fecha del archivo: caché largo sin quedarse viejo
$fonts = 'https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:wght@600;700;800&family=DM+Sans:wght@400;500;600;700&display=swap';
?><!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="theme-color" content="#1d4ed8">
<link id="favicon" rel="icon" href="<?= $logo ? $e($logo) : 'data:,' ?>">
<title><?= $e($nombre) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="<?= $fonts ?>" media="print" onload="this.media='all'">
<noscript><link rel="stylesheet" href="<?= $fonts ?>"></noscript>
<link rel="stylesheet" href="assets/app.css?v=<?= $v('app.css') ?>">
</head>
<body>

<header class="top">
  <div class="top-in">
    <a class="brand" href="?action=home">
      <?php if ($logo): ?><img src="<?= $e($logo) ?>" alt="Logo" id="logoBox"><?php else: ?><div id="logoBox" class="ph-logo">T</div><?php endif; ?>
      <span id="nombrePagina"><?= $e($nombre) ?></span>
    </a>
    <div class="top-act">
      <a class="btn btn-soft" id="btnAdmin" style="display:none" href="?action=admin"><span class="hide-m">Panel admin</span><svg class="i" viewBox="0 0 24 24"><rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/></svg></a>
      <button class="btn btn-line" id="btnLogin" onclick="abrirLogin()">Ingresar</button>
      <button class="btn btn-line" id="btnLogout" style="display:none" onclick="logout()">Salir</button>
      <button class="btn btn-pri cart-btn" onclick="abrirCarrito()" aria-label="Abrir carrito">
        <svg class="i" viewBox="0 0 24 24"><path d="M6 6h15l-1.5 9h-12z"/><path d="M6 6 5 3H2"/><circle cx="9" cy="20" r="1.5"/><circle cx="18" cy="20" r="1.5"/></svg>
        <span class="hide-m">Carrito</span><span class="badge" id="badgeCarrito"></span>
      </button>
    </div>
  </div>
</header>

<main id="app"></main>

<!-- Detalle de producto -->
<div class="overlay" id="modalProducto"><div class="sheet" id="modalProductoCont"></div></div>

<!-- Imagen ampliada -->
<div class="overlay" id="lightbox" onclick="cerrarLightbox()"><img id="lightboxImg" src="" alt="Imagen ampliada"></div>

<!-- Ingreso -->
<div class="overlay" id="modalLogin">
  <div class="sheet sm">
    <button class="x" onclick="cerrarLogin()" aria-label="Cerrar">✕</button>
    <h2>Ingresar</h2>
    <div class="fld"><label for="loginUsuario">Usuario</label><input type="text" id="loginUsuario" autocomplete="username" maxlength="50"></div>
    <div class="fld"><label for="loginPassword">Contraseña</label><input type="password" id="loginPassword" autocomplete="current-password"></div>
    <button class="btn btn-pri btn-block" onclick="hacerLogin()">Ingresar</button>
  </div>
</div>

<!-- Carrito (cajón lateral) -->
<div class="overlay drawer-ov" id="modalCarrito">
  <aside class="drawer">
    <header><h2>Tu carrito</h2><button class="x" onclick="cerrarCarrito()" aria-label="Cerrar">✕</button></header>
    <div id="carritoCont" style="display:flex;flex-direction:column;flex:1;min-height:0"></div>
  </aside>
</div>

<div id="toast" role="status"></div>
<div id="printArea" style="display:none"></div>

<script>window.__BOOT = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE) ?>;</script>
<script src="assets/app.js?v=<?= $v('app.js') ?>" defer></script>
</body>
</html>
