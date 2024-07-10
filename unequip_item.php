<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['item_id']) || !isset($_POST['slot'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in or item ID/slot not provided.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$item_id = $_POST['item_id'];
$slot = $_POST['slot'];

try {
    $conn->beginTransaction();

    // Check if the item is equipped by the user
    $stmt = $conn->prepare("SELECT * FROM equipped_items WHERE user_id = :user_id AND item_id = :item_id AND slot = :slot LIMIT 1");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':item_id', $item_id);
    $stmt->bindParam(':slot', $slot);
    $stmt->execute();
    $equipped_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$equipped_item) {
        throw new Exception('Item not equipped by user.');
    }

    // Get stats of the item to be unequipped
    $stmt = $conn->prepare("SELECT damage, armor FROM items WHERE id = :item_id");
    $stmt->bindParam(':item_id', $item_id);
    $stmt->execute();
    $item_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Remove stats of the unequipped item
    $stmt = $conn->prepare("UPDATE characters SET damage = damage - :damage, armor = armor - :armor WHERE user_id = :user_id");
    $stmt->bindParam(':damage', $item_stats['damage']);
    $stmt->bindParam(':armor', $item_stats['armor']);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    // Remove the item from equipped items
    $stmt = $conn->prepare("DELETE FROM equipped_items WHERE user_id = :user_id AND item_id = :item_id AND slot = :slot LIMIT 1");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':item_id', $item_id);
    $stmt->bindParam(':slot', $slot);
    $stmt->execute();

    // Add the item back to inventory
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