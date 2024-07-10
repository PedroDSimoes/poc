<?php
include 'db.php';

function gainXp($characterId, $xpGained) {
    global $conn;
    
    // Get current XP, level, and unallocated points
    $stmt = $conn->prepare("SELECT xp, level, unallocated_points, constitution FROM characters WHERE id = :id");
    $stmt->bindParam(':id', $characterId);
    $stmt->execute();
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$character) {
        return false;
    }

    $xp = $character['xp'] + $xpGained;
    $level = $character['level'];
    $unallocated_points = $character['unallocated_points'];
    $constitution = $character['constitution'];
    
    // Calculate XP required for next level
    $xpRequired = 50 * pow($level, 2) + 150 * $level + 100;
    
    // Check for level up
    while ($xp >= $xpRequired) {
        $xp -= $xpRequired;
        $level++;
        $unallocated_points += 5; // Add 5 unallocated points for each level up
        $xpRequired = 50 * pow($level, 2) + 150 * $level + 100;
    }
    
    // Recalculate HP based on the updated constitution
    $base_hp = 50;
    $hp_increment_per_point = 5;
    $hp = $base_hp + ($constitution * $hp_increment_per_point);

    // Update character XP, level, unallocated points, and HP
    $stmt = $conn->prepare("UPDATE characters SET xp = :xp, level = :level, unallocated_points = :unallocated_points, hp = :hp WHERE id = :id");
    $stmt->bindParam(':xp', $xp);
    $stmt->bindParam(':level', $level);
    $stmt->bindParam(':unallocated_points', $unallocated_points);
    $stmt->bindParam(':hp', $hp);
    $stmt->bindParam(':id', $characterId);
    $stmt->execute();
    
    return true;
}

function gainMoney($characterId, $amount) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE characters SET money = money + :amount WHERE id = :id");
    $stmt->bindParam(':amount', $amount);
    $stmt->bindParam(':id', $characterId);
    $stmt->execute();
    
    return $stmt->rowCount() > 0;
}

function loseMoney($characterId, $amount) {
    global $conn;
    
    $stmt = $conn->prepare("UPDATE characters SET money = money - :amount WHERE id = :id AND money >= :amount");
    $stmt->bindParam(':amount', $amount);
    $stmt->bindParam(':id', $characterId);
    $stmt->execute();
    
    return $stmt->rowCount() > 0;
}
?>