<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'User not logged in.']);
    exit();
}

$user_id = $_SESSION['user_id'];

$strength = isset($_POST['strength']) ? intval($_POST['strength']) : 0;
$dexterity = isset($_POST['dexterity']) ? intval($_POST['dexterity']) : 0;
$constitution = isset($_POST['constitution']) ? intval($_POST['constitution']) : 0;
$negotiation = isset($_POST['negotiation']) ? intval($_POST['negotiation']) : 0;

$total_points = $strength + $dexterity + $constitution + $negotiation;

if ($total_points > 5) {
    echo json_encode(['success' => false, 'message' => 'You cannot allocate more than 5 points.']);
    exit();
}

try {
    $conn->beginTransaction();

    // Get the character ID and current unallocated points
    $stmt = $conn->prepare("SELECT id, unallocated_points, constitution FROM characters WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();
    $character = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$character || $character['unallocated_points'] < $total_points) {
        throw new Exception("Invalid unallocated points.");
    }

    $character_id = $character['id'];
    $current_constitution = $character['constitution'];
    $new_constitution = $current_constitution + $constitution;
    $unallocated_points = $character['unallocated_points'] - $total_points;

    // Calculate the new HP based on the updated constitution
    $base_hp = 50;
    $hp_increment_per_point = 5;
    $new_hp = $base_hp + ($new_constitution * $hp_increment_per_point);

    // Update the character's stats and HP
    $stmt = $conn->prepare("
        UPDATE characters 
        SET 
            strength = strength + :strength,
            dexterity = dexterity + :dexterity,
            constitution = constitution + :constitution,
            negotiation = negotiation + :negotiation,
            unallocated_points = :unallocated_points,
            hp = :new_hp
        WHERE id = :id
    ");
    $stmt->bindParam(':strength', $strength);
    $stmt->bindParam(':dexterity', $dexterity);
    $stmt->bindParam(':constitution', $constitution);
    $stmt->bindParam(':negotiation', $negotiation);
    $stmt->bindParam(':unallocated_points', $unallocated_points);
    $stmt->bindParam(':new_hp', $new_hp);
    $stmt->bindParam(':id', $character_id);
    $stmt->execute();

    $conn->commit();

    echo json_encode(['success' => true]);
} catch (Exception $e) {
    $conn->rollBack();
    echo json_encode(['success' => false, 'message' => 'An error occurred: ' . $e->getMessage()]);
}
?>