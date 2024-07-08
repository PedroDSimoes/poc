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
    $_SESSION['character_id'] = $character['id'];
}

// Check if the quest has been accepted or is in progress
$stmt = $conn->prepare("SELECT * FROM user_quests WHERE user_id = :user_id AND quest_id = 1 AND status IN ('accepted', 'in_progress')");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$quest_accepted = $stmt->fetch(PDO::FETCH_ASSOC);

// Fetch the current work lock time and workplace from the database
$stmt = $conn->prepare("SELECT work_lock_time, current_workplace FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$work_lock_time = isset($user['work_lock_time']) ? $user['work_lock_time'] : 0;
$current_workplace = isset($user['current_workplace']) ? $user['current_workplace'] : '';
$current_time = time();
$lock_remaining_time = max(0, $work_lock_time - $current_time);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Kitchen</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <h1>Kitchen</h1>
    <?php if ($character): ?>
        <h2>Character Details</h2>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($character['character_name']); ?></p>
        <p><strong>Level:</strong> <span id="level"><?php echo htmlspecialchars($character['level']); ?></span></p>
        <p><strong>XP:</strong> <span id="xp"><?php echo htmlspecialchars($character['xp']); ?></span></p>
        <p><strong>Money:</strong> <span id="money"><?php echo htmlspecialchars($character['money']); ?></span></p>
    <?php endif; ?>
    <button id="workButton" class="btn btn-primary" <?php echo $lock_remaining_time > 0 ? 'disabled' : ''; ?>>Work</button>
    <a href="main.php" class="btn btn-secondary">Back to Main Page</a>
    <a href="map.php" class="btn btn-secondary">Back to Map</a>
    <?php if ($quest_accepted): ?>
        <button id="questItemButton" class="btn btn-success mt-3">Pick Up Cooking Pot</button>
    <?php endif; ?>
    <p id="statusMessage" class="mt-3"></p>
</div>

<?php if ($quest_accepted): ?>
<!-- Quest Item Modal -->
<div class="modal fade" id="questItemModal" tabindex="-1" role="dialog" aria-labelledby="questItemModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="questItemModalLabel">Quest Item</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <p>You picked up a Cooking Pot!</p>
                <button id="confirmQuestItemButton" class="btn btn-success">Confirm</button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    $(document).ready(function() {
        let lockRemainingTime = <?php echo $lock_remaining_time; ?>;
        let currentWorkplace = <?php echo json_encode($current_workplace); ?>;
        
        function updateStatusMessage() {
            if (lockRemainingTime > 0) {
                $('#statusMessage').text(`You are working in the ${currentWorkplace} for another ${lockRemainingTime} seconds.`);
                lockRemainingTime--;
                setTimeout(updateStatusMessage, 1000);
            } else {
                $('#statusMessage').text('');
                $('#workButton').prop('disabled', false);
            }
        }

        if (lockRemainingTime > 0) {
            $('#workButton').prop('disabled', true);
            updateStatusMessage();
        }

        $('#workButton').on('click', function() {
            $.ajax({
                url: 'work.php',
                method: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#xp').text(response.new_xp);
                        $('#money').text(response.new_money);
                        $('#level').text(response.new_level); // Update level in real-time
                        lockRemainingTime = response.lock_duration;
                        currentWorkplace = response.place;
                        $('#workButton').prop('disabled', true);
                        updateStatusMessage();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error:', error);
                    console.error('Response text:', xhr.responseText);
                    alert('An error occurred. Please try again.');
                }
            });
        });

        $('#questItemButton').on('click', function() {
            $('#questItemModal').modal('show');
        });

        $('#confirmQuestItemButton').on('click', function() {
            $.ajax({
                url: 'pickup_quest_item.php',
                method: 'POST',
                data: { quest_id: 1 },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        alert('You picked up the Cooking Pot!');
                        $('#questItemModal').modal('hide');
                        $('#questItemButton').hide();
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error picking up quest item:', status, error);
                }
            });
        });
    });
</script>
</body>
</html>