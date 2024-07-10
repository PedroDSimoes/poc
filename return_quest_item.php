<?php
include 'session.php';
include 'db.php';
include 'character_functions.php';

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

    // Get character ID
    $stmt = $conn->prepare("SELECT id FROM characters WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
    $character_id = $character['id'];

    // Update character XP and check for level up
    $success = gainXp($character_id, $reward_xp);
    if (!$success) {
        throw new Exception("Failed to update character XP and level.");
    }

    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>