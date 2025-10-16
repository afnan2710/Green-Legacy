<?php
require_once 'db_connect.php';
session_start();

if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// Check if message ID is provided
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: admin_messages.php");
    exit();
}

$message_id = $_GET['id'];

// Fetch the message
$stmt = $conn->prepare("SELECT * FROM messages WHERE id = ?");
$stmt->bind_param("i", $message_id);
$stmt->execute();
$result = $stmt->get_result();
$message = $result->fetch_assoc();

// If message not found
if (!$message) {
    header("Location: admin_messages.php");
    exit();
}

// Mark message as read
$update_stmt = $conn->prepare("UPDATE messages SET is_read = TRUE WHERE id = ?");
$update_stmt->bind_param("i", $message_id);
$update_stmt->execute();

require_once 'admin_sidebar.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Message - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body class="bg-gray-100">
    <?php include 'admin_sidebar.php'; ?>
    
    <div class="ml-0 lg:ml-64 p-6">
        <div class="max-w-4xl mx-auto">
            <div class="flex justify-between items-center mb-8">
                <h1 class="text-3xl font-bold text-gray-800">Message Details</h1>
                <a href="admin_messages.php" class="text-green-600 hover:text-green-800">
                    <i class="fas fa-arrow-left mr-1"></i> Back to Messages
                </a>
            </div>
            
            <div class="bg-white rounded-xl shadow-lg p-8">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">From</h3>
                        <p class="mt-1 text-lg font-medium text-gray-900"><?php echo htmlspecialchars($message['name']); ?></p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Email</h3>
                        <p class="mt-1 text-lg font-medium text-gray-900"><?php echo htmlspecialchars($message['email']); ?></p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Date</h3>
                        <p class="mt-1 text-lg font-medium text-gray-900">
                            <?php echo date('F j, Y \a\t g:i a', strtotime($message['created_at'])); ?>
                        </p>
                    </div>
                    <div>
                        <h3 class="text-sm font-medium text-gray-500">Status</h3>
                        <span class="mt-1 inline-flex items-center px-3 py-1 rounded-full text-sm font-medium 
                            <?php echo $message['is_read'] ? 'bg-gray-100 text-gray-800' : 'bg-green-100 text-green-800'; ?>">
                            <?php echo $message['is_read'] ? 'Read' : 'New'; ?>
                        </span>
                    </div>
                </div>
                
                <?php if (!empty($message['subject'])): ?>
                <div class="mb-8">
                    <h3 class="text-sm font-medium text-gray-500">Subject</h3>
                    <p class="mt-1 text-xl font-semibold text-gray-900"><?php echo htmlspecialchars($message['subject']); ?></p>
                </div>
                <?php endif; ?>
                
                <div>
                    <h3 class="text-sm font-medium text-gray-500">Message</h3>
                    <div class="mt-4 p-4 bg-gray-50 rounded-lg">
                        <p class="text-gray-800 whitespace-pre-line"><?php echo htmlspecialchars($message['message']); ?></p>
                    </div>
                </div>
                
                <div class="mt-8 pt-6 border-t border-gray-200 flex justify-end space-x-4">
                    <a href="admin_delete_message.php?id=<?php echo $message['id']; ?>" 
                       class="inline-flex items-center px-4 py-2 border border-red-300 text-sm font-medium rounded-md text-red-700 bg-white hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500"
                       onclick="return confirm('Are you sure you want to delete this message?');">
                        <i class="fas fa-trash mr-2"></i> Delete Message
                    </a>
                    <a href="mailto:<?php echo htmlspecialchars($message['email']); ?>?subject=Re: <?php echo urlencode($message['subject'] ?? 'Your message'); ?>" 
                       class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                        <i class="fas fa-reply mr-2"></i> Reply via Email
                    </a>
                </div>
            </div>
        </div>
    </div>
</body>
</html>