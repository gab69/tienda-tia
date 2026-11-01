TIENDA - estructura limpia por capas
====================================
index.php            Punto de entrada (enruta API y vista)
config/config.php    Base de datos y opciones (EDITA AQUÍ tus datos de hosting)
src/
  bootstrap.php      Arranque: gzip, autocarga, sesión, instalador
  Router.php         Tabla de rutas: acción -> controlador
  Controller/        Reciben la petición y responden (delgados)
  Repository/        Todo el SQL vive aquí (una clase por tabla/tema)
  Errors.php         Respuesta clara ante cualquier fallo (y registro en el log)
  Installer.php      Crea tablas e índices UNA vez (storage/installed.lock)
  ImageUploader.php  Valida, redimensiona, crea miniatura y convierte a WebP
views/layout.php     Página HTML (incrusta config, sesión y campañas)
assets/app.css|js    Estilos y lógica del navegador (con caché largo)

Instalación: sube todo, edita config/config.php y abre index.php.
Usuario inicial: admin / admin123 (cámbialo al entrar).
Si ya tenías la base de datos, se conserva; los índices nuevos se crean solos.
Si la base de datos falta, se vuelve a crear sola al abrir la tienda.
Para volver a ejecutar el instalador/migración: borra storage/installed.lock
Requiere PHP 8.0 o superior.
