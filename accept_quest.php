<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['quest_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in or quest ID not provided.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$quest_id = $_POST['quest_id'];

// Check if the quest is already accepted
$stmt = $conn->prepare("SELECT * FROM user_quests WHERE user_id = :user_id AND quest_id = :quest_id AND status = 'accepted'");
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':quest_id', $quest_id);
$stmt->execute();
$existing_quest = $stmt->fetch(PDO::FETCH_ASSOC);

if ($existing_quest) {
    echo json_encode(['success' => false, 'message' => 'Quest already accepted.']);
    exit();
}

// Insert the quest acceptance
$stmt = $conn->prepare("INSERT INTO user_quests (user_id, quest_id, status) VALUES (:user_id, :quest_id, 'accepted')");
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':quest_id', $quest_id);
$stmt->execute();

echo json_encode(['success' => true]);
?>