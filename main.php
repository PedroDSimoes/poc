<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

$user_id = $_SESSION['user_id'];

// Check if the user has a character
$stmt = $conn->prepare("SELECT * FROM characters WHERE user_id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$character = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$character) {
    $_SESSION['character_creation_required'] = true;
    header('Location: charactercreate.php');
    exit();
} else {
    $_SESSION['character_creation_required'] = false;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Main Page</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <h1>Welcome to the main page!</h1>
    <?php if ($character): ?>
        <h2>Character Details</h2>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($character['character_name']); ?></p>
        <p><strong>Level:</strong> <?php echo htmlspecialchars($character['level']); ?></p>
        <p><strong>Strength:</strong> <?php echo htmlspecialchars($character['strength']); ?></p>
        <p><strong>Dexterity:</strong> <?php echo htmlspecialchars($character['dexterity']); ?></p>
        <p><strong>Constitution:</strong> <?php echo htmlspecialchars($character['constitution']); ?></p>
        <p><strong>Negotiation:</strong> <?php echo htmlspecialchars($character['negotiation']); ?></p>
    <?php endif; ?>
    <a href="logout.php" class="btn btn-danger">Logout</a>
    <a href="map.php" class="btn btn-primary">View Map</a>
</div>
</body>
</html>