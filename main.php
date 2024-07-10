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

// Fetch the work lock time and current workplace from the database
$stmt = $conn->prepare("SELECT work_lock_time, current_workplace FROM users WHERE id = :user_id");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$user = $stmt->fetch(PDO::FETCH_ASSOC);
$work_lock_time = isset($user['work_lock_time']) ? $user['work_lock_time'] : 0;
$current_workplace = isset($user['current_workplace']) ? $user['current_workplace'] : '';
$current_time = time();
$lock_remaining_time = max(0, $work_lock_time - $current_time);

// Fetch the most recent conversation partners and their character names
$stmt = $conn->prepare("
    SELECT DISTINCT 
        CASE 
            WHEN sender_id = :user_id THEN receiver_id 
            ELSE sender_id 
        END as partner_id,
        c.character_name
    FROM messages m
    JOIN characters c ON (c.user_id = m.sender_id OR c.user_id = m.receiver_id)
    WHERE (m.sender_id = :user_id OR m.receiver_id = :user_id) AND c.user_id != :user_id
    ORDER BY m.created_at DESC
");
$stmt->bindParam(':user_id', $user_id);
$stmt->execute();
$recent_conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recipient_id if specified in URL
$recipient_id = isset($_GET['recipient_id']) ? $_GET['recipient_id'] : null;
$recipient_name = isset($_GET['recipient_name']) ? $_GET['recipient_name'] : null;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Main Page</title>
    <link href="https://maxcdn.bootstrapcdn.com/bootstrap/4.0.0/css/bootstrap.min.css" rel="stylesheet">
    <style>
        /* Side panel style */
        .chat-panel {
            height: 100%;
            width: 0;
            position: fixed;
            top: 0;
            right: 0;
            background-color: #f1f1f1;
            overflow-x: hidden;
            transition: 0.5s;
            padding-top: 60px;
            z-index: 1000;
        }

        .chat-panel .closebtn {
            position: absolute;
            top: 0;
            right: 25px;
            font-size: 36px;
        }

        .chat-panel .chat-content {
            padding: 20px;
        }

        .chat-panel .nav-tabs .nav-link.active {
            background-color: #ddd;
        }

        .chat-panel .tab-content .tab-pane {
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const statInputs = document.querySelectorAll('.stat-input');
            const pointsLeftElement = document.getElementById('points-left');
            let pointsLeft = <?php echo $character['unallocated_points']; ?>;

            function updatePointsLeft() {
                pointsLeft = <?php echo $character['unallocated_points']; ?>;
                statInputs.forEach(input => {
                    pointsLeft -= parseInt(input.value) - parseInt(input.dataset.initial);
                });
                pointsLeftElement.textContent = pointsLeft;
            }

            statInputs.forEach(input => {
                input.addEventListener('input', updatePointsLeft);
                input.previousElementSibling.addEventListener('click', function() {
                    if (parseInt(input.value) > parseInt(input.dataset.initial)) {
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
    <h1>Welcome to the main page!</h1>
    <?php if ($character): ?>
        <h2>Character Details</h2>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($character['character_name']); ?></p>
        <p><strong>Level:</strong> <span id="level"><?php echo htmlspecialchars($character['level']); ?></span></p>
        <p><strong>XP:</strong> <span id="xp"><?php echo htmlspecialchars($character['xp']); ?></span></p>
        <p><strong>Strength:</strong> <?php echo htmlspecialchars($character['strength']); ?></p>
        <p><strong>Dexterity:</strong> <?php echo htmlspecialchars($character['dexterity']); ?></p>
        <p><strong>Constitution:</strong> <?php echo htmlspecialchars($character['constitution']); ?></p>
        <p><strong>Negotiation:</strong> <?php echo htmlspecialchars($character['negotiation']); ?></p>
        <p><strong>Block:</strong> <?php echo htmlspecialchars($character['block']); ?></p>
        <p><strong>Cell:</strong> <?php echo htmlspecialchars($character['cell']); ?></p>
    <?php endif; ?>
    <a href="logout.php" class="btn btn-danger">Logout</a>
    <a href="map.php" class="btn btn-primary">View Map</a>
    <button id="chatButton" class="btn btn-info">Chat</button>
    <button id="inventoryButton" class="btn btn-warning">Inventory</button>
    <button id="equipButton" class="btn btn-secondary">Equip</button>
    <button id="characterStatsButton" class="btn btn-info">Character Stats</button>
    <p id="statusMessage" class="mt-3"></p>
</div>

<!-- Chat Side Panel -->
<div id="chatPanel" class="chat-panel">
    <a href="javascript:void(0)" class="closebtn" onclick="closeChatPanel()">&times;</a>
    <div class="chat-content">
        <ul class="nav nav-tabs" id="chatTabs" role="tablist">
            <?php foreach ($recent_conversations as $index => $conversation): ?>
                <li class="nav-item">
                    <a class="nav-link <?php echo $index === 0 ? 'active' : ''; ?>" id="tab-<?php echo $conversation['partner_id']; ?>" data-toggle="tab" href="#chat-<?php echo $conversation['partner_id']; ?>" role="tab" aria-controls="chat-<?php echo $conversation['partner_id']; ?>" aria-selected="<?php echo $index === 0 ? 'true' : 'false'; ?>">
                        <?php echo htmlspecialchars($conversation['character_name']); ?>
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
        <div class="tab-content" id="chatTabsContent">
            <?php foreach ($recent_conversations as $index => $conversation): ?>
                <div class="tab-pane fade <?php echo $index === 0 ? 'show active' : ''; ?>" id="chat-<?php echo $conversation['partner_id']; ?>" role="tabpanel" aria-labelledby="tab-<?php echo $conversation['partner_id']; ?>">
                    <div id="messagesContainer-<?php echo $conversation['partner_id']; ?>"></div>
                    <div class="form-group">
                        <textarea id="messageText-<?php echo $conversation['partner_id']; ?>" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="button" class="btn btn-primary sendMessageButton" data-recipient-id="<?php echo $conversation['partner_id']; ?>">Send</button>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
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

<!-- Equip Modal -->
<div class="modal fade" id="equipModal" tabindex="-1" role="dialog" aria-labelledby="equipModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="equipModalLabel">Equip Items</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <div id="equippedItems" class="d-flex flex-wrap">
                    <div class="p-2 border m-1" id="equippedWeapon">Weapon: None</div>
                    <div class="p-2 border m-1" id="equippedArmor">Armor: None</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Character Stats Modal -->
<div class="modal fade" id="characterStatsModal" tabindex="-1" role="dialog" aria-labelledby="characterStatsModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="characterStatsModalLabel">Character Stats</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <?php if ($character): ?>
                    <h2>Character Details</h2>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($character['character_name']); ?></p>
                    <p><strong>Level:</strong> <span id="level"><?php echo htmlspecialchars($character['level']); ?></span></p>
                    <p><strong>XP:</strong> <span id="xp"><?php echo htmlspecialchars($character['xp']); ?></span></p>
                    <p><strong>Money:</strong> <span id="money"><?php echo htmlspecialchars($character['money']); ?></span></p>
                    <p><strong>Strength:</strong> <?php echo htmlspecialchars($character['strength']); ?></p>
                    <p><strong>Dexterity:</strong> <?php echo htmlspecialchars($character['dexterity']); ?></p>
                    <p><strong>Constitution:</strong> <?php echo htmlspecialchars($character['constitution']); ?></p>
                    <p><strong>Negotiation:</strong> <?php echo htmlspecialchars($character['negotiation']); ?></p>

                    <?php if ($character['unallocated_points'] > 0): ?>
                        <h3>Allocate Points</h3>
                        <div class="form-group">
                            <label>Points Left: <span id="points-left"><?php echo $character['unallocated_points']; ?></span></label>
                        </div>
                        <form id="allocatePointsForm">
                            <div class="form-group">
                                <label>Strength:</label>
                                <div class="stat-buttons">
                                    <button type="button">-</button>
                                    <input type="number" class="form-control stat-input" name="strength" value="0" min="0" data-initial="0">
                                    <button type="button">+</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Dexterity:</label>
                                <div class="stat-buttons">
                                    <button type="button">-</button>
                                    <input type="number" class="form-control stat-input" name="dexterity" value="0" min="0" data-initial="0">
                                    <button type="button">+</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Constitution:</label>
                                <div class="stat-buttons">
                                    <button type="button">-</button>
                                    <input type="number" class="form-control stat-input" name="constitution" value="0" min="0" data-initial="0">
                                    <button type="button">+</button>
                                </div>
                            </div>
                            <div class="form-group">
                                <label>Negotiation:</label>
                                <div class="stat-buttons">
                                    <button type="button">-</button>
                                    <input type="number" class="form-control stat-input" name="negotiation" value="0" min="0" data-initial="0">
                                    <button type="button">+</button>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary" id="allocatePointsButton">Allocate Points</button>
                        </form>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    let activeRecipientId = <?php echo $recipient_id ? json_encode($recipient_id) : 'null'; ?>;
    let activeRecipientName = <?php echo $recipient_name ? json_encode($recipient_name) : 'null'; ?>;
    let lockRemainingTime = <?php echo $lock_remaining_time; ?>;
    let currentWorkplace = <?php echo json_encode($current_workplace); ?>;

    function openChatPanel() {
        document.getElementById("chatPanel").style.width = "400px";
        fetchMessagesForAllTabs();
    }

    function closeChatPanel() {
        document.getElementById("chatPanel").style.width = "0";
    }

    // Open chat panel and fetch messages when chat button is pressed
    $('#chatButton').on('click', function() {
        openChatPanel();
    });

    $('#characterStatsButton').on('click', function() {
        $('#characterStatsModal').modal('show');
    });

    // Fetch messages for a specific recipient
    function fetchMessages(recipientId) {
        console.log('Fetching messages for recipient:', recipientId); // Debug log
        $.ajax({
            url: 'fetch_messages.php',
            method: 'POST',
            data: { recipient_id: recipientId },
            success: function(response) {
                console.log('Messages fetched for recipient:', recipientId, response); // Debug log
                $('#messagesContainer-' + recipientId).html(response);
            },
            error: function(xhr, status, error) {
                console.error('Error fetching messages:', status, error); // Debug log
            }
        });
    }

    // Fetch messages for all tabs
    function fetchMessagesForAllTabs() {
        $('.nav-link').each(function() {
            const recipientId = $(this).attr('id').split('-')[1];
            fetchMessages(recipientId);
        });
    }

    // Send message
    $(document).on('click', '.sendMessageButton', function() {
        const recipientId = $(this).data('recipient-id');
        const messageText = $('#messageText-' + recipientId).val();
        if (messageText.trim() !== '') {
            console.log('Sending message to recipient:', recipientId, messageText); // Debug log
            $.ajax({
                url: 'send_message.php',
                method: 'POST',
                data: { recipient_id: recipientId, message: messageText },
                success: function() {
                    console.log('Message sent to recipient:', recipientId); // Debug log
                    $('#messageText-' + recipientId).val('');
                    fetchMessages(recipientId);
                },
                error: function(xhr, status, error) {
                    console.error('Error sending message:', status, error); // Debug log
                }
            });
        }
    });

    // Open chat panel if redirected from a cell click
    $(document).ready(function() {
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.has('recipient_id')) {
            activeRecipientId = urlParams.get('recipient_id');
            activeRecipientName = urlParams.get('recipient_name');
            openChatPanel();
            addConversationTab(activeRecipientId, activeRecipientName);
        }
        
        // Update status message
        updateStatusMessage();

        // Fetch inventory and equipped items
        fetchInventoryAndEquip();
    });

    // Add new conversation tab
    function addConversationTab(recipientId, recipientName) {
        console.log('Adding new conversation tab for recipient:', recipientId, recipientName); // Debug log
        if ($('#tab-' + recipientId).length === 0) {
            $('#chatTabs').append(
                `<li class="nav-item">
                    <a class="nav-link" id="tab-${recipientId}" data-toggle="tab" href="#chat-${recipientId}" role="tab" aria-controls="chat-${recipientId}" aria-selected="false">
                        ${recipientName}
                    </a>
                </li>`
            );
            $('#chatTabsContent').append(
                `<div class="tab-pane fade" id="chat-${recipientId}" role="tabpanel" aria-labelledby="tab-${recipientId}">
                    <div id="messagesContainer-${recipientId}"></div>
                    <div class="form-group">
                        <textarea id="messageText-${recipientId}" class="form-control" rows="3"></textarea>
                    </div>
                    <button type="button" class="btn btn-primary sendMessageButton" data-recipient-id="${recipientId}">Send</button>
                </div>`
            );
        }
        // Ensure all tabs and tab content are in the correct state
        $('.nav-link').removeClass('active');
        $('.tab-pane').removeClass('show active');
        $('#tab-' + recipientId).addClass('active');
        $('#chat-' + recipientId).addClass('show active');
        fetchMessages(recipientId);
    }

    // Re-initialize tab contents visibility when a tab is shown
    $('a[data-toggle="tab"]').on('shown.bs.tab', function (e) {
        const target = $(e.target).attr("href"); // activated tab
        $(target).addClass('show active');
    });

    // Make sure that previously loaded tab contents are visible when panel is shown
    $('#chatPanel').on('shown', function () {
        $('.tab-pane').each(function() {
            if ($(this).hasClass('active')) {
                $(this).addClass('show');
            }
        });
    });

    function updateStatusMessage() {
        if (lockRemainingTime > 0) {
            $('#statusMessage').text(`You are working in the ${currentWorkplace} for another ${lockRemainingTime} seconds.`);
            lockRemainingTime--;
            setTimeout(updateStatusMessage, 1000);
        } else {
            $('#statusMessage').text('');
        }
    }

    // Fetch inventory and equipped items
    function fetchInventoryAndEquip() {
        $.ajax({
            url: 'fetch_inventory.php',
            method: 'POST',
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    // Display inventory items
                    $('#inventoryWeapons').empty();
                    $('#inventoryArmor').empty();
                    response.inventory_items.forEach(function(item) {
    if (item.type === 'weapon') {
        $('#inventoryWeapons').append(
            `<div class="p-2 border m-1">${item.name}<br><small>${item.description}</small><br>
            <button class="btn btn-sm btn-primary equipButton" data-item-id="${item.id}" data-item-type="weapon" data-inventory-id="${item.inventory_id}">Equip</button></div>`
        );
    } else if (item.type === 'armor') {
        $('#inventoryArmor').append(
            `<div class="p-2 border m-1">${item.name}<br><small>${item.description}</small><br>
            <button class="btn btn-sm btn-primary equipButton" data-item-id="${item.id}" data-item-type="armor" data-inventory-id="${item.inventory_id}">Equip</button></div>`
        );
    }
});

                    // Display equipped items
                    $('#equippedWeapon').html('Weapon: None');
                    $('#equippedArmor').html('Armor: None');
                    response.equipped_items.forEach(function(item) {
                        if (item.slot === 'weapon') {
                            $('#equippedWeapon').html(`Weapon: ${item.name}<br><small>${item.description}</small><br><button class="btn btn-sm btn-danger unequipButton" data-item-id="${item.id}" data-slot="weapon">Unequip</button>`);
                        } else if (item.slot === 'armor') {
                            $('#equippedArmor').html(`Armor: ${item.name}<br><small>${item.description}</small><br><button class="btn btn-sm btn-danger unequipButton" data-item-id="${item.id}" data-slot="armor">Unequip</button>`);
                        }
                    });
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error fetching inventory and equipped items:', status, error);
            }
        });
    }

    // Open inventory modal
    $('#inventoryButton').on('click', function() {
        $('#inventoryModal').modal('show');
    });

    // Open equip modal
    $('#equipButton').on('click', function() {
        $('#equipModal').modal('show');
    });

    // Equip item
    $(document).on('click', '.equipButton', function() {
    const itemId = $(this).data('item-id');
    const itemType = $(this).data('item-type');
    const inventoryId = $(this).data('inventory-id');
    $.ajax({
        url: 'equip_item.php',
        method: 'POST',
        data: { item_id: itemId, slot: itemType, inventory_id: inventoryId },
        dataType: 'json',
        success: function(response) {
            if (response.success) {
                fetchInventoryAndEquip();
            } else {
                alert('Error: ' + response.message);
            }
        },
        error: function(xhr, status, error) {
            console.error('Error equipping item:', status, error);
        }
    });
});

    // Unequip item
    $(document).on('click', '.unequipButton', function() {
        const itemId = $(this).data('item-id');
        const slot = $(this).data('slot');
        $.ajax({
            url: 'unequip_item.php',
            method: 'POST',
            data: { item_id: itemId, slot: slot },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    fetchInventoryAndEquip();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error unequipping item:', status, error);
            }
        });
    });

    // Allocate points
    $('#allocatePointsButton').on('click', function() {
        const strength = parseInt($('input[name="strength"]').val());
        const dexterity = parseInt($('input[name="dexterity"]').val());
        const constitution = parseInt($('input[name="constitution"]').val());
        const negotiation = parseInt($('input[name="negotiation"]').val());

        $.ajax({
            url: 'allocate_points.php',
            method: 'POST',
            data: {
                strength: strength,
                dexterity: dexterity,
                constitution: constitution,
                negotiation: negotiation
            },
            dataType: 'json',
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert('Error: ' + response.message);
                }
            },
            error: function(xhr, status, error) {
                console.error('Error allocating points:', status, error);
            }
        });
    });

    // Handle errors in adding new tabs and content
    window.onerror = function(message, source, lineno, colno, error) {
        console.error('Global error handler:', message, source, lineno, colno, error);
    };
</script>
</body>
</html>