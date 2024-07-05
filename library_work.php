<?php
include 'session.php';
include 'db.php';
include 'character_functions.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch the current work lock time and workplace from the database
$stmt = $conn->prepare("SELECT work_lock_time, current_workplace FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);

$work_lock_time = isset($user['work_lock_time']) ? $user['work_lock_time'] : 0;
$current_time = time();
$lock_duration = 10; // 10 seconds for testing

if ($current_time < $work_lock_time) {
    $remaining_time = $work_lock_time - $current_time;
    echo json_encode(['success' => false, 'message' => "You can't work for another $remaining_time seconds."]);
    exit();
}

// Set new work lock time and current workplace
$new_lock_time = $current_time + $lock_duration;
$current_workplace = 'library';
$stmt = $conn->prepare("UPDATE users SET work_lock_time = :work_lock_time, current_workplace = :current_workplace WHERE id = :user_id");
$stmt->bindParam(':work_lock_time', $new_lock_time);
$stmt->bindParam(':current_workplace', $current_workplace);
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

$character_id = $_SESSION['character_id'];

// Gain XP and money
gainXp($character_id, 20);
gainMoney($character_id, 5);

// Fetch updated character details
$stmt = $conn->prepare("SELECT xp, money, level FROM characters WHERE id = :id");
$stmt->bindParam(':id', $character_id);
$stmt->execute();
$updated_character = $stmt->fetch(PDO::FETCH_ASSOC);

echo json_encode([
    'success' => true, 
    'new_xp' => $updated_character['xp'], 
    'new_money' => $updated_character['money'], 
    'new_level' => $updated_character['level'],
    'lock_duration' => $lock_duration,
    'place' => $current_workplace
]);
?>