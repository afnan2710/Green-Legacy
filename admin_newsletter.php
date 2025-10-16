<?php
// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
// Database connection
require_once 'db_connect.php';

// Handle actions (delete, send newsletter)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_subscriber'])) {
        $subscriber_id = $_POST['subscriber_id'];
        $stmt = $conn->prepare("DELETE FROM newsletter_subscribers WHERE id = ?");
        $stmt->bind_param("i", $subscriber_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Subscriber deleted successfully";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting subscriber: " . $stmt->error;
            $_SESSION['message_type'] = "error";
        }
        $stmt->close();
    } elseif (isset($_POST['send_newsletter'])) {
        $subject = $_POST['subject'];
        $content = $_POST['content'];
        
        // Get all subscribers
        $subscribers = [];
        $stmt = $conn->prepare("SELECT email FROM newsletter_subscribers WHERE active = 1");
        $stmt->execute();
        $result = $stmt->get_result();
        while ($row = $result->fetch_assoc()) {
            $subscribers[] = $row['email'];
        }
        $stmt->close();
        
        $success_count = 0;
        $error_count = 0;
        
        foreach ($subscribers as $email) {
            $mail = new PHPMailer;
            $mail->isSMTP();
            $mail->Host = 'smtp.gmail.com';
            $mail->SMTPAuth = true;
            $mail->Username = 'info.afnan27@gmail.com';
            $mail->Password = 'rokp jusi apxx wjkn';
            $mail->SMTPSecure = 'tls';
            $mail->Port = 587;
            
            $mail->setFrom('info.afnan27@gmail.com', 'Green Legacy');
            $mail->addAddress($email);
            $mail->isHTML(true);
            
            $mail->Subject = $subject;
            $mail->Body = $content;
            
            if ($mail->send()) {
                $success_count++;
            } else {
                $error_count++;
                error_log('Mailer Error for ' . $email . ': ' . $mail->ErrorInfo);
            }
        }
        
        $_SESSION['message'] = "Newsletter sent to $success_count subscribers. Failed: $error_count";
        $_SESSION['message_type'] = $error_count > 0 ? "warning" : "success";
    }
    
    header("Location: admin_newsletter.php");
    exit;
}

// Get all subscribers
$subscribers = [];
$stmt = $conn->prepare("SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $subscribers[] = $row;
    }
}
$stmt->close();
?>

<?php include 'admin_sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Newsletter Management - Admin Panel</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      margin-left: 280px;
    }
    
    @media (max-width: 1024px) {
      body {
        margin-left: 0;
      }
    }
    
    .content-container {
      padding: 2rem;
    }
    
    /* Status badges */
    .badge-active {
      background-color: #d1fae5;
      color: #065f46;
    }
    
    .badge-inactive {
      background-color: #fee2e2;
      color: #b91c1c;
    }
    
    /* Table styles */
    .table-container {
      overflow-x: auto;
    }
    
    .subscriber-table {
      width: 100%;
      border-collapse: collapse;
    }
    
    .subscriber-table th, .subscriber-table td {
      padding: 0.75rem 1rem;
      text-align: left;
      border-bottom: 1px solid #e5e7eb;
    }
    
    .subscriber-table th {
      background-color: #f9fafb;
      font-weight: 600;
      text-transform: uppercase;
      font-size: 0.75rem;
      letter-spacing: 0.05em;
      color: #6b7280;
    }
    
    .subscriber-table tr:hover {
      background-color: #f9fafb;
    }
    
    /* Action buttons */
    .action-btn {
      padding: 0.25rem 0.5rem;
      border-radius: 0.25rem;
      font-size: 0.875rem;
      transition: all 0.2s;
    }
    
    .delete-btn {
      background-color: #fee2e2;
      color: #b91c1c;
    }
    
    .delete-btn:hover {
      background-color: #fecaca;
    }
    
    /* Modal styles */
    .modal {
      display: none;
      position: fixed;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      background-color: rgba(0, 0, 0, 0.5);
      z-index: 2000;
      justify-content: center;
      align-items: center;
    }
    
    .modal-content {
      background-color: white;
      border-radius: 0.5rem;
      width: 90%;
      max-width: 500px;
      max-height: 90vh;
      overflow-y: auto;
    }
    
    /* Alert messages */
    .alert {
      padding: 0.75rem 1rem;
      border-radius: 0.375rem;
      margin-bottom: 1rem;
    }
    
    .alert-success {
      background-color: #d1fae5;
      color: #065f46;
    }
    
    .alert-error {
      background-color: #fee2e2;
      color: #b91c1c;
    }
    
    .alert-warning {
      background-color: #fef3c7;
      color: #92400e;
    }
  </style>
