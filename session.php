<?php
// Configure secure session parameters
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_httponly', 1);
ini_set('session.cookie_secure', 1);
session_start();

// Regenerate session ID to prevent session fixation
if (!isset($_SESSION['initiated'])) {
    session_regenerate_id(true);
    $_SESSION['initiated'] = true;
}

// Implement session timeout for main.php only
if (basename($_SERVER['PHP_SELF']) == 'main.php') {
    $timeout_duration = 21600; // For testing purposes, set to 5 seconds

    if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $timeout_duration) {
        session_unset();
        session_destroy();
        header('Location: index.php?timeout=true');
        exit();
    }

    $_SESSION['last_activity'] = time();
}
?>