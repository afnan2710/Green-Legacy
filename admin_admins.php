<?php
// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: admin_login.php');
    exit;
}

// Database connection
require_once 'db_connect.php';

// Initialize variables
$errors = [];
$admins = [];

// Handle form submission for adding new admin
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_admin'])) {
    $username = trim($_POST['username']);
    $email = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $role = $_POST['role'];

    // Validation
    if (empty($username)) $errors['username'] = 'Username is required';
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    } else {
        // Check if email already exists
        $stmt = $conn->prepare("SELECT admin_id FROM admins WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors['email'] = 'Email already exists';
        }
        $stmt->close();
    }
    
    if (empty($password)) {
        $errors['password'] = 'Password is required';
    } elseif (strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters';
    }
    if ($password !== $confirm_password) {
        $errors['confirm_password'] = 'Passwords do not match';
    }

    // If no errors, insert admin
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO admins (username, email, password, role) VALUES (?, ?, ?, ?)");
        $stmt->bind_param("ssss", $username, $email, $hashed_password, $role);
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "Admin added successfully";
            $_SESSION['message_type'] = "success";
            header("Location: admin_admins.php");
            exit;
        } else {
            $errors['database'] = "Error adding admin: " . $stmt->error;
        }
        $stmt->close();
    }
}

// Handle admin deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_admin'])) {
    $admin_id = $_POST['admin_id'];
    
    // Prevent deleting own account
    if ($admin_id == $_SESSION['admin_id']) {
        $_SESSION['message'] = "You cannot delete your own account";
        $_SESSION['message_type'] = "error";
    } else {
        $stmt = $conn->prepare("DELETE FROM admins WHERE admin_id = ?");
        $stmt->bind_param("i", $admin_id);
        if ($stmt->execute()) {
            $_SESSION['message'] = "Admin deleted successfully";
            $_SESSION['message_type'] = "success";
        } else {
            $_SESSION['message'] = "Error deleting admin: " . $stmt->error;
            $_SESSION['message_type'] = "error";
        }
        $stmt->close();
    }
    header("Location: admin_admins.php");
    exit;
}

// Get all admins from database
$stmt = $conn->prepare("SELECT admin_id, username, email, role, created_at FROM admins ORDER BY created_at DESC");
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $admins[] = $row;
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
  <title>Admin Management - Admin Panel</title>
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
    
    /* Role badges */
    .badge-admin {
      background-color: #d1fae5;
      color: #065f46;
    }
    
    .badge-editor {
      background-color: #fef3c7;
      color: #92400e;
    }
    
    .badge-consultant {
      background-color: #e0e7ff;
      color: #3730a3;
    }
  </style>
