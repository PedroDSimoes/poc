<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['quest_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in or quest ID not provided.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$quest_id = $_POST['quest_id'];

// Check if the quest is in progress
$stmt = $conn->prepare("SELECT * FROM user_quests WHERE user_id = :user_id AND quest_id = :quest_id AND status = 'in_progress'");
$stmt->bindParam(':user_id', $user_id);
$stmt->bindParam(':quest_id', $quest_id);
$stmt->execute();
$quest = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quest) {
    echo json_encode(['success' => false, 'message' => 'Quest not in progress.']);
    exit();
}

try {
    $conn->beginTransaction();

    // Update quest status to completed
    $stmt = $conn->prepare("UPDATE user_quests SET status = 'completed' WHERE id = :id");
    $stmt->bindParam(':id', $quest['id']);
    $stmt->execute();

    // Fetch the quest reward XP
    $stmt = $conn->prepare("SELECT reward_xp FROM quests WHERE id = :quest_id");
    $stmt->bindParam(':quest_id', $quest_id);
    $stmt->execute();
    $quest_reward = $stmt->fetch(PDO::FETCH_ASSOC);
    $reward_xp = $quest_reward['reward_xp'];

    // Update character XP
    $stmt = $conn->prepare("UPDATE characters SET xp = xp + :reward_xp WHERE user_id = :user_id");
    $stmt->bindParam(':reward_xp', $reward_xp);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>