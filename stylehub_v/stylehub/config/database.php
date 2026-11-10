<?php
/**
 * Koneksi database memakai PDO.
 * Sesuaikan 4 konstanta di bawah dengan server lokal Anda (XAMPP default: root tanpa password).
 */
define('DB_HOST', 'localhost');
define('DB_NAME', 'dbstylehub');
define('DB_USER', 'root');
define('DB_PASS', '');

function db()
{
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,   // error SQL jadi exception
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,                    // prepared statement asli
                // Samakan collation koneksi dengan database (utf8mb4_unicode_ci) supaya tidak error 1267
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci",
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            exit('Koneksi database gagal. Pastikan MySQL menyala dan database "dbstylehub" sudah diimpor. '
                . 'Detail: ' . htmlspecialchars($e->getMessage()));
        }
    }
    return $pdo;
}