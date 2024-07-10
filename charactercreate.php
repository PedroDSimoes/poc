<?php
include 'session.php';
include 'db.php';
include 'csrf.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit();
}

// Redirect if character creation is already completed
if (isset($_SESSION['character_creation_required']) && !$_SESSION['character_creation_required']) {
    header('Location: main.php');
    exit();
}

$error = '';

function getRandomCell($conn) {
    $blocks = range('A', 'O');
    $cells = range(1, 20);

    shuffle($blocks);
    shuffle($cells);

    foreach ($blocks as $block) {
        foreach ($cells as $cell) {
            $stmt = $conn->prepare("SELECT COUNT(*) FROM characters WHERE block = :block AND cell = :cell");
            $stmt->bindParam(':block', $block);
            $stmt->bindParam(':cell', $cell);
            $stmt->execute();
            $count = $stmt->fetchColumn();

            if ($count == 0) {
                return [$block, $cell];
            }
        }
    }
    throw new Exception("No available cells");
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'])) {
        $error = "Invalid CSRF token.";
        error_log("Invalid CSRF token during registration attempt.");
    } else {
        $user_id = $_SESSION['user_id'];
        $character_name = filter_input(INPUT_POST, 'character_name', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $strength = intval($_POST['strength']);
        $dexterity = intval($_POST['dexterity']);
        $constitution = intval($_POST['constitution']);
        $negotiation = intval($_POST['negotiation']);
        $total_points = $strength + $dexterity + $constitution + $negotiation;

        if (strlen($character_name) < 3) {
            $error = "Character name must be at least 3 characters.";
        } elseif ($total_points != 15) {
            $error = "You must allocate exactly 15 points.";
        } else {
            try {
                list($block, $cell) = getRandomCell($conn);

                $hp = 50 + ($constitution * 5);

                $stmt = $conn->prepare("INSERT INTO characters (user_id, character_name, strength, dexterity, constitution, negotiation, level, block, cell, hp) VALUES (:user_id, :character_name, :strength, :dexterity, :constitution, :negotiation, 1, :block, :cell, :hp)");
                $stmt->bindParam(':user_id', $user_id);
                $stmt->bindParam(':character_name', $character_name);
                $stmt->bindParam(':strength', $strength);
                $stmt->bindParam(':dexterity', $dexterity);
                $stmt->bindParam(':constitution', $constitution);
                $stmt->bindParam(':negotiation', $negotiation);
                $stmt->bindParam(':block', $block);
                $stmt->bindParam(':cell', $cell);
                $stmt->bindParam(':hp', $hp);
                $stmt->execute();

                // Set the session flag indicating that character creation is complete
                $_SESSION['character_creation_required'] = false;

                header('Location: main.php');
                exit();
            } catch (Exception $e) {
                $error = "An error occurred: " . $e->getMessage();
            } catch (PDOException $e) {
                $error = "An error occurred: " . $e->getMessage();
            }
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Create Character</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        .stat-buttons {
            display: flex;
            align-items: center;
        }
        .stat-buttons button {
            margin: 0 5px;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const totalPoints = 15;
            const statInputs = document.querySelectorAll('.stat-input');
            const pointsLeftElement = document.getElementById('points-left');
            let pointsLeft = totalPoints;

            function updatePointsLeft() {
                pointsLeft = totalPoints;
                statInputs.forEach(input => {
                    pointsLeft -= parseInt(input.value);
                });
                pointsLeftElement.textContent = pointsLeft;
            }

            statInputs.forEach(input => {
                input.addEventListener('input', updatePointsLeft);
                input.previousElementSibling.addEventListener('click', function() {
                    if (parseInt(input.value) > 0) {
                        input.value = parseInt(input.value) - 1;
                        updatePointsLeft();
                    }
                });
                input.nextElementSibling.addEventListener('click', function() {
                    if (pointsLeft > 0) {
                        input.value = parseInt(input.value) + 1;
                        updatePointsLeft();
                    }
                });
            });

            updatePointsLeft(); // Initial call to set points left
        });
    </script>
</head>
<body>
<div class="container">
    <h2>Create Character</h2>
    <?php if ($error): ?>
        <div class="alert alert-danger" role="alert"><?php echo $error; ?></div>
    <?php endif; ?>
    <form id="characterCreateForm" method="post" action="charactercreate.php">
        <input type="hidden" name="csrf_token" value="<?php echo generate_csrf_token(); ?>">
        <div class="form-group">
            <label for="character_name">Character Name:</label>
            <input type="text" class="form-control" id="character_name" name="character_name" required>
        </div>
        <div class="form-group">
            <label>Points Left: <span id="points-left">15</span></label>
        </div>
        <div class="form-group">
            <label>Strength:</label>
            <div class="stat-buttons">
                <button type="button">-</button>
                <input type="number" class="form-control stat-input" name="strength" value="0" min="0">
                <button type="button">+</button>
            </div>
        </div>
        <div class="form-group">
            <label>Dexterity:</label>
            <div class="stat-buttons">
                <button type="button">-</button>
                <input type="number" class="form-control stat-input" name="dexterity" value="0" min="0">
                <button type="button">+</button>
            </div>
        </div>
        <div class="form-group">
            <label>Constitution:</label>
            <div class="stat-buttons">
                <button type="button">-</button>
                <input type="number" class="form-control stat-input" name="constitution" value="0" min="0">
                <button type="button">+</button>
            </div>
        </div>
        <div class="form-group">
            <label>Negotiation:</label>
            <div class="stat-buttons">
                <button type="button">-</button>
                <input type="number" class="form-control stat-input" name="negotiation" value="0" min="0">
                <button type="button">+</button>
            </div>
        </div>
        <button type="submit" class="btn btn-primary">Create Character</button>
    </form>
</div>
</body>
</html>