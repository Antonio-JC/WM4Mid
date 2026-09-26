<?php
declare(strict_types=1);

final class Database
{
    private static ?PDO $instance = null;

    private const HOST = 'sql301.infinityfree.com';
    private const DB   = 'if0_43016075_rentalDB';
    private const USER = 'if0_43016075';
    private const PASS = 'xFreehostCS123';
    private const CHARSET = 'utf8mb4';

    private function __construct() {}

    public static function connection(): PDO
    {
        if (self::$instance === null) {
            $dsn = sprintf(
                'mysql:host=%s;dbname=%s;charset=%s',
                self::HOST,
                self::DB,
                self::CHARSET
            );

            self::$instance = new PDO(self::HOST !== '' ? $dsn : $dsn, self::USER, self::PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        }

        return self::$instance;
    }
}
