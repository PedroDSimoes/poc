<?php
include 'session.php';
include 'db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Fetch all characters and their assigned cells
$stmt = $conn->prepare("SELECT character_name, block, cell FROM characters ORDER BY block, cell");
$stmt->execute();
$characters = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Initialize the map structure
$map = [];
foreach (range('A', 'O') as $block) {
    for ($cell = 1; $cell <= 20; $cell++) {
        $map[$block][$cell] = null;
    }
}

// Populate the map with characters
foreach ($characters as $character) {
    $map[$character['block']][$character['cell']] = $character['character_name'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Map View</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .map {
            margin-top: 20px;
        }
        .block {
            border: 1px solid #ccc;
            padding: 10px;
            text-align: center;
            margin-bottom: 10px;
        }
        .cell {
            border: 1px solid #ccc;
            padding: 10px;
            margin: 5px 0;
            text-align: center;
        }
        .occupied {
            background-color: #d4edda;
        }
    </style>
</head>
<body>
<div class="container">
    <h2>Map View</h2>
    <a href="main.php" class="btn btn-secondary">Back to Main Page</a>
    <div class="map">
        <?php foreach ($map as $block => $cells): ?>
            <div class="block">
                <button class="btn btn-link" type="button" data-toggle="collapse" data-target="#block-<?php echo $block; ?>" aria-expanded="false" aria-controls="block-<?php echo $block; ?>">
                    Block <?php echo $block; ?>
                </button>
                <div class="collapse" id="block-<?php echo $block; ?>">
                    <?php foreach ($cells as $cell => $character_name): ?>
                        <div class="cell <?php echo $character_name ? 'occupied' : ''; ?>">
                            <strong>Cell <?php echo $cell; ?></strong>
                            <?php if ($character_name): ?>
                                <div><?php echo htmlspecialchars($character_name); ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="mt-4">
        <button class="btn btn-info">Kitchen</button>
        <button class="btn btn-info">Library</button>
        <button class="btn btn-info">Workshop</button>
        <button class="btn btn-info">Day Hall</button>
        <button class="btn btn-info">Outside Yard</button>
    </div>
</div>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
</body>
</html>
