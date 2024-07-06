<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['item_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in or item ID not provided.']);
    exit();
}

$user_id = $_SESSION['user_id'];
$item_id = $_POST['item_id'];

// Fetch the item details
$stmt = $conn->prepare("SELECT * FROM items WHERE id = :item_id");
$stmt->bindParam(':item_id', $item_id);
$stmt->execute();
$item = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    echo json_encode(['success' => false, 'message' => 'Item not found.']);
    exit();
}

// Fetch the user's money
$stmt = $conn->prepare("SELECT money FROM characters WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$character = $stmt->fetch(PDO::FETCH_ASSOC);

if ($character['money'] < $item['price']) {
    echo json_encode(['success' => false, 'message' => 'Not enough money to buy this item.']);
    exit();
}

// Deduct money and add item to inventory
try {
    $conn->beginTransaction();

    $stmt = $conn->prepare("UPDATE characters SET money = money - :price WHERE user_id = :user_id");
    $stmt->bindParam(':price', $item['price']);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $stmt = $conn->prepare("INSERT INTO inventory (user_id, item_id) VALUES (:user_id, :item_id)");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->bindParam(':item_id', $item_id);
    $stmt->execute();

    $conn->commit();

    // Fetch updated money
    $stmt = $conn->prepare("SELECT money FROM characters WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $updated_character = $stmt->fetch(PDO::FETCH_ASSOC);

    echo json_encode([
        'success' => true,
        'new_money' => $updated_character['money'],
        'item_name' => $item['name']
    ]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>