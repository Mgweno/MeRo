<?php
$host = 'mysql-29497d54-mcnoopar-ee3c.e.aivencloud.com';
$port = '28668';
$dbname = 'defaultdb';
$username = 'avnadmin';
$password = 'AVNS_aVZpPralnEh3REL5ZP';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    // Aiven requires SSL certificate verification
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => __DIR__ . '/ca.pem',
        PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT => false,
    ];

    $pdo = new PDO($dsn, $username, $password, $options);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
