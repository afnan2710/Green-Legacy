<?php
require_once 'db_connect.php';
header('Content-Type: application/json');

session_start();

// Handle different actions
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'start_chat':
            handleStartChat();
            break;
        case 'send_message':
            handleSendMessage();
            break;
        case 'get_messages':
            handleGetMessages();
            break;
        case 'admin_get_sessions':
            handleAdminGetSessions();
            break;
        case 'admin_get_messages':
            handleAdminGetMessages();
            break;
        case 'admin_send_message':
            handleAdminSendMessage();
            break;
        case 'admin_close_session':
            handleAdminCloseSession();
            break;
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}

function handleStartChat() {
    global $conn;
    
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $initialMessage = trim($_POST['message'] ?? '');
    
    if (empty($name) || empty($email)) {
        throw new Exception('Name and email are required');
    }
    
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email format');
    }
    
    // Generate a unique session ID
    $sessionId = uniqid('chat_', true);
    
    // Start transaction
    $conn->begin_transaction();
    
    try {
        // Create new chat session
        $stmt = $conn->prepare("INSERT INTO chat_sessions (id, user_name, user_email) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $sessionId, $name, $email);
        $stmt->execute();
        
        // Add initial message if provided
        if (!empty($initialMessage)) {
            $stmt = $conn->prepare("INSERT INTO chat_messages (session_id, sender_type, message) VALUES (?, 'user', ?)");
            $stmt->bind_param("ss", $sessionId, $initialMessage);
            $stmt->execute();
        }
        
        $conn->commit();
        
        // Return session ID to client
        echo json_encode([
            'success' => true,
            'sessionId' => $sessionId,
            'userName' => $name,
            'userEmail' => $email
        ]);
    } catch (Exception $e) {
        $conn->rollback();
        throw $e;
    }
}

function handleSendMessage() {
    global $conn;
    
    $sessionId = $_POST['sessionId'] ?? '';
    $message = trim($_POST['message'] ?? '');
    
    if (empty($sessionId)) {
        throw new Exception('Session ID is required');
    }
    
    if (empty($message)) {
        throw new Exception('Message cannot be empty');
    }
    
    $stmt = $conn->prepare("INSERT INTO chat_messages (session_id, sender_type, message) VALUES (?, 'user', ?)");
    $stmt->bind_param("ss", $sessionId, $message);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}

function handleGetMessages() {
    global $conn;
    
    $sessionId = $_POST['sessionId'] ?? '';
    $lastMessageId = $_POST['lastMessageId'] ?? 0;
    
    if (empty($sessionId)) {
        throw new Exception('Session ID is required');
    }
    
    // Get new messages
    $stmt = $conn->prepare("
        SELECT id, sender_type, message, created_at 
        FROM chat_messages 
        WHERE session_id = ? AND id > ? 
        ORDER BY created_at ASC
    ");
    $stmt->bind_param("si", $sessionId, $lastMessageId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'sender' => $row['sender_type'],
            'message' => htmlspecialchars($row['message']),
            'time' => date('H:i', strtotime($row['created_at']))
        ];
    }
    
    // Check if session is still active
    $stmt = $conn->prepare("SELECT status FROM chat_sessions WHERE id = ?");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $session = $stmt->get_result()->fetch_assoc();
    
    echo json_encode([
        'success' => true,
        'messages' => $messages,
        'sessionActive' => $session && $session['status'] === 'active'
    ]);
}

// Admin functions
function handleAdminGetSessions() {
    global $conn;
    
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized');
    }
    
    $status = $_POST['status'] ?? 'active';
    $adminId = $_SESSION['admin_id'];
    
    $query = "
        SELECT cs.id, cs.user_name, cs.user_email, cs.status, cs.created_at, cs.updated_at,
               (SELECT COUNT(*) FROM chat_messages cm WHERE cm.session_id = cs.id AND cm.sender_type = 'user' AND cm.is_read = FALSE) as unread_count
        FROM chat_sessions cs
        WHERE cs.status = ?
        ORDER BY cs.updated_at DESC
    ";
    
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $status);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $sessions = [];
    while ($row = $result->fetch_assoc()) {
        $sessions[] = [
            'id' => $row['id'],
            'userName' => $row['user_name'],
            'userEmail' => $row['user_email'],
            'status' => $row['status'],
            'unreadCount' => $row['unread_count'],
            'lastActivity' => $row['updated_at'],
            'created' => $row['created_at']
        ];
    }
    
    echo json_encode(['success' => true, 'sessions' => $sessions]);
}

