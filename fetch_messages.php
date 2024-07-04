<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['recipient_id'])) {
    exit();
}

$user_id = $_SESSION['user_id'];
$recipient_id = $_POST['recipient_id'];

// Fetch messages between the logged-in user and the recipient
$stmt = $conn->prepare("
    SELECT m.*, u.username as sender_name
    FROM messages m
    JOIN users u ON m.sender_id = u.id
    WHERE (m.sender_id = :user_id AND m.receiver_id = :recipient_id)
       OR (m.sender_id = :recipient_id AND m.receiver_id = :user_id)
    ORDER BY m.created_at ASC
");
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':recipient_id', $recipient_id);
$stmt->execute();
$messages = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Display messages
foreach ($messages as $message) {
    $align = $message['sender_id'] == $user_id ? 'text-right' : 'text-left';
    echo "<div class='message {$align}'>";
    echo "<strong>{$message['sender_name']}:</strong> " . htmlspecialchars($message['message']);
    echo "<br><small class='text-muted'>" . htmlspecialchars($message['created_at']) . "</small>";
    echo "</div>";
}
?>
