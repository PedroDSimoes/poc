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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Outside Yard</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
<div class="container">
    <h1>Outside Yard</h1>
    <?php if ($character): ?>
        <h2>Character Details</h2>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($character['character_name']); ?></p>
        <p><strong>Level:</strong> <span id="level"><?php echo htmlspecialchars($character['level']); ?></span></p>
        <p><strong>XP:</strong> <span id="xp"><?php echo htmlspecialchars($character['xp']); ?></span></p>
        <p><strong>Money:</strong> <span id="money"><?php echo htmlspecialchars($character['money']); ?></span></p>
    <?php endif; ?>
    <h3>Buy Items</h3>
    <div>
        <button class="btn btn-primary buyItemButton" data-item-id="1">Buy Knife ($10)</button>
        <button class="btn btn-primary buyItemButton" data-item-id="2">Buy Bulletproof Vest ($20)</button>
    </div>
    <button id="inventoryButton" class="btn btn-warning mt-3">View Inventory</button>
    <a href="map.php" class="btn btn-secondary mt-3">Back to Map</a>
    <p id="statusMessage" class="mt-3"></p>
</div>

<!-- Inventory Modal -->
<div class="modal fade" id="inventoryModal" tabindex="-1" role="dialog" aria-labelledby="inventoryModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="inventoryModalLabel">Inventory</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <h6>Weapons</h6>
                <div id="inventoryWeapons" class="d-flex flex-wrap"></div>
                <h6 class="mt-3">Armor</h6>
                <div id="inventoryArmor" class="d-flex flex-wrap"></div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    $(document).ready(function() {
        $('.buyItemButton').on('click', function() {
            const itemId = $(this).data('item-id');
            $.ajax({
                url: 'buy_item.php',
                method: 'POST',
                data: { item_id: itemId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#money').text(response.new_money);
                        fetchInventory();  // Update inventory after buying item
                        alert('You bought a ' + response.item_name);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error buying item:', status, error);
                }
            });
        });

        $('#inventoryButton').on('click', function() {
            fetchInventory();
            $('#inventoryModal').modal('show');
        });

        function fetchInventory() {
            $.ajax({
                url: 'fetch_inventory.php',
                method: 'POST',
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#inventoryWeapons').empty();
                        $('#inventoryArmor').empty();
                        response.inventory_items.forEach(function(item) {
                            if (item.type === 'weapon') {
                                $('#inventoryWeapons').append(
                                    `<div class="p-2 border m-1">${item.name}<br><small>${item.description}</small><br>
                                    <button class="btn btn-sm btn-danger sellItemButton" data-item-id="${item.id}" data-item-type="weapon">Sell</button></div>`
                                );
                            } else if (item.type === 'armor') {
                                $('#inventoryArmor').append(
                                    `<div class="p-2 border m-1">${item.name}<br><small>${item.description}</small><br>
                                    <button class="btn btn-sm btn-danger sellItemButton" data-item-id="${item.id}" data-item-type="armor">Sell</button></div>`
                                );
                            }
                        });
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error fetching inventory:', status, error);
                }
            });
        }

        $(document).on('click', '.sellItemButton', function() {
            const itemId = $(this).data('item-id');
            $.ajax({
                url: 'sell_item.php',
                method: 'POST',
                data: { item_id: itemId },
                dataType: 'json',
                success: function(response) {
                    if (response.success) {
                        $('#money').text(response.new_money);
                        fetchInventory();  // Update inventory after selling item
                        alert('You sold a ' + response.item_name + ' for $' + response.selling_price);
                    } else {
                        alert('Error: ' + response.message);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Error selling item:', status, error);
                }
            });
        });
    });
</script>
</body>
</html>