function handleAdminGetMessages() {
    global $conn;
    
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized');
    }
    
    $sessionId = $_POST['sessionId'] ?? '';
    
    if (empty($sessionId)) {
        throw new Exception('Session ID is required');
    }
    
    // Get all messages for this session
    $stmt = $conn->prepare("
        SELECT id, sender_type, message, created_at 
        FROM chat_messages 
        WHERE session_id = ? 
        ORDER BY created_at ASC
    ");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    $result = $stmt->get_result();
    
    $messages = [];
    while ($row = $result->fetch_assoc()) {
        $messages[] = [
            'id' => $row['id'],
            'sender' => $row['sender_type'],
            'message' => htmlspecialchars($row['message']),
            'time' => date('H:i', strtotime($row['created_at']))
        ];
    }
    
    // Mark messages as read
    $stmt = $conn->prepare("
        UPDATE chat_messages 
        SET is_read = TRUE 
        WHERE session_id = ? AND sender_type = 'user' AND is_read = FALSE
    ");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    
    // Assign admin to session if not already assigned
    $stmt = $conn->prepare("
        UPDATE chat_sessions 
        SET admin_id = ?, status = 'active', updated_at = CURRENT_TIMESTAMP 
        WHERE id = ? AND (admin_id IS NULL OR admin_id = ?)
    ");
    $adminId = $_SESSION['admin_id'];
    $stmt->bind_param("isi", $adminId, $sessionId, $adminId);
    $stmt->execute();
    
    echo json_encode(['success' => true, 'messages' => $messages]);
}

function handleAdminSendMessage() {
    global $conn;
    
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized');
    }
    
    $sessionId = $_POST['sessionId'] ?? '';
    $message = trim($_POST['message'] ?? '');
    
    if (empty($sessionId)) {
        throw new Exception('Session ID is required');
    }
    
    if (empty($message)) {
        throw new Exception('Message cannot be empty');
    }
    
    $adminId = $_SESSION['admin_id'];
    
    $stmt = $conn->prepare("
        INSERT INTO chat_messages (session_id, sender_type, admin_id, message) 
        VALUES (?, 'admin', ?, ?)
    ");
    $stmt->bind_param("sis", $sessionId, $adminId, $message);
    $stmt->execute();
    
    // Update session timestamp
    $stmt = $conn->prepare("
        UPDATE chat_sessions 
        SET updated_at = CURRENT_TIMESTAMP 
        WHERE id = ?
    ");
    $stmt->bind_param("s", $sessionId);
    $stmt->execute();
    
    echo json_encode(['success' => true]);
}

function handleAdminCloseSession() {
    global $conn;
    
    if (!isset($_SESSION['admin_id'])) {
        throw new Exception('Unauthorized');
    }
    
    $sessionId = $_POST['sessionId'] ?? '';
    
    if (empty($sessionId)) {
        throw new Exception('Session ID is required');
    }
    
    $adminId = $_SESSION['admin_id'];
    
    // Start transaction to ensure atomic operation
    $conn->begin_transaction();
    
    try {
        // First check if session exists and is assigned to this admin
        $stmt = $conn->prepare("SELECT id FROM chat_sessions WHERE id = ? AND (admin_id IS NULL OR admin_id = ?) AND status = 'active' FOR UPDATE");
        $stmt->bind_param("si", $sessionId, $adminId);
        $stmt->execute();
        
        if ($stmt->get_result()->num_rows === 0) {
            throw new Exception('Session not found or already closed');
        }
        
        // Update session status
        $stmt = $conn->prepare("UPDATE chat_sessions SET status = 'closed', updated_at = CURRENT_TIMESTAMP WHERE id = ?");
        $stmt->bind_param("s", $sessionId);
        $stmt->execute();
        
        if ($stmt->affected_rows === 0) {
            throw new Exception('Failed to close session');
        }
        
        $conn->commit();
        
        echo json_encode(['success' => true]);
    } catch (Exception $e) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
}