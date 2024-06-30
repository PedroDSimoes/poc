<?php
function generate_csrf_token() {
    $csrf_lifetime = 6 * 3600; // 6 hours in seconds

    if (empty($_SESSION['csrf_token']) || empty($_SESSION['csrf_token_time']) || time() - $_SESSION['csrf_token_time'] > $csrf_lifetime) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        $_SESSION['csrf_token_time'] = time();
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf_token($token) {
    $csrf_lifetime = 6 * 3600; // 6 hours in seconds

    if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time'])) {
        return false;
    }

    // Check if token is older than the CSRF lifetime
    if (time() - $_SESSION['csrf_token_time'] > $csrf_lifetime) {
        unset($_SESSION['csrf_token']);
        unset($_SESSION['csrf_token_time']);
        return false;
    }

    return hash_equals($_SESSION['csrf_token'], $token);
}
?>