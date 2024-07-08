<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['quest_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in or quest ID not provided.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$quest_id = $_POST['quest_id'];

try {
    $conn->beginTransaction();

    // Check if the quest is already accepted and not yet completed
    $stmt = $conn->prepare("SELECT * FROM user_quests WHERE user_id = :user_id AND quest_id = :quest_id AND status = 'accepted'");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':quest_id', $quest_id);
    $stmt->execute();
    $quest = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$quest) {
        throw new Exception('Quest not accepted or already completed.');
    }

    // Check if the user has the Cooking Pot in their inventory
    $stmt = $conn->prepare("SELECT * FROM inventory WHERE user_id = :user_id AND item_id = (SELECT id FROM items WHERE name = 'Cooking Pot')");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Cooking Pot not found in inventory.');
    }

    // Remove the Cooking Pot from inventory
    $stmt = $conn->prepare("DELETE FROM inventory WHERE id = :id");
    $stmt->bindParam(':id', $item['id']);
    $stmt->execute();

    // Update quest status to completed
    $stmt = $conn->prepare("UPDATE user_quests SET status = 'completed' WHERE id = :id");
    $stmt->bindParam(':id', $quest['id']);
    $stmt->execute();

    // Reward the player with XP
    $stmt = $conn->prepare("UPDATE characters SET xp = xp + 100 WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>