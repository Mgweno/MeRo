<?php
$host = 'mysql-29497d54-mcnoopar-ee3c.e.aivencloud.com';
$port = '28668';
$dbname = 'defaultdb';
$username = 'avnadmin';
$password = 'AVNS_aVZpPralnEh3REL5ZP';

try {
    $dsn = "mysql:host=$host;port=$port;dbname=$dbname;charset=utf8mb4";
    
    // Aiven requires SSL connection options
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_SSL_CA => __DIR__ . '/ca.pem', // Optional if you download the CA certificate, but usually works with options below
    ];

    // Alternatively, if you just want to connect without managing the certificate file locally right away:
    $pdo = new PDO($dsn, $username, $password, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
    ]);

} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
