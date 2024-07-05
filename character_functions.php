<?php
include 'db.php';

function gainXp($characterId, $xpGained) {
    global $conn;
    
    // Get current XP and level
    $stmt = $conn->prepare("SELECT xp, level FROM characters WHERE id = :id");
    $stmt->bindParam(':id', $characterId);
    $stmt->execute();
    $character = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$character) {
        return false;
    }

    $xp = $character['xp'] + $xpGained;
    $level = $character['level'];
    
    // Calculate XP required for next level
    $xpRequired = 50 * pow($level, 2) + 150 * $level + 100;
    
    // Check for level up
    while ($xp >= $xpRequired) {
        $xp -= $xpRequired;
        $level++;
        $xpRequired = 50 * pow($level, 2) + 150 * $level + 100;
    }
    
    // Update character XP and level
    $stmt = $conn->prepare("UPDATE characters SET xp = :xp, level = :level WHERE id = :id");
    $stmt->bindParam(':xp', $xp);
    $stmt->bindParam(':level', $level);
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