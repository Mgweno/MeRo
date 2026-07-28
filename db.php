<?php

$host = "sql300.infinityfree.com";
$user = "if0_42521402";
$password = "n6AXDxQj4fLKYgx";
$database = "if0_42521402_retail_db";

$conn = new mysqli($host, $user, $password, $database);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>
