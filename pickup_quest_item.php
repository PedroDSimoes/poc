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
$quest = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$quest) {
    echo json_encode(['success' => false, 'message' => 'Quest not accepted.']);
    exit();
}

try {
    $conn->beginTransaction();

    // Update quest status to in_progress
    $stmt = $conn->prepare("UPDATE user_quests SET status = 'in_progress' WHERE id = :id");
    $stmt->bindParam(':id', $quest['id']);
    $stmt->execute();

    // Get the item_id for the Cooking Pot
    $stmt = $conn->prepare("SELECT id FROM items WHERE name = 'Cooking Pot'");
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        throw new Exception('Cooking Pot item not found.');
    }
    $item_id = $item['id'];

    // Add quest item to inventory
    $stmt = $conn->prepare("INSERT INTO inventory (user_id, item_id) VALUES (:user_id, :item_id)");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':item_id', $item_id);
    $stmt->execute();

    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>