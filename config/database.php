<?php
$host = 'localhost'; // db server
$user = 'root'; // db username
$password = '';
$dbname = 'quickserve'; // db name

try {
    // Create new PDO instance for MySQL connection
    $pdo = new PDO('mysql:host=' . $host . ';dbname=' . $dbname, $user, $password);

    // Set PDO to throw exceptions on error
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Set default fetch mode to associative array 
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // If connection fails → stop script and show error message
    die("Database connection failed: " . $e->getMessage());
}
?>