</head>
<body>
  <div class="content-container">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Admin Accounts</h1>
      <button onclick="openAddAdminModal()" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center">
        <i class="fas fa-plus mr-2"></i> Add Admin
      </button>
    </div>
    
    <!-- Display messages -->
    <?php if (isset($_SESSION['message'])): ?>
      <div class="mb-4 p-3 rounded-lg text-sm <?php echo $_SESSION['message_type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?>">
        <?php 
          echo $_SESSION['message']; 
          unset($_SESSION['message']);
          unset($_SESSION['message_type']);
        ?>
      </div>
    <?php endif; ?>
    
    <!-- Admins Table -->
    <div class="bg-white rounded-lg shadow overflow-hidden">
      <table class="min-w-full divide-y divide-gray-200">
        <thead class="bg-gray-50">
          <tr>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">ID</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Username</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Role</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Created</th>
            <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
          </tr>
        </thead>
        <tbody class="bg-white divide-y divide-gray-200">
          <?php if (empty($admins)): ?>
            <tr>
              <td colspan="6" class="px-6 py-4 text-center text-gray-500">No admins found</td>
            </tr>
          <?php else: ?>
            <?php foreach ($admins as $admin): ?>
              <tr>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo $admin['admin_id']; ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900"><?php echo htmlspecialchars($admin['username']); ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo htmlspecialchars($admin['email']); ?></td>
                <td class="px-6 py-4 whitespace-nowrap">
                  <span class="px-2 py-1 text-xs rounded-full <?php 
                    echo $admin['role'] === 'admin' ? 'badge-admin' : 
                         ($admin['role'] === 'consultant' ? 'badge-consultant' : 'badge-editor'); 
                  ?>">
                    <?php echo ucfirst($admin['role']); ?>
                  </span>
                </td>
                <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500"><?php echo date('M d, Y', strtotime($admin['created_at'])); ?></td>
                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                  <?php if ($admin['admin_id'] != $_SESSION['admin_id']): ?>
                    <form method="POST" class="inline">
                      <input type="hidden" name="admin_id" value="<?php echo $admin['admin_id']; ?>">
                      <button type="submit" name="delete_admin" class="text-red-600 hover:text-red-900" onclick="return confirm('Are you sure you want to delete this admin?')">
                        <i class="fas fa-trash-alt mr-1"></i> Delete
                      </button>
                    </form>
                  <?php else: ?>
                    <span class="text-gray-400">Current account</span>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
  
  <!-- Add Admin Modal -->
  <div id="addAdminModal" class="modal">
    <div class="modal-content">
      <div class="p-6">
        <div class="flex justify-between items-center mb-4">
          <h3 class="text-lg font-medium text-gray-900">Add New Admin</h3>
          <button onclick="closeModal()" class="text-gray-400 hover:text-gray-500">
            <i class="fas fa-times"></i>
          </button>
        </div>
        
        <form method="POST" action="admin_admins.php">
          <div class="space-y-4">
            <!-- Username -->
            <div>
              <label for="username" class="block text-sm font-medium text-gray-700">Username *</label>
              <input type="text" id="username" name="username" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500">
              <?php if (isset($errors['username'])): ?>
                <p class="mt-1 text-sm text-red-600"><?php echo $errors['username']; ?></p>
              <?php endif; ?>
            </div>
            
            <!-- Email -->
            <div>
              <label for="email" class="block text-sm font-medium text-gray-700">Email *</label>
              <input type="email" id="email" name="email" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500">
              <?php if (isset($errors['email'])): ?>
                <p class="mt-1 text-sm text-red-600"><?php echo $errors['email']; ?></p>
              <?php endif; ?>
            </div>
            
            <!-- Password -->
            <div>
              <label for="password" class="block text-sm font-medium text-gray-700">Password *</label>
              <input type="password" id="password" name="password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500">
              <?php if (isset($errors['password'])): ?>
                <p class="mt-1 text-sm text-red-600"><?php echo $errors['password']; ?></p>
              <?php endif; ?>
            </div>
            
            <!-- Confirm Password -->
            <div>
              <label for="confirm_password" class="block text-sm font-medium text-gray-700">Confirm Password *</label>
              <input type="password" id="confirm_password" name="confirm_password" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500">
              <?php if (isset($errors['confirm_password'])): ?>
                <p class="mt-1 text-sm text-red-600"><?php echo $errors['confirm_password']; ?></p>
              <?php endif; ?>
            </div>
            
            <!-- Role -->
            <div>
              <label for="role" class="block text-sm font-medium text-gray-700">Role *</label>
              <select id="role" name="role" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500">
                <option value="admin">Admin</option>
                <option value="editor">Editor</option>
                <option value="consultant">Consultant</option>
              </select>
            </div>
          </div>
          
          <div class="mt-6 flex justify-end space-x-3">
            <button type="button" onclick="closeModal()" class="px-4 py-2 border border-gray-300 rounded-md text-sm font-medium text-gray-700 hover:bg-gray-50">
              Cancel
            </button>
            <button type="submit" name="add_admin" class="px-4 py-2 bg-green-600 text-white rounded-md text-sm font-medium hover:bg-green-700">
              Add Admin
            </button>
          </div>
        </form>
      </div>
    </div>
  </div>
  
  <script>
    // Modal functions
    function openAddAdminModal() {
      document.getElementById('addAdminModal').style.display = 'flex';
    }
    
    function closeModal() {
      document.getElementById('addAdminModal').style.display = 'none';
    }
    
    // Close modal when clicking outside
    window.onclick = function(event) {
      const modal = document.getElementById('addAdminModal');
      if (event.target === modal) {
        closeModal();
      }
    }
  </script>
</body>
</html>