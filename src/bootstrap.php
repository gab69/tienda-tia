<?php
define('ROOT', dirname(__DIR__));
error_reporting(E_ALL);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Compresión gzip (si el servidor la soporta) + búfer que absorbe bytes invisibles.
if (extension_loaded('zlib') && !ini_get('zlib.output_compression')) ob_start('ob_gzhandler'); else ob_start();

// Autocarga PSR-4 simple: App\Foo\Bar -> src/Foo/Bar.php
spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'App\\')) {
        $file = ROOT . '/src/' . str_replace('\\', '/', substr($class, 4)) . '.php';
        if (is_file($file)) require $file;
    }
});

App\Errors::register();
App\Config::load(ROOT . '/config/config.php');
App\Session::start();
App\Installer::ensure();
