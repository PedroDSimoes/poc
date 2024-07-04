<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['recipient_id']) || !isset($_POST['message'])) {
    exit();
}

$user_id = $_SESSION['user_id'];
$recipient_id = $_POST['recipient_id'];
$message = trim($_POST['message']);

if ($message !== '') {
    $stmt = $conn->prepare("
        INSERT INTO messages (sender_id, receiver_id, message)
        VALUES (:sender_id, :receiver_id, :message)
    ");
    $stmt->bindParam(':sender_id', $user_id);
    $stmt->bindParam(':receiver_id', $recipient_id);
    $stmt->bindParam(':message', $message);
    $stmt->execute();
}
?>