</head>
<body>
  <div class="content-container">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Newsletter Management</h1>
      <button onclick="openSendModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center">
        <i class="fas fa-paper-plane mr-2"></i> Send Newsletter
      </button>
    </div>
    
    <!-- Display messages -->
    <?php if (isset($_SESSION['message'])): ?>
      <div class="alert alert-<?php echo $_SESSION['message_type']; ?>">
        <?php 
          echo $_SESSION['message']; 
          unset($_SESSION['message']);
          unset($_SESSION['message_type']);
        ?>
      </div>
    <?php endif; ?>
    
    <!-- Subscribers Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden table-container">
      <table class="subscriber-table">
        <thead>
          <tr>
            <th>ID</th>
            <th>Email</th>
            <th>Subscribed On</th>
            <th>Status</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php if (empty($subscribers)): ?>
            <tr>
              <td colspan="5" class="text-center py-4 text-gray-500">No subscribers found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($subscribers as $subscriber): ?>
              <tr>
                <td><?php echo $subscriber['id']; ?></td>
                <td><?php echo htmlspecialchars($subscriber['email']); ?></td>
                <td><?php echo date('M d, Y', strtotime($subscriber['subscribed_at'])); ?></td>
                <td>
                  <span class="<?php echo $subscriber['active'] ? 'badge-active' : 'badge-inactive'; ?> px-2 py-1 rounded-full text-xs font-medium">
                    <?php echo $subscriber['active'] ? 'Active' : 'Inactive'; ?>
                  </span>
                </td>
                <td>
                  <button onclick="confirmDelete(<?php echo $subscriber['id']; ?>)" class="action-btn delete-btn">
                    <i class="fas fa-trash-alt mr-1"></i> Delete
                  </button>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
    
    <!-- Pagination -->
    <div class="flex justify-between items-center mt-4">
      <div class="text-sm text-gray-500">
        Showing 1 to <?php echo count($subscribers); ?> of <?php echo count($subscribers); ?> entries
      </div>
      <div class="flex space-x-2">
        <button class="px-3 py-1 border rounded-md text-sm disabled:opacity-50" disabled>
          Previous
        </button>
        <button class="px-3 py-1 border rounded-md text-sm bg-green-600 text-white">
          1
        </button>
        <button class="px-3 py-1 border rounded-md text-sm disabled:opacity-50" disabled>
          Next
        </button>
      </div>
    </div>
  </div>
  
  <!-- Delete Confirmation Modal -->
  <div id="deleteModal" class="modal">
    <div class="modal-content">
      <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Confirm Deletion</h3>
        <p class="text-gray-600 mb-6">Are you sure you want to delete this subscriber? This action cannot be undone.</p>
        <div class="flex justify-end space-x-3">
          <button onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
            Cancel
          </button>
          <form id="deleteForm" method="POST">
            <input type="hidden" name="subscriber_id" id="deleteSubscriberId">
            <input type="hidden" name="delete_subscriber" value="1">
            <button type="submit" class="px-4 py-2 bg-red-600 text-white rounded-md text-sm font-medium hover:bg-red-700">
              Delete
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Send Newsletter Modal -->
  <div id="sendModal" class="modal">
    <div class="modal-content">
      <div class="p-6">
        <h3 class="text-lg font-medium text-gray-900 mb-4">Send Newsletter</h3>
        <form method="POST" id="newsletterForm">
          <div class="mb-4">
            <label for="subject" class="block text-sm font-medium text-gray-700 mb-1">Subject</label>
            <input type="text" id="subject" name="subject" required
                   class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
          </div>
          <div class="mb-4">
            <label for="content" class="block text-sm font-medium text-gray-700 mb-1">Content</label>
            <textarea id="content" name="content" rows="10" required
                      class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"></textarea>
          </div>
          <div class="flex justify-end space-x-3">
            <button type="button" onclick="closeSendModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
              Cancel
            </button>
            <button type="submit" name="send_newsletter" value="1" class="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700">
              Send Newsletter
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <script>
    // Confirm delete function
    function confirmDelete(subscriberId) {
      document.getElementById('deleteSubscriberId').value = subscriberId;
      document.getElementById('deleteModal').style.display = 'flex';
    }
    
    // Open send newsletter modal
    function openSendModal() {
      document.getElementById('sendModal').style.display = 'flex';
    }
    
    // Close modal functions
    function closeModal() {
      document.getElementById('deleteModal').style.display = 'none';
    }
    
    function closeSendModal() {
      document.getElementById('sendModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
      const deleteModal = document.getElementById('deleteModal');
      const sendModal = document.getElementById('sendModal');
      
      if (event.target === deleteModal) {
        closeModal();
      }
      
      if (event.target === sendModal) {
        closeSendModal();
      }
    }
  </script>
</body>
</html>