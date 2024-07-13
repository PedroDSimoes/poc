<?php
$url = getenv('DATABASE_URL');
if ($url) {
    $dbparts = parse_url($url);

    $hostname = $dbparts['host'];
    $username = $dbparts['user'];
    $password = $dbparts['pass'];
    $database = ltrim($dbparts['path'],'/');
} else {
    // Fallback to local database settings for development
    $hostname = 'localhost';
    $username = 'root';
    $password = '';
    $database = 'game_db';
}

try {
    $conn = new PDO("pgsql:host=$hostname;dbname=$database", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
