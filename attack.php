<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id']) || !isset($_POST['target_id'])) {
    echo "Error: Invalid request.";
    exit();
}

$user_id = $_SESSION['user_id'];
$target_id = $_POST['target_id'];

// Check if the attacker has attacked the same player within the last 10 seconds
function canAttack($attackerId, $targetId) {
    global $conn;
    $stmt = $conn->prepare("SELECT last_attack_time FROM characters WHERE user_id = :user_id");
    $stmt->bindParam(':user_id', $attackerId);
    $stmt->execute();
    $lastAttackTime = $stmt->fetchColumn();

    if ($lastAttackTime) {
        $lastAttackTimestamp = strtotime($lastAttackTime);
        $currentTimestamp = time();
        $timeDiff = $currentTimestamp - $lastAttackTimestamp;

        if ($timeDiff < 10) {
            return false;
        }
    }

    return true;
}

if (!canAttack($user_id, $target_id)) {
    echo "Error: You must wait 10 seconds before attacking the same player again.";
    exit();
}

function getCharacterStats($userId) {
    global $conn;
    $stmt = $conn->prepare("SELECT c.*, 
                            COALESCE(w.damage, 0) as equipped_weapon_damage, 
                            COALESCE(a.armor, 0) as equipped_armor_value,
                            c.armor as base_armor_value,
                            c.damage as base_weapon_damage,
                            c.character_name,
                            c.money
                            FROM characters c
                            LEFT JOIN equipped_items ei_weapon ON c.id = ei_weapon.user_id AND ei_weapon.slot = 'weapon'
                            LEFT JOIN items w ON ei_weapon.item_id = w.id
                            LEFT JOIN equipped_items ei_armor ON c.id = ei_armor.user_id AND ei_armor.slot = 'armor'
                            LEFT JOIN items a ON ei_armor.item_id = a.id
                            WHERE c.user_id = :user_id");
    $stmt->bindParam(':user_id', $userId);
    $stmt->execute();
    return $stmt->fetch(PDO::FETCH_ASSOC);
}

$player1 = getCharacterStats($user_id);
$player2 = getCharacterStats($target_id);

if (!$player1 || !$player2) {
    echo "Error: Character not found.";
    exit();
}

// Calculate total armor value
$player1['armor_value'] = $player1['equipped_armor_value'] + $player1['base_armor_value'];
$player2['armor_value'] = $player2['equipped_armor_value'] + $player2['base_armor_value'];

// Calculate total weapon damage
$player1['weapon_damage'] = max(1, $player1['equipped_weapon_damage'] + $player1['base_weapon_damage']);
$player2['weapon_damage'] = max(1, $player2['equipped_weapon_damage'] + $player2['base_weapon_damage']);

// Log player armor and weapon damage values
echo "Player 1 ({$player1['character_name']}) Armor: {$player1['armor_value']}, Weapon Damage: {$player1['weapon_damage']}<br>";
echo "Player 2 ({$player2['character_name']}) Armor: {$player2['armor_value']}, Weapon Damage: {$player2['weapon_damage']}<br>";

$combatLog = [];
$round = 1;
$player1['current_hp'] = $player1['hp'];
$player2['current_hp'] = $player2['hp'];
$strengthModifier = 0.20;

while ($player1['current_hp'] > 0 && $player2['current_hp'] > 0 && $round <= 100) {
    // Player 2 (defender) hits first
    $player2RealDamage = round($player2['weapon_damage'] * (1 + $strengthModifier * $player2['strength']));
    $player2MitigatedDamage = max(0, $player2RealDamage - round($player1['armor_value'] * 0.5));
    $player1['current_hp'] -= $player2MitigatedDamage;
    $player1['current_hp'] = max(0, $player1['current_hp']); // Ensure HP doesn't drop below 0
    $combatLog[] = "Round $round: {$player2['character_name']} hits {$player1['character_name']} for $player2MitigatedDamage damage (Real Damage: $player2RealDamage, {$player1['character_name']}'s Armor: {$player1['armor_value']}). {$player1['current_hp']} HP.";

    if ($player1['current_hp'] <= 0) {
        $combatLog[] = "{$player2['character_name']} wins the combat!";
        break;
    }

    // Player 1 (attacker) hits second
    $player1RealDamage = round($player1['weapon_damage'] * (1 + $strengthModifier * $player1['strength']));
    $player1MitigatedDamage = max(0, $player1RealDamage - round($player2['armor_value'] * 0.5));
    $player2['current_hp'] -= $player1MitigatedDamage;
    $player2['current_hp'] = max(0, $player2['current_hp']); // Ensure HP doesn't drop below 0
    $combatLog[] = "Round $round: {$player1['character_name']} hits {$player2['character_name']} for $player1MitigatedDamage damage (Real Damage: $player1RealDamage, {$player2['character_name']}'s Armor: {$player2['armor_value']}). {$player2['current_hp']}.";

    if ($player2['current_hp'] <= 0) {
        $combatLog[] = "{$player1['character_name']} wins the combat!";
        // Update attacker's HP in the database
        $stmt = $conn->prepare("UPDATE characters SET hp = :hp WHERE user_id = :user_id");
        $stmt->bindParam(':hp', $player1['current_hp']);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        // Transfer 10% of defender's money to attacker
        $moneyReward = round($player2['money'] * 0.10);
        $stmt = $conn->prepare("UPDATE characters SET money = money + :moneyReward WHERE user_id = :user_id");
        $stmt->bindParam(':moneyReward', $moneyReward);
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        // Deduct money from defender
        $stmt = $conn->prepare("UPDATE characters SET money = money - :moneyReward WHERE user_id = :target_id");
        $stmt->bindParam(':moneyReward', $moneyReward);
        $stmt->bindParam(':target_id', $target_id);
        $stmt->execute();

        // Update last attack time
        $stmt = $conn->prepare("UPDATE characters SET last_attack_time = NOW() WHERE user_id = :user_id");
        $stmt->bindParam(':user_id', $user_id);
        $stmt->execute();

        break;
    }

    // Update attacker's HP in the database after each round
    $stmt = $conn->prepare("UPDATE characters SET hp = :hp WHERE user_id = :user_id");
    $stmt->bindParam(':hp', $player1['current_hp']);
    $stmt->bindParam(':user_id', $user_id);
    $stmt->execute();

    $round++;
}

// Update last attack time if combat ended without break
$stmt = $conn->prepare("UPDATE characters SET last_attack_time = NOW() WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();

// Display the combat log
echo "<ul>";
foreach ($combatLog as $logEntry) {
    echo "<li>$logEntry</li>";
}
echo "</ul>";
?>
