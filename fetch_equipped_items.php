<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];

$stmt = $conn->prepare("
    SELECT ei.slot, i.name, i.description, ei.id 
    FROM equipped_items ei 
    JOIN items i ON ei.item_id = i.id 
    WHERE ei.user_id = :user_id
");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$equipped_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo json_encode(['success' => true, 'equipped_items' => $equipped_items]);
?>