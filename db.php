<?php
$host = 'mysql-29497d54-mcnoopar-ee3c.e.aivencloud.com';
$port = '28668';
$dbname = 'defaultdb';
$username = 'avnadmin';
$password = 'AVNS_aVZpPralnEh3REL5ZP';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    // SSL options required by Aiven MySQL
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => true,
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
