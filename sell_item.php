<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['item_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in or item ID not provided.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$item_id = $_POST['item_id'];

try {
    $conn->beginTransaction();

    // Fetch the item details
    $stmt = $conn->prepare("SELECT * FROM items WHERE id = :item_id");
    $stmt->bindParam(':item_id', $item_id);
    $stmt->execute();
    $item = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$item) {
        throw new Exception('Item not found.');
    }

    // Calculate the selling price (50% of the item price)
    $selling_price = $item['price'] / 2;

    // Remove the item from the inventory
    $stmt = $conn->prepare("DELETE FROM inventory WHERE user_id = :user_id AND item_id = :item_id LIMIT 1");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':item_id', $item_id);
    $stmt->execute();

    if ($stmt->rowCount() === 0) {
        throw new Exception('Item not found in inventory.');
    }

    // Update the character's money
    $stmt = $conn->prepare("UPDATE characters SET money = money + :selling_price WHERE user_id = :user_id");
    $stmt->bindParam(':selling_price', $selling_price);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    // Fetch updated money
    $stmt = $conn->prepare("SELECT money FROM characters WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $updated_character = $stmt->fetch(PDO::FETCH_ASSOC);

    $conn->commit();

    echo json_encode([
        'success' => true,
        'new_money' => $updated_character['money'],
        'item_name' => $item['name'],
        'selling_price' => $selling_price
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>