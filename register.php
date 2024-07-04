<?php
include 'db.php';
include 'csrf.php';

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid CSRF token.";
        error_log("Invalid CSRF token during registration attempt.");
    } else {
        $username = filter_input(INPUT_POST, 'username', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $email = filter_input(INPUT_POST, 'email', FILTER_VALIDATE_EMAIL);
        $password = $_POST['password'];

        if (empty($username) || empty($email) || empty($password)) {
            $error = "All fields are required.";
        } elseif (!$email) {
            $error = "Invalid email address.";
        } elseif (strlen($username) < 5) {
            $error = "Username must be at least 5 characters.";
        } elseif (strlen($password) < 8 || !preg_match("/\d/", $password)) {
            $error = "Password must be at least 8 characters and include a number.";
        } else {
            $hashed_password = password_hash($password, PASSWORD_BCRYPT);

            try {
                $stmt = $conn->prepare("INSERT INTO users (username, email, password) VALUES (:username, :email, :password)");
                $stmt->bindParam(':username', $username);
                $stmt->bindParam(':email', $email);
                $stmt->bindParam(':password', $hashed_password);
                $stmt->execute();

                error_log("Successful registration for user: $username");

                // Clear session and start a new one to prevent conflicts
                session_unset();
                session_destroy();

                header('Location: index.php?registered=true');
                exit();
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) { // Integrity constraint violation
                    $error = "Username or email already taken.";
                } else {
                    $error = "An error occurred: " . $e->getMessage();
                }
                error_log("Failed registration attempt for user: $username. Error: " . $e->getMessage());
            }
        }
    }
}

// Start session again to generate CSRF token
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="scripts.js"></script>
</head>
<body>
<div class="container">
    <h2>Register</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
    <?php endif; ?>
    <form id="registerForm" method="post" action="register.php">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <div class="form-group">
            <label for="username">Username:</label>
            <input type="text" class="form-control" id="username" name="username" required>
        </div>
        <div class="form-group">
            <label for="email">Email:</label>
            <input type="email" class="form-control" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="password">Password:</label>
            <input type="password" class="form-control" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">Register</button>
        <a href="index.php" class="btn btn-secondary">Back to Login</a>
        <div class="form-group">
            <div class="error-container"></div> <!-- Centralized error message container -->
        </div>
    </form>
</div>
</body>
</html>