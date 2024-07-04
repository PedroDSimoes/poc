<?php
include 'session.php';
include 'db.php';
include 'csrf.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$error = '';

// Handle session timeout and clear session if needed
if (isset($_GET['timeout']) && $_GET['timeout'] == 'true') {
    session_unset();
    session_destroy();
    session_start(); // Start a new session for the login attempt
}

// Handle registration success message
if (isset($_GET['registered']) && $_GET['registered'] == 'true') {
    session_unset();
    session_destroy();
    session_start();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid CSRF token.";
    } else {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $password = $_POST['password'];

        if (empty($username) || empty($password)) {
            $error = "Username and password are required.";
        } else {
            try {
                $stmt = $conn->prepare("SELECT * FROM users WHERE username = :username");
                $stmt->bindParam(':username', $username);
                $stmt->execute();
                $user = $stmt->fetch(PDO::FETCH_ASSOC);

                if ($user && password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['last_activity'] = time(); // Set last activity time
                    error_log("Successful login attempt for user: $username");
                    header('Location: main.php');
                    exit();
                } else {
                    $error = "Invalid username or password.";
                    error_log("Failed login attempt for user: $username");
                }
            } catch (PDOException $e) {
                $error = "An error occurred: " . $e->getMessage();
                error_log("Failed login attempt for user: $username. Error: " . $e->getMessage());
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="scripts.js"></script>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const params = new URLSearchParams(window.location.search);
            if (params.has('timeout') && params.get('timeout') === 'true') {
                params.delete('timeout');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
            if (params.has('registered') && params.get('registered') === 'true') {
                params.delete('registered');
                window.history.replaceState({}, document.title, window.location.pathname);
            }
        });
    </script>
</head>
<body>
<div class="container">
    <h2>Login</h2>
    <?php if (isset($_GET['timeout']) && $_GET['timeout'] == 'true'): ?>
        <div class="alert alert-warning" role="alert">Your session has timed out. Please log in again.</div>
    <?php endif; ?>
    <?php if (isset($_GET['registered']) && $_GET['registered'] == 'true'): ?>
        <div class="alert alert-success" role="alert">Registration successful. Please log in.</div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
    <?php endif; ?>
    <form id="loginForm" method="post" action="index.php">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Login</button>
        <div class="form-group">
            <div class="error-container"></div> <!-- Centralized error message container -->
        </div>
    </form>
    <a href="register.php">Register here</a>
</div>
</body>
</html>