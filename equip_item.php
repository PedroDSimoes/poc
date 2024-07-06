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

    // Remove any existing item in the slot and move it back to inventory
    $stmt = $conn->prepare("SELECT id, item_id FROM equipped_items WHERE user_id = :user_id AND slot = :slot");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':slot', $slot);
    $stmt->execute();
    $equipped_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($equipped_item) {
        $stmt = $conn->prepare("INSERT INTO inventory (user_id, item_id) VALUES (:user_id, :item_id)");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':item_id', $equipped_item['item_id']);
        $stmt->execute();

        $stmt = $conn->prepare("DELETE FROM equipped_items WHERE id = :id");
        $stmt->bindParam(':id', $equipped_item['id']);
        $stmt->execute();
    }

    // Remove the item from inventory
    $stmt = $conn->prepare("DELETE FROM inventory WHERE user_id = :user_id AND item_id = :item_id LIMIT 1");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':item_id', $item_id);
    $stmt->execute();

    // Equip the new item
    $stmt = $conn->prepare("INSERT INTO equipped_items (user_id, item_id, slot) VALUES (:user_id, :item_id, :slot)");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':item_id', $item_id);
    $stmt->bindParam(':slot', $slot);
    $stmt->execute();

    $conn->commit();
    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>