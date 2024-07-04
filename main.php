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
    <button id="chatButton" class="btn btn-info">Chat</button>
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

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
<script>
    let activeRecipientId = <?php echo $recipient_id ? json_encode($recipient_id) : 'null'; ?>;
    let activeRecipientName = <?php echo $recipient_name ? json_encode($recipient_name) : 'null'; ?>;

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

    // Handle errors in adding new tabs and content
    window.onerror = function(message, source, lineno, colno, error) {
        console.error('Global error handler:', message, source, lineno, colno, error);
    };
</script>
</body>
</html>