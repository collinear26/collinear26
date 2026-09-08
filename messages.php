<?php
session_start();
include 'db_conn.php';
include 'csrf.php';

if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit();
}

$my_id = intval($_SESSION['user_id']);
$my_name = trim(($_SESSION['firstname'] ?? '') . ' ' . ($_SESSION['lastname'] ?? '')) ?: 'You';

// Avatar color palette — pinipili base sa user ID, para consistent ang
// kulay ng bawat tao kahit sino ang tumingin
$avatar_palette = ['#0ea5e9', '#f97316', '#8b5cf6', '#ef4444', '#10b981', '#eab308', '#ec4899', '#14b8a6', '#6366f1', '#f43f5e'];

function getAvatarColor($user_id, $palette) {
    return $palette[$user_id % count($palette)];
}

function getInitials($firstname, $lastname) {
    $f = !empty($firstname) ? strtoupper(substr($firstname, 0, 1)) : '';
    $l = !empty($lastname) ? strtoupper(substr($lastname, 0, 1)) : '';
    return $f . $l ?: '?';
}

// I-format ang oras: kung ngayong araw, oras lang (h:i A); kung hindi, petsa (M d)
function formatConvTime($datetime_str) {
    if (empty($datetime_str)) return '';
    $ts = strtotime($datetime_str);
    if (date('Y-m-d', $ts) === date('Y-m-d')) {
        return date('h:i A', $ts);
    }
    return date('M d', $ts);
}

