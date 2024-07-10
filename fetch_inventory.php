<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch inventory items
$stmt = $conn->prepare("SELECT inv.id as inventory_id, i.id, i.name, i.description, i.type FROM inventory inv JOIN items i ON inv.item_id = i.id WHERE inv.user_id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$inventory_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Fetch equipped items
$stmt = $conn->prepare("SELECT ei.slot, i.id, i.name, i.description FROM equipped_items ei JOIN items i ON ei.item_id = i.id WHERE ei.user_id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$equipped_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true,
    'inventory_items' => $inventory_items,
    'equipped_items' => $equipped_items
]);
?>
