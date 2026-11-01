<?php
namespace App;

use PDO;
use PDOException;

final class Database
{
    private const UNKNOWN_DATABASE = 1049;

    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo) return self::$pdo;
        try {
            self::$pdo = self::connect(true);
        } catch (PDOException $e) {
            if ($e->getCode() !== self::UNKNOWN_DATABASE) throw $e;
            // El candado existe pero la base no (se borró o se movió el proyecto): se vuelve a crear.
            Installer::install(true);
            self::$pdo ??= self::connect(true);
        }
        return self::$pdo;
    }

    public static function connect(bool $withDb): PDO
    {
        $c = Config::get('db');
        $dsn = "mysql:host={$c['host']};" . ($withDb ? "dbname={$c['name']};" : '') . 'charset=utf8mb4';
        return new PDO($dsn, $c['user'], $c['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::ATTR_TIMEOUT => 5,
        ]);
    }
}