// Kunin ang lahat ng conversations ng naka-login na user, kasama ang
// impormasyon ng KABILANG tao sa bawat isa
$conv_query = mysqli_query($conn, "
    SELECT c.id AS conv_id, c.last_message, c.last_message_time,
           u.id AS other_id, u.firstname, u.lastname, u.department,
           (SELECT COUNT(*) FROM messages m WHERE m.conversation_id = c.id AND m.sender_id != $my_id AND m.is_read = 0) AS unread_count
    FROM conversations c
    JOIN users u ON u.id = (CASE WHEN c.user_one_id = $my_id THEN c.user_two_id ELSE c.user_one_id END)
    WHERE c.user_one_id = $my_id OR c.user_two_id = $my_id
    ORDER BY c.last_message_time DESC, c.created_at DESC
");

// Alamin ang active conversation — mula sa URL, o yung pinaka-huling
// ginamit kung wala namang tinukoy
$active_conv_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

// SECURITY: i-verify na kabilang talaga ang naka-login na user dito bago
// ipakita ang mga detalye at messages nito
$active_conv = null;
if ($active_conv_id > 0) {
    $active_check = mysqli_query($conn, "
        SELECT c.id AS conv_id, u.id AS other_id, u.firstname, u.lastname, u.department,
               u.email, u.user_type, u.id_number, u.account_status
        FROM conversations c
        JOIN users u ON u.id = (CASE WHEN c.user_one_id = $my_id THEN c.user_two_id ELSE c.user_one_id END)
        WHERE c.id = $active_conv_id AND (c.user_one_id = $my_id OR c.user_two_id = $my_id)
        LIMIT 1
    ");
    if ($active_check && mysqli_num_rows($active_check) > 0) {
        $active_conv = mysqli_fetch_assoc($active_check);
    } else {
        $active_conv_id = 0; // hindi pala sakop ng user na ito, i-treat parang walang napili
    }
}

// Kunin ang mga messages ng active conversation (kung meron)
$messages = [];
if ($active_conv_id > 0) {
    $messages_result = mysqli_query($conn, "
        SELECT id, sender_id, message_text, attachment_path, attachment_name, edited_at, is_deleted, created_at 
        FROM messages 
        WHERE conversation_id = $active_conv_id 
        ORDER BY id ASC
    ");
    if ($messages_result) {
        while ($row = mysqli_fetch_assoc($messages_result)) {
            $messages[] = $row;
        }
    }

    // I-mark na "read" ang lahat ng messages dito na galing sa KABILANG tao
    // (hindi sa akin), dahil binubuksan na ngayon ng user ang conversation na ito
    mysqli_query($conn, "UPDATE messages SET is_read = 1 WHERE conversation_id = $active_conv_id AND sender_id != $my_id AND is_read = 0");
}
$last_message_id = !empty($messages) ? end($messages)['id'] : 0;

// Kunin ang lahat ng IBANG users (para sa "New Conversation" modal)
$all_users_result = mysqli_query($conn, "SELECT id, firstname, lastname, department FROM users WHERE id != $my_id ORDER BY firstname ASC");
$all_users = [];
if ($all_users_result) {
    while ($row = mysqli_fetch_assoc($all_users_result)) {
        $all_users[] = $row;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Messages | ASCOT RecordsHub</title>

    <!-- Lucide Icons -->
    <script src="https://unpkg.com/lucide@latest"></script>
    <!-- SweetAlert2 (para sa Delete Conversation / Unsend confirmations) -->
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <!-- External Stylesheet -->
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">

    <script>
        if (localStorage.getItem('sidebar-collapsed') === 'true') {
            document.documentElement.classList.add('sidebar-is-collapsed');
        }
        var savedTheme = localStorage.getItem('theme');
        if (savedTheme === 'dark' || savedTheme === 'light') {
            document.documentElement.setAttribute('data-theme', savedTheme);
        }
    </script>

    <style>
        .content { padding: 12px 36px 36px 36px; flex: 1; overflow: hidden; }
        .chat-container { height: 100%; background: rgba(255, 255, 255, 0.75); backdrop-filter: blur(16px); -webkit-backdrop-filter: blur(16px); border: 1px solid var(--surface-translucent); border-radius: 16px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.08); display: flex; overflow: hidden; }
        
        .chat-sidebar { width: 320px; border-right: 1px solid var(--brand-soft-15); display: flex; flex-direction: column; background: rgba(255, 255, 255, 0.4); }
        .chat-search { padding: 16px; border-bottom: 1px solid var(--brand-soft-10); display: flex; gap: 8px; align-items: center; }
        .search-input-box { display: flex; align-items: center; gap: 8px; background: var(--surface-translucent); padding: 8px 12px; border-radius: 8px; border: 1px solid var(--brand-soft-20); flex: 1; }
        .search-input-box input { border: none; background: transparent; outline: none; font-size: 12px; width: 100%; font-weight: 600; }
        .new-conv-btn { background: var(--brand-solid); color: var(--white); border: none; width: 34px; height: 34px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; flex-shrink: 0; transition: 0.2s; }
        .new-conv-btn:hover { background: var(--brand-hover); }
        .conv-list { flex: 1; overflow-y: auto; }
        .conv-item { display: flex; align-items: center; gap: 12px; padding: 14px 16px; border-bottom: 1px solid var(--brand-soft-08); cursor: pointer; transition: all 0.2s ease; text-decoration: none; color: inherit; }
        .conv-item:hover { background: var(--brand-soft-08); }
        .conv-item.active { background: var(--brand-soft-15); border-left: 4px solid var(--brand); }
        .conv-avatar { width: 40px; height: 40px; border-radius: 10px; color: var(--white); font-weight: 800; display: flex; align-items: center; justify-content: center; font-size: 14px; position: relative; flex-shrink: 0; }
        .conv-details { flex: 1; min-width: 0; }
        .conv-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 4px; }
        .conv-name { font-size: 13px; font-weight: 700; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .conv-time { font-size: 10px; color: var(--text-muted); font-weight: 600; }
        .conv-msg { font-size: 11.5px; color: var(--status-neutral-text); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; font-weight: 500; }
        .empty-conv-list { padding: 30px 20px; text-align: center; color: var(--text-muted); font-size: 12px; }

        /* UNREAD CONVERSATION STYLING: naka-bold ang pangalan at preview
           habang may unread pa, para malaman agad kung sino ang may bagong
           mensahe nang hindi na kailangang buksan muna ang bawat isa */
        .conv-item.has-unread .conv-name { font-weight: 800; color: var(--brand); }
        .conv-item.has-unread .conv-msg { font-weight: 700; color: var(--text-primary); }
        .conv-item.has-unread .conv-time { color: var(--brand); font-weight: 800; }
        .conv-unread-dot {
            background: var(--brand-solid);
            color: var(--white);
            font-size: 10px;
            font-weight: 800;
            min-width: 18px;
            height: 18px;
            border-radius: 9px;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 0 5px;
            flex-shrink: 0;
        }

        .chat-main { flex: 1; display: flex; flex-direction: column; background: rgba(255, 255, 255, 0.2); position: relative; }
        .chat-header { padding: 14px 20px; border-bottom: 1px solid var(--brand-soft-15); background: var(--surface-translucent); display: flex; align-items: center; justify-content: space-between; position: relative; }
        .active-user { display: flex; align-items: center; gap: 10px; }
        .active-title h3 { font-size: 14px; font-weight: 800; color: var(--text-primary); margin: 0; }
        .active-title p { font-size: 11px; color: var(--brand); font-weight: 600; margin: 0; }

        .chat-messages { flex: 1; padding: 20px; overflow-y: auto; display: flex; flex-direction: column; gap: 14px; }
        .msg-row { display: flex; align-items: flex-end; gap: 6px; max-width: 75%; }
        .msg-row.mine { align-self: flex-end; flex-direction: row-reverse; }
        .msg-bubble { max-width: 100%; padding: 10px 14px; border-radius: 14px; font-size: 12.5px; font-weight: 600; line-height: 1.4; position: relative; }
        .msg-incoming { background: var(--surface-translucent); color: var(--text-heading); border-bottom-left-radius: 2px; box-shadow: 0 2px 8px var(--shadow-soft); }
        .msg-outgoing { background: var(--brand-solid); color: var(--white); border-bottom-right-radius: 2px; box-shadow: 0 2px 10px rgba(6, 78, 59, 0.3); }
        .msg-time { font-size: 9.5px; margin-top: 4px; display: block; text-align: right; opacity: 0.75; }
        .msg-edited-tag { font-size: 9px; opacity: 0.65; font-style: italic; margin-left: 4px; }
        .msg-deleted { font-style: italic; opacity: 0.65; }

        /* Attachment card sa loob ng message bubble */
        .msg-attachment { display: flex; align-items: center; gap: 8px; background: rgba(0,0,0,0.08); padding: 8px 10px; border-radius: 8px; margin-bottom: 6px; text-decoration: none; color: inherit; }
        .msg-outgoing .msg-attachment { background: rgba(255,255,255,0.15); }
        .msg-attachment-name { font-size: 11.5px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; max-width: 160px; }

        /* Hover actions (Edit/Unsend) — lumalabas lang sa sariling messages */
        .msg-actions { display: none; flex-direction: column; gap: 2px; }
        .msg-row.mine:hover .msg-actions { display: flex; }
        .msg-action-btn { background: rgba(0,0,0,0.06); border: none; width: 22px; height: 22px; border-radius: 6px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--status-neutral-text); }
        .msg-action-btn:hover { background: var(--shadow-medium); color: var(--brand); }

        /* Delete Conversation button sa header */
        .chat-header-actions { display: flex; gap: 6px; }
        .chat-header-btn { background: none; border: none; width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--text-muted); }
        .chat-header-btn:hover { background: rgba(220, 38, 38, 0.1); color: var(--status-danger-solid); }

        /* File attach button sa input area */
        .attach-btn { background: var(--brand-soft-08); border: none; width: 38px; height: 38px; border-radius: 10px; display: flex; align-items: center; justify-content: center; cursor: pointer; color: var(--brand); flex-shrink: 0; }
        .attach-btn:hover { background: var(--brand-soft-15); }
        .attach-preview { display: flex; align-items: center; gap: 6px; background: var(--brand-soft-08); padding: 4px 10px; border-radius: 8px; font-size: 11px; font-weight: 700; color: var(--brand); }
        .attach-preview button { background: none; border: none; cursor: pointer; color: var(--status-danger-solid); font-weight: 800; }

        .chat-input-area { padding: 14px 20px; background: rgba(255, 255, 255, 0.7); border-top: 1px solid var(--brand-soft-15); display: flex; align-items: center; gap: 10px; }
        .chat-input-area input { flex: 1; background: var(--surface); border: 1px solid var(--brand-soft-25); padding: 10px 14px; border-radius: 10px; font-size: 12.5px; font-weight: 600; outline: none; }
        .send-btn { background: var(--brand-solid); color: var(--white); border: none; padding: 10px 16px; border-radius: 10px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 6px; box-shadow: 0 4px 12px rgba(6, 78, 59, 0.3); }
        .send-btn:hover { background: var(--brand-hover); }
        .send-btn:disabled { opacity: 0.6; cursor: not-allowed; }

        /* CONTACT PROFILE PANEL (ikatlong column) — simpleng buod ng
           impormasyon ng kausap, katabi ng chat thread. Nagpapakita lang
           kapag may napiling conversation; nawawala kapag wala pang napili
           o sa maliliit na screen (tingnan ang mobile media query sa ibaba). */
        .chat-profile-panel {
            width: 260px;
            flex-shrink: 0;
            border-left: 1px solid var(--brand-soft-15);
            background: rgba(255, 255, 255, 0.5);
            overflow-y: auto;
            padding: 28px 20px;
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
        }
        .profile-avatar-lg {
            width: 72px;
            height: 72px;
            border-radius: 18px;
            color: var(--white);
            font-weight: 800;
            font-size: 24px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 14px;
        }
        .profile-panel-name { font-size: 15px; font-weight: 800; color: var(--text-primary); margin: 0; }
        .profile-panel-role {
            display: inline-block;
            margin-top: 6px;
            padding: 3px 10px;
            border-radius: 999px;
            background: var(--brand-soft-10);
            color: var(--brand);
            font-size: 10.5px;
            font-weight: 800;
            text-transform: uppercase;
            letter-spacing: .03em;
        }
        .profile-panel-section {
            width: 100%;
            margin-top: 24px;
            padding-top: 18px;
            border-top: 1px solid var(--brand-soft-10);
            text-align: left;
        }
        .profile-panel-section-title {
            font-size: 10.5px;
            font-weight: 800;
            color: var(--text-muted);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin: 0 0 12px 0;
        }
        .profile-panel-row { display: flex; align-items: flex-start; gap: 10px; margin-bottom: 14px; }
        .profile-panel-row:last-child { margin-bottom: 0; }
        .profile-panel-row i { width: 15px; height: 15px; color: var(--brand); flex-shrink: 0; margin-top: 1px; }
        .profile-panel-row-label { font-size: 10px; color: var(--text-faint); font-weight: 700; text-transform: uppercase; letter-spacing: .03em; }
        .profile-panel-row-value { font-size: 12.5px; color: var(--text-heading); font-weight: 600; word-break: break-word; }

        /* Sa maliit na screen, wala nang puwang para sa 3rd column — ang
           conversation list at chat lang ang priority doon (parehong
           breakpoint ng ibang responsive work sa buong app). */
        @media (max-width: 1024px) {
            .chat-profile-panel { display: none; }
        }

        /* NEW CONVERSATION MODAL — ang overlay/panel/header chrome ay galing na
           sa shared .modal-overlay/.modal-panel/.modal-header sa style.css;
           dito na lang ang mga bagay na specific sa "user picker" layout na
           ito (search box + scrollable list), dahil hindi ito karaniwang form. */
        .modal-search { padding: 12px 20px; border-bottom: 1px solid var(--brand-soft-08); }
        .modal-user-list { overflow-y: auto; flex: 1; }
        .modal-user-item { display: flex; align-items: center; gap: 10px; padding: 12px 20px; cursor: pointer; text-decoration: none; color: inherit; transition: 0.2s; }
        .modal-user-item:hover { background: rgba(6, 78, 59, 0.06); }
        .modal-user-item .conv-avatar { width: 34px; height: 34px; font-size: 12px; }
        .modal-user-name { font-size: 13px; font-weight: 700; color: var(--text-primary); }
        .modal-user-dept { font-size: 11px; color: var(--text-muted); }
    </style>
</head>
<body>
    <div class="app-container">
        
        <?php include 'sidebar.php'; ?>

        <div class="main-wrapper">
            <div class="top-header">
                <div class="page-title">
                    <h1>Internal Communication Hub</h1>
                    <p>Direct communication and document clarification channel between staff members</p>
                </div>
            </div>

            <div class="content">
                <div class="chat-container">
                    
                    <!-- CONVERSATIONS LIST -->
                    <div class="chat-sidebar">
                        <div class="chat-search">
                            <div class="search-input-box">
                                <i data-lucide="search" style="width: 14px; color: var(--text-muted);"></i>
                                <input type="text" id="searchInput" placeholder="Search conversations..." onkeyup="filterConversations()">
                            </div>
                            <button type="button" class="new-conv-btn" title="Start new conversation" onclick="openNewConvModal()">
                                <i data-lucide="square-pen" style="width: 15px;"></i>
                            </button>
                        </div>
                        <div class="conv-list" id="convList">
                            <?php if (empty(mysqli_num_rows($conv_query) ?? 0)): ?>
                                <div class="empty-conv-list">No conversations yet. Click <i data-lucide="square-pen" style="width:12px; vertical-align: middle;"></i> to start one.</div>
                            <?php else: ?>
                                <?php while ($conv = mysqli_fetch_assoc($conv_query)): ?>
                                    <?php
                                        $c_color = getAvatarColor($conv['other_id'], $avatar_palette);
                                        $c_initials = getInitials($conv['firstname'], $conv['lastname']);
                                        $c_name = htmlspecialchars($conv['firstname'] . ' ' . $conv['lastname']);
                                        $c_unread = intval($conv['unread_count']);
                                    ?>
                                    <a href="messages.php?id=<?php echo $conv['conv_id']; ?>" class="conv-item <?php echo ($conv['conv_id'] == $active_conv_id) ? 'active' : ''; ?> <?php echo ($c_unread > 0) ? 'has-unread' : ''; ?>" data-name="<?php echo strtolower($c_name); ?>">
                                        <div class="conv-avatar" style="background: <?php echo $c_color; ?>;"><?php echo $c_initials; ?></div>
                                        <div class="conv-details">
                                            <div class="conv-header">
                                                <span class="conv-name"><?php echo $c_name; ?></span>
                                                <span class="conv-time"><?php echo formatConvTime($conv['last_message_time']); ?></span>
                                            </div>
                                            <div class="conv-msg"><?php echo htmlspecialchars($conv['last_message'] ?? 'Start the conversation...'); ?></div>
                                        </div>
                                        <?php if ($c_unread > 0): ?>
                                            <span class="conv-unread-dot"><?php echo $c_unread > 9 ? '9+' : $c_unread; ?></span>
                                        <?php endif; ?>
                                    </a>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- ACTIVE CHAT AREA -->
                    <div class="chat-main">
                        <?php if ($active_conv): ?>
                            <?php
                                $active_color = getAvatarColor($active_conv['other_id'], $avatar_palette);
                                $active_initials = getInitials($active_conv['firstname'], $active_conv['lastname']);
                                $active_name = htmlspecialchars($active_conv['firstname'] . ' ' . $active_conv['lastname']);
                            ?>
                            <div class="chat-header">
                                <div class="active-user">
                                    <div class="conv-avatar" style="background: <?php echo $active_color; ?>; width: 36px; height: 36px; font-size: 13px;"><?php echo $active_initials; ?></div>
                                    <div class="active-title">
                                        <h3><?php echo $active_name; ?></h3>
                                        <p><?php echo htmlspecialchars($active_conv['department'] ?? ''); ?></p>
                                    </div>
                                </div>
                                <div class="chat-header-actions">
                                    <button type="button" class="chat-header-btn" title="Delete Conversation" onclick="confirmDeleteConversation(<?php echo $active_conv_id; ?>)">
                                        <i data-lucide="trash-2" style="width: 16px;"></i>
                                    </button>
                                </div>
                            </div>

                            <div class="chat-messages" id="chatMessages" data-conv-id="<?php echo $active_conv_id; ?>" data-last-id="<?php echo $last_message_id; ?>">
                                <?php if (empty($messages)): ?>
                                    <p style="text-align: center; color: var(--text-muted); font-size: 12px; margin-top: 20px;">No messages in this thread yet. Say hi!</p>
                                <?php else: ?>
                                    <?php foreach ($messages as $msg): ?>
                                        <?php 
                                            $is_mine = intval($msg['sender_id']) === $my_id;
                                            $is_deleted = !empty($msg['is_deleted']);
                                            $is_edited = !empty($msg['edited_at']);
                                        ?>
                                        <div class="msg-row <?php echo $is_mine ? 'mine' : ''; ?>">
                                            <?php if ($is_mine && !$is_deleted): ?>
                                                <div class="msg-actions">
                                                    <button type="button" class="msg-action-btn" title="Edit" onclick="startEditMessage(<?php echo $msg['id']; ?>)"><i data-lucide="pencil" style="width:12px;"></i></button>
                                                    <button type="button" class="msg-action-btn" title="Unsend" onclick="confirmUnsendMessage(<?php echo $msg['id']; ?>)"><i data-lucide="trash-2" style="width:12px;"></i></button>
                                                </div>
                                            <?php endif; ?>
                                            <div class="msg-bubble <?php echo $is_mine ? 'msg-outgoing' : 'msg-incoming'; ?>" id="msg-<?php echo $msg['id']; ?>" data-msg-id="<?php echo $msg['id']; ?>">
                                                <?php if ($is_deleted): ?>
                                                    <span class="msg-deleted">This message was unsent</span>
                                                <?php else: ?>
                                                    <?php if (!empty($msg['attachment_path'])): ?>
                                                        <a href="download_attachment.php?id=<?php echo (int) $msg['id']; ?>" target="_blank" class="msg-attachment">
                                                            <i data-lucide="paperclip" style="width:13px; flex-shrink:0;"></i>
                                                            <span class="msg-attachment-name"><?php echo htmlspecialchars($msg['attachment_name']); ?></span>
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (!empty(trim($msg['message_text']))): ?>
                                                        <span class="msg-text"><?php echo nl2br(htmlspecialchars($msg['message_text'])); ?></span>
                                                    <?php endif; ?>
                                                    <span class="msg-time"><?php echo date('h:i A', strtotime($msg['created_at'])); ?><?php if ($is_edited): ?><span class="msg-edited-tag">(edited)</span><?php endif; ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>

                            <form id="chatForm" class="chat-input-area" style="margin: 0;" enctype="multipart/form-data">
                                <input type="hidden" name="conversation_id" value="<?php echo $active_conv_id; ?>">
                                <?php csrf_field(); ?>
                                <input type="file" name="attachment" id="attachmentInput" style="display: none;" accept=".pdf,.docx,.doc,.jpg,.jpeg,.png" onchange="showAttachPreview()">
                                <button type="button" class="attach-btn" title="Attach a file" onclick="document.getElementById('attachmentInput').click()">
                                    <i data-lucide="paperclip" style="width: 16px;"></i>
                                </button>
                                <div id="attachPreviewWrap" style="display:none;">
                                    <span class="attach-preview">
                                        <i data-lucide="file" style="width:12px;"></i>
                                        <span id="attachPreviewName"></span>
                                        <button type="button" onclick="clearAttachment()">&times;</button>
                                    </span>
                                </div>
                                <input type="text" name="message_text" id="messageInput" placeholder="Type a message or attach a document..." autocomplete="off">
                                <button type="submit" id="sendBtn" class="send-btn"><i data-lucide="send" style="width: 14px;"></i> Send</button>
                            </form>
                        <?php else: ?>
                            <div style="display: flex; align-items: center; justify-content: center; height: 100%; color: var(--text-muted); flex-direction: column; gap: 10px;">
                                <i data-lucide="message-square" style="width: 40px; opacity: 0.4;"></i>
                                <p>Select a conversation, or start a new one</p>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if ($active_conv): ?>
                        <!-- CONTACT PROFILE PANEL -->
                        <div class="chat-profile-panel">
                            <div class="profile-avatar-lg" style="background: <?php echo $active_color; ?>;"><?php echo $active_initials; ?></div>
                            <p class="profile-panel-name"><?php echo $active_name; ?></p>
                            <span class="profile-panel-role"><?php echo htmlspecialchars(ucfirst(strtolower($active_conv['user_type'] ?? 'Staff'))); ?></span>

                            <div class="profile-panel-section">
                                <p class="profile-panel-section-title">Contact Information</p>
                                <div class="profile-panel-row">
                                    <i data-lucide="building-2"></i>
                                    <div>
                                        <div class="profile-panel-row-label">Department</div>
                                        <div class="profile-panel-row-value"><?php echo htmlspecialchars($active_conv['department'] ?: 'Not set'); ?></div>
                                    </div>
                                </div>
                                <div class="profile-panel-row">
                                    <i data-lucide="mail"></i>
                                    <div>
                                        <div class="profile-panel-row-label">Email</div>
                                        <div class="profile-panel-row-value"><?php echo htmlspecialchars($active_conv['email'] ?? 'Not set'); ?></div>
                                    </div>
                                </div>
                                <div class="profile-panel-row">
                                    <i data-lucide="id-card"></i>
                                    <div>
                                        <div class="profile-panel-row-label">ID Number</div>
                                        <div class="profile-panel-row-value"><?php echo htmlspecialchars($active_conv['id_number'] ?? 'Not set'); ?></div>
                                    </div>
                                </div>
                                <div class="profile-panel-row">
                                    <i data-lucide="circle-check-big"></i>
                                    <div>
                                        <div class="profile-panel-row-label">Account Status</div>
                                        <div class="profile-panel-row-value"><?php echo htmlspecialchars(ucfirst(strtolower($active_conv['account_status'] ?? 'active'))); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endif; ?>

                </div>
            </div>
        </div>
    </div>

    <!-- NEW CONVERSATION MODAL -->
    <div id="newConvModal" class="modal-overlay" style="display: none;">
        <div class="modal-panel modal-panel--sm" style="max-height: 70vh;">
            <div class="modal-header">
                <div class="modal-header-text"><h3>Start New Conversation</h3></div>
                <button type="button" class="modal-close" onclick="closeNewConvModal()">&times;</button>
            </div>
            <div class="modal-search">
                <div class="search-input-box">
                    <i data-lucide="search" style="width: 14px; color: var(--text-muted);"></i>
                    <input type="text" id="modalSearchInput" placeholder="Search users..." onkeyup="filterModalUsers()">
                </div>
            </div>
            <div class="modal-user-list" id="modalUserList">
                <?php foreach ($all_users as $u): ?>
                    <?php
                        $u_color = getAvatarColor($u['id'], $avatar_palette);
                        $u_initials = getInitials($u['firstname'], $u['lastname']);
                        $u_name = htmlspecialchars($u['firstname'] . ' ' . $u['lastname']);
                    ?>
                    <a href="start_conversation.php?user_id=<?php echo $u['id']; ?>" class="modal-user-item" data-name="<?php echo strtolower($u_name); ?>">
                        <div class="conv-avatar" style="background: <?php echo $u_color; ?>;"><?php echo $u_initials; ?></div>
                        <div>
                            <div class="modal-user-name"><?php echo $u_name; ?></div>
                            <div class="modal-user-dept"><?php echo htmlspecialchars($u['department'] ?? ''); ?></div>
                        </div>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script src="sidebar.js?v=<?php echo time(); ?>"></script>
    <script>
        // CSRF token para sa mga AJAX POST na hindi galing sa isang totoong <form>
        // (delete conversation/message, edit message) — ang chatForm mismo ay may
        // sarili nang hidden field na csrf_field() na dinagdag sa PHP.
        const CSRF_TOKEN = "<?php echo csrf_token(); ?>";

        lucide.createIcons();

        // Kasalukuyang bukas na conversation (0 kung wala) — ginagamit para
        // malaman kung alin ang dapat naka-highlight sa listahan pagka-refresh
        const activeConvId = <?php echo intval($active_conv_id); ?>;

        const chatMessagesEl = document.getElementById('chatMessages');
        const chatForm = document.getElementById('chatForm');
        const messageInput = document.getElementById('messageInput');
        const sendBtn = document.getElementById('sendBtn');

        // Auto scroll to bottom
        function scrollToBottom() {
            if (chatMessagesEl) chatMessagesEl.scrollTop = chatMessagesEl.scrollHeight;
        }
        scrollToBottom();

        // Helper: gumawa ng HTML para sa isang message bubble (buong row,
        // kasama ang hover action buttons kung sarili mong message)
        function buildMessageBubble(msg) {
            const row = document.createElement('div');
            row.className = 'msg-row' + (msg.is_mine ? ' mine' : '');

            if (msg.is_mine && !msg.is_deleted) {
                const actions = document.createElement('div');
                actions.className = 'msg-actions';
                actions.innerHTML = `
                    <button type="button" class="msg-action-btn" title="Edit" onclick="startEditMessage(${msg.id})"><i data-lucide="pencil" style="width:12px;"></i></button>
                    <button type="button" class="msg-action-btn" title="Unsend" onclick="confirmUnsendMessage(${msg.id})"><i data-lucide="trash-2" style="width:12px;"></i></button>
                `;
                row.appendChild(actions);
            }

            const bubble = document.createElement('div');
            bubble.className = 'msg-bubble ' + (msg.is_mine ? 'msg-outgoing' : 'msg-incoming');
            bubble.id = 'msg-' + msg.id;
            bubble.dataset.msgId = msg.id;

            if (msg.is_deleted) {
                bubble.innerHTML = '<span class="msg-deleted">This message was unsent</span>';
            } else {
                let html = '';
                if (msg.attachment_path) {
                    html += `<a href="download_attachment.php?id=${msg.id}" target="_blank" class="msg-attachment">
                        <i data-lucide="paperclip" style="width:13px; flex-shrink:0;"></i>
                        <span class="msg-attachment-name">${msg.attachment_name}</span>
                    </a>`;
                }
                if (msg.message_text && msg.message_text.trim() !== '') {
                    html += `<span class="msg-text">${msg.message_text.replace(/\n/g, '<br>')}</span>`;
                }
                html += `<span class="msg-time">${msg.created_at}${msg.is_edited ? '<span class="msg-edited-tag">(edited)</span>' : ''}</span>`;
                bubble.innerHTML = html;
            }

            row.appendChild(bubble);
            lucide.createIcons();
            return row;
        }

        // AJAX SEND MESSAGE (para hindi na kailangang mag-full page reload)
        if (chatForm) {
            chatForm.addEventListener('submit', function (e) {
                e.preventDefault();
                const text = messageInput.value.trim();
                const attachmentInput = document.getElementById('attachmentInput');
                const hasFile = attachmentInput.files.length > 0;

                if (!text && !hasFile) return;

                sendBtn.disabled = true;
                const formData = new FormData(chatForm);

                fetch('send_message.php', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    sendBtn.disabled = false;
                    if (data.success) {
                        // Alisin muna ang "no messages yet" placeholder kung meron
                        const placeholder = chatMessagesEl.querySelector('p');
                        if (placeholder) placeholder.remove();

                        chatMessagesEl.appendChild(buildMessageBubble({
                            id: data.id,
                            is_mine: true,
                            message_text: data.message_text,
                            attachment_path: data.attachment_path,
                            attachment_name: data.attachment_name,
                            is_edited: false,
                            is_deleted: false,
                            created_at: data.created_at,
                        }));
                        chatMessagesEl.dataset.lastId = data.id;
                        messageInput.value = '';
                        clearAttachment();
                        scrollToBottom();
                    } else {
                        alert(data.error || 'Failed to send message. Please try again.');
                    }
                })
                .catch(() => {
                    sendBtn.disabled = false;
                    alert('A connection problem occurred. Please try again.');
                });
            });
        }

        // FILE ATTACH: preview ng napiling file bago i-send
        function showAttachPreview() {
            const input = document.getElementById('attachmentInput');
            if (input.files.length > 0) {
                document.getElementById('attachPreviewName').textContent = input.files[0].name;
                document.getElementById('attachPreviewWrap').style.display = 'inline-block';
            }
        }
        function clearAttachment() {
            const input = document.getElementById('attachmentInput');
            if (input) input.value = '';
            const wrap = document.getElementById('attachPreviewWrap');
            if (wrap) wrap.style.display = 'none';
        }

        // DELETE CONVERSATION
        function confirmDeleteConversation(convId) {
            Swal.fire({
                title: 'Delete this conversation?',
                text: 'This will permanently delete all messages in this conversation. This cannot be undone.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Delete',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                confirmButtonColor: 'var(--status-danger-solid)'
            }).then((result) => {
                if (!result.isConfirmed) return;

                const formData = new FormData();
                formData.append('conversation_id', convId);
                formData.append('csrf_token', CSRF_TOKEN);

                fetch('delete_conversation.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            window.location.href = 'messages.php';
                        } else {
                            alert(data.error || 'Failed to delete the conversation.');
                        }
                    })
                    .catch(() => alert('A connection problem occurred.'));
            });
        }

        // UNSEND MESSAGE
        function confirmUnsendMessage(msgId) {
            Swal.fire({
                title: 'Unsend this message?',
                text: 'The other person will see that you unsent this message.',
                icon: 'warning',
                showCancelButton: true,
                confirmButtonText: 'Yes, Unsend',
                cancelButtonText: 'Cancel',
                reverseButtons: true,
                confirmButtonColor: 'var(--status-danger-solid)'
            }).then((result) => {
                if (!result.isConfirmed) return;

                const formData = new FormData();
                formData.append('message_id', msgId);
                formData.append('csrf_token', CSRF_TOKEN);

                fetch('delete_message.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            const bubble = document.getElementById('msg-' + msgId);
                            if (bubble) {
                                bubble.innerHTML = '<span class="msg-deleted">This message was unsent</span>';
                                const row = bubble.closest('.msg-row');
                                const actions = row ? row.querySelector('.msg-actions') : null;
                                if (actions) actions.remove();
                            }
                        } else {
                            alert(data.error || 'Failed to unsend the message.');
                        }
                    })
                    .catch(() => alert('A connection problem occurred.'));
            });
        }

        // EDIT MESSAGE: papalitan ang bubble content ng isang inline text input
        function startEditMessage(msgId) {
            const bubble = document.getElementById('msg-' + msgId);
            if (!bubble) return;

            const currentText = bubble.querySelector('.msg-text')
                ? bubble.querySelector('.msg-text').textContent
                : '';

            bubble.innerHTML = `
                <input type="text" class="edit-msg-input" value="${currentText.replace(/"/g, '&quot;')}" style="width:100%; border:none; outline:none; background:transparent; color:inherit; font:inherit; font-weight:600;">
            `;
            const input = bubble.querySelector('.edit-msg-input');
            input.focus();
            input.select();

            function saveEdit() {
                const newText = input.value.trim();
                if (!newText) { cancelEdit(); return; }

                const formData = new FormData();
                formData.append('message_id', msgId);
                formData.append('new_text', newText);
                formData.append('csrf_token', CSRF_TOKEN);

                fetch('edit_message.php', { method: 'POST', body: formData })
                    .then(res => res.json())
                    .then(data => {
                        if (data.success) {
                            bubble.innerHTML = `<span class="msg-text">${data.message_text.replace(/\n/g, '<br>')}</span><span class="msg-time">${bubble.dataset.time || ''}<span class="msg-edited-tag">(edited)</span></span>`;
                        } else {
                            alert(data.error || 'Failed to save the edit.');
                        }
                    })
                    .catch(() => alert('A connection problem occurred.'));
            }
            function cancelEdit() {
                location.reload();
            }

            input.addEventListener('keydown', function (e) {
                if (e.key === 'Enter') { e.preventDefault(); saveEdit(); }
                if (e.key === 'Escape') { e.preventDefault(); cancelEdit(); }
            });
            input.addEventListener('blur', saveEdit);
        }

        // REAL-TIME POLLING: awtomatikong tumingin ng bagong messages tuwing
        // ilang segundo, nang hindi kailangang i-refresh ang buong page
        if (chatMessagesEl) {
            const convId = chatMessagesEl.dataset.convId;

            function pollNewMessages() {
                const lastId = chatMessagesEl.dataset.lastId || 0;
                fetch(`get_messages.php?conversation_id=${convId}&after_id=${lastId}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data.success && data.messages.length > 0) {
                            const wasNearBottom = (chatMessagesEl.scrollHeight - chatMessagesEl.scrollTop - chatMessagesEl.clientHeight) < 100;

                            data.messages.forEach(msg => {
                                chatMessagesEl.appendChild(buildMessageBubble(msg));
                                chatMessagesEl.dataset.lastId = msg.id;
                            });

                            // I-scroll lang pababa kung malapit na naman sa ilalim ang user dati
                            if (wasNearBottom) scrollToBottom();
                        }
                    })
                    .catch(() => { /* tahimik lang mag-fail, susubukan ulit sa susunod na poll */ });
            }

            setInterval(pollNewMessages, 3000);
        }

        // Search Bar Functionality (conversation list)
        function filterConversations() {
            let input = document.getElementById('searchInput').value.toLowerCase();
            let items = document.getElementsByClassName('conv-item');
            for (let i = 0; i < items.length; i++) {
                let name = items[i].getAttribute('data-name');
                items[i].style.display = name.includes(input) ? "flex" : "none";
            }
        }

        // Helper: buuin ang HTML ng isang conv-item base sa datos galing sa server
        function buildConvItem(conv) {
            const a = document.createElement('a');
            a.href = 'messages.php?id=' + conv.conv_id;
            a.className = 'conv-item' + (conv.conv_id === activeConvId ? ' active' : '') + (conv.unread_count > 0 ? ' has-unread' : '');
            a.setAttribute('data-name', conv.name.toLowerCase());

            const unreadBadge = conv.unread_count > 0
                ? `<span class="conv-unread-dot">${conv.unread_count > 9 ? '9+' : conv.unread_count}</span>`
                : '';

            a.innerHTML = `
                <div class="conv-avatar" style="background: ${conv.color};">${conv.initials}</div>
                <div class="conv-details">
                    <div class="conv-header">
                        <span class="conv-name">${conv.name}</span>
                        <span class="conv-time">${conv.last_time}</span>
                    </div>
                    <div class="conv-msg">${conv.last_message}</div>
                </div>
                ${unreadBadge}
            `;
            return a;
        }

        // REAL-TIME CONVERSATION LIST: awtomatikong i-refresh ang listahan sa
        // kaliwa tuwing ilang segundo, para makita agad kung may bagong
        // conversation o bagong message kahit ibang thread ang bukas mo ngayon
        function pollConversationList() {
            fetch('get_conversations.php')
                .then(res => res.json())
                .then(data => {
                    if (!data.success) return;

                    const convList = document.getElementById('convList');
                    if (!convList) return;

                    if (data.conversations.length === 0) {
                        convList.innerHTML = '<div class="empty-conv-list">No conversations yet. Click <i data-lucide="square-pen" style="width:12px; vertical-align: middle;"></i> to start one.</div>';
                        lucide.createIcons();
                        return;
                    }

                    convList.innerHTML = '';
                    data.conversations.forEach(conv => {
                        convList.appendChild(buildConvItem(conv));
                    });

                    // I-reapply ang kasalukuyang laman ng search box, kung meron,
                    // para hindi mawala ang filter tuwing nagre-refresh ang listahan
                    filterConversations();
                })
                .catch(() => { /* tahimik lang mag-fail, susubukan ulit sa susunod na poll */ });
        }

        setInterval(pollConversationList, 4000);

        // New Conversation Modal
        function openNewConvModal() {
            document.getElementById('newConvModal').style.display = 'flex';
            document.getElementById('modalSearchInput').value = '';
            filterModalUsers();
        }
        function closeNewConvModal() {
            document.getElementById('newConvModal').style.display = 'none';
        }
        function filterModalUsers() {
            let input = document.getElementById('modalSearchInput').value.toLowerCase();
            let items = document.getElementsByClassName('modal-user-item');
            for (let i = 0; i < items.length; i++) {
                let name = items[i].getAttribute('data-name');
                items[i].style.display = name.includes(input) ? "flex" : "none";
            }
        }
        // Isara ang modal kapag na-click sa labas nito
        document.getElementById('newConvModal').addEventListener('click', function (e) {
            if (e.target === this) closeNewConvModal();
        });
    </script>
</body>
</html>