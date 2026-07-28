<?php
$host = 'sql300.infinityfree.com';
$dbname = 'retail_db'; // Replace with your complete database name
$username = 'if0_42521402';
$password = 'n6AXDxQj4fLKYgx';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}
?>
