<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['item_id']) || !isset($_POST['slot']) || !isset($_POST['inventory_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in or item ID/slot/inventory ID not provided.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$item_id = $_POST['item_id'];
$slot = $_POST['slot'];
$inventory_id = $_POST['inventory_id'];

try {
    $conn->beginTransaction();

    // Remove any existing item in the slot and move it back to inventory
    $stmt = $conn->prepare("SELECT item_id, id FROM equipped_items WHERE user_id = :user_id AND slot = :slot");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':slot', $slot);
    $stmt->execute();
    $equipped_item = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($equipped_item) {
        // Get stats of the currently equipped item
        $stmt = $conn->prepare("SELECT damage, armor FROM items WHERE id = :item_id");
        $stmt->bindParam(':item_id', $equipped_item['item_id']);
        $stmt->execute();
        $equipped_item_stats = $stmt->fetch(PDO::FETCH_ASSOC);

        // Remove stats of the currently equipped item
        $stmt = $conn->prepare("UPDATE characters SET damage = damage - :damage, armor = armor - :armor WHERE user_id = :user_id");
        $stmt->bindParam(':damage', $equipped_item_stats['damage']);
        $stmt->bindParam(':armor', $equipped_item_stats['armor']);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        // Remove the currently equipped item
        $stmt = $conn->prepare("DELETE FROM equipped_items WHERE user_id = :user_id AND slot = :slot");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':slot', $slot);
        $stmt->execute();

        // Add the currently equipped item back to inventory
        $stmt = $conn->prepare("INSERT INTO inventory (user_id, item_id) VALUES (:user_id, :item_id)");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->bindParam(':item_id', $equipped_item['item_id']);
        $stmt->execute();
    }

    // Get stats of the new item to be equipped
    $stmt = $conn->prepare("SELECT damage, armor FROM items WHERE id = :item_id");
    $stmt->bindParam(':item_id', $item_id);
    $stmt->execute();
    $item_stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Add stats of the new item to the character
    $stmt = $conn->prepare("UPDATE characters SET damage = damage + :damage, armor = armor + :armor WHERE user_id = :user_id");
    $stmt->bindParam(':damage', $item_stats['damage']);
    $stmt->bindParam(':armor', $item_stats['armor']);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    // Remove the new item from inventory using the specific inventory ID
    $stmt = $conn->prepare("DELETE FROM inventory WHERE id = :inventory_id");
    $stmt->bindParam(':inventory_id', $inventory_id);
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