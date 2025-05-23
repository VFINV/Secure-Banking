<?php
$serverName = getenv('DB_SERVER') ?: 'localhost'; // Apache Web Server from XAMMP
$databaseName = getenv('DB_DATABASE') ?: 'Secure Banking'; // database from SSMS
$username =  getenv('DB_USERNAME') ?: ''; // no username or password for this DB
$password = getenv('DB_PASSWORD') ?: '';

try {
    $conn = new PDO(
        "sqlsrv:server=$serverName;Database=$databaseName",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, // ensures errors throw exceptions
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC, // makes queries return associative arrays by default.
        ]
    );

    echo "<script>console.log('Connection successful!');</script>";

} catch (PDOException $e) {
    echo 'Connection Failed!';
    echo "<script>console.log('Connection  failed');</script>";
    error_log("Database connection error: " . $e->getMessage());
};
