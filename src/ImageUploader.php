<?php
namespace App;

final class ImageUploader
{
    private const ALLOWED = ['jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp'];
    private const MAX_PIXELS = 40_000_000; // una imagen liviana puede ocupar GB de memoria al descomprimirse

    /**
     * Valida (extensión + contenido real), corrige la orientación, redimensiona y guarda en WebP.
     * Con $thumb crea además la miniatura img_xxx_t.webp para el catálogo. Devuelve [ok, rutaOMensaje].
     */
    public static function store(array $file, bool $thumb = true): array
    {
        if (($file['error'] ?? -1) !== UPLOAD_ERR_OK) return [false, 'Error al subir el archivo'];
        if ($file['size'] > Config::get('img_max_bytes')) return [false, 'La imagen supera el tamaño máximo (5MB)'];

        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset(self::ALLOWED[$ext])) return [false, 'Formato no permitido. Usa jpg, png, gif o webp'];

        $info = @getimagesize($file['tmp_name']);
        if ($info === false || !in_array($info['mime'], self::ALLOWED, true)) return [false, 'El archivo no es una imagen válida'];
        if ($info[0] * $info[1] > self::MAX_PIXELS) return [false, 'La imagen tiene demasiados píxeles. Redúcela e inténtalo de nuevo'];

        $dir = Config::get('upload_dir');
        $url = Config::get('upload_url');
        if (!is_dir($dir)) @mkdir($dir, 0755, true);

        // Sin GD/WebP: se guarda el archivo validado tal cual.
        if (!function_exists('imagewebp') || !function_exists('imagecreatetruecolor')) {
            $name = 'img_' . bin2hex(random_bytes(8)) . '.' . $ext;
            return @move_uploaded_file($file['tmp_name'], $dir . $name) ? [true, $url . $name] : [false, 'No se pudo guardar la imagen'];
        }

        $src = match ($info['mime']) {
            'image/jpeg' => @imagecreatefromjpeg($file['tmp_name']),
            'image/png'  => @imagecreatefrompng($file['tmp_name']),
            'image/gif'  => @imagecreatefromgif($file['tmp_name']),
            'image/webp' => @imagecreatefromwebp($file['tmp_name']),
            default => null,
        };
        if (!$src) return [false, 'No se pudo procesar la imagen'];

        imagepalettetotruecolor($src);
        imagealphablending($src, true);
        imagesavealpha($src, true);
        if ($info['mime'] === 'image/jpeg') $src = self::uprighted($src, $file['tmp_name']);

        $image = self::fit($src, (int)Config::get('img_max_width'));
        $base = 'img_' . bin2hex(random_bytes(8));
        $quality = (int)Config::get('img_webp_quality');
        if (!@imagewebp($image, $dir . $base . '.webp', $quality)) return [false, 'No se pudo convertir la imagen a WebP'];
        if ($thumb) @imagewebp(self::fit($image, (int)Config::get('img_thumb_width', 480)), $dir . $base . '_t.webp', $quality);
        return [true, $url . $base . '.webp'];
    }

    /** Borra una imagen subida y su miniatura. Ignora rutas fuera de la carpeta de subidas. */
    public static function delete(string $path): void
    {
        $base = realpath(Config::get('upload_dir'));
        $local = realpath(ROOT . '/' . $path);
        if (!$base || !$local || !str_starts_with($local, $base . DIRECTORY_SEPARATOR)) return;
        @unlink($local);
        @unlink(preg_replace('/\.webp$/', '_t.webp', $local));
    }

    /** Las fotos de celular guardan el giro en EXIF, y al pasar a WebP ese dato se pierde. */
    private static function uprighted(\GdImage $img, string $path): \GdImage
    {
        if (!function_exists('exif_read_data')) return $img;
        $angle = [3 => 180, 6 => -90, 8 => 90][(@exif_read_data($path) ?: [])['Orientation'] ?? 1] ?? 0;
        if ($angle === 0) return $img;
        return imagerotate($img, $angle, 0) ?: $img;
    }

    /** Reduce la imagen para que su lado mayor no pase de $max, sin agrandarla nunca. */
    private static function fit(\GdImage $img, int $max): \GdImage
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $ratio = $max / max($w, $h);
        if ($ratio >= 1) return $img;
        $nw = max(1, (int)round($w * $ratio));
        $nh = max(1, (int)round($h * $ratio));
        $dst = imagecreatetruecolor($nw, $nh);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);
        return $dst;
    }
}
