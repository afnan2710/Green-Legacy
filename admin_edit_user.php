<?php
// Start session and check admin authentication
session_start();
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

// Database connection
require_once 'db_connect.php';

// Initialize variables
$errors = [];
$user = null;

// Get user ID from URL
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: admin_users.php');
    exit;
}

$user_id = $_GET['id'];

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    $_SESSION['message'] = "User not found";
    $_SESSION['message_type'] = "error";
    header('Location: admin_users.php');
    exit;
}

$user = $result->fetch_assoc();
$stmt->close();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize and validate inputs
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $dob = $_POST['dob'];
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    if (empty($firstname)) $errors['firstname'] = 'First name is required';
    if (empty($lastname)) $errors['lastname'] = 'Last name is required';
    if (empty($email)) {
        $errors['email'] = 'Email is required';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid email format';
    } else {
        // Check if email already exists (excluding current user)
        $stmt = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
        $stmt->bind_param("si", $email, $user_id);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows > 0) {
            $errors['email'] = 'Email already exists';
        }
        $stmt->close();
    }
    
    if (empty($phone)) $errors['phone'] = 'Phone number is required';
    if (empty($dob)) $errors['dob'] = 'Date of birth is required';
    
    // Password validation only if password is being changed
    if (!empty($password)) {
        if (strlen($password) < 8) {
            $errors['password'] = 'Password must be at least 8 characters';
        }
        if ($password !== $confirm_password) {
            $errors['confirm_password'] = 'Passwords do not match';
        }
    }

    // Handle profile picture upload
    $profile_pic = $user['profile_pic'];
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
        $file_type = $_FILES['profile_pic']['type'];
        
        if (in_array($file_type, $allowed_types)) {
            $upload_dir = 'uploads/profiles/';
            if (!is_dir($upload_dir)) {
                mkdir($upload_dir, 0755, true);
            }
            
            // Delete old profile picture if it exists
            if ($profile_pic && file_exists($upload_dir . $profile_pic)) {
                unlink($upload_dir . $profile_pic);
            }
            
            $ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '.' . $ext;
            $destination = $upload_dir . $filename;
            
            if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
                $profile_pic = $filename;
            } else {
                $errors['profile_pic'] = 'Failed to upload profile picture';
            }
        } else {
            $errors['profile_pic'] = 'Only JPG, PNG, and GIF files are allowed';
        }
    }

    // If no errors, update user
    if (empty($errors)) {
        // Prepare SQL based on whether password is being updated
        if (!empty($password)) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET firstname=?, lastname=?, email=?, phone=?, address=?, dob=?, password=?, profile_pic=? WHERE id=?");
            $stmt->bind_param("ssssssssi", $firstname, $lastname, $email, $phone, $address, $dob, $hashed_password, $profile_pic, $user_id);
        } else {
            $stmt = $conn->prepare("UPDATE users SET firstname=?, lastname=?, email=?, phone=?, address=?, dob=?, profile_pic=? WHERE id=?");
            $stmt->bind_param("sssssssi", $firstname, $lastname, $email, $phone, $address, $dob, $profile_pic, $user_id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['message'] = "User updated successfully";
            $_SESSION['message_type'] = "success";
            header("Location: admin_users.php");
            exit;
        } else {
            $errors['database'] = "Error updating user: " . $stmt->error;
        }
        $stmt->close();
    }
}

?>

<?php include 'admin_sidebar.php'; ?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Edit User - Admin Panel</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      margin-left: 280px; /* Match sidebar width */
    }
    
    @media (max-width: 1024px) {
      body {
        margin-left: 0;
      }
    }
    
    .content-container {
      padding: 2rem;
    }
    
    /* Form styles */
    .form-container {
      max-width: 800px;
      margin: 0 auto;
    }
    
    .form-group {
      margin-bottom: 1.5rem;
    }
    
    .form-label {
      display: block;
      margin-bottom: 0.5rem;
      font-weight: 500;
      color: #374151;
    }
    
    .form-input {
      width: 100%;
      padding: 0.5rem 0.75rem;
      border: 1px solid #d1d5db;
      border-radius: 0.375rem;
      transition: border-color 0.2s;
    }
    
    .form-input:focus {
      outline: none;
      border-color: #10b981;
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.1);
    }
    
    .form-input-error {
      border-color: #ef4444;
    }
    
    .error-message {
      color: #ef4444;
      font-size: 0.875rem;
      margin-top: 0.25rem;
    }
    
    /* File upload */
    .file-upload {
      display: flex;
      align-items: center;
    }
    
    .file-upload-input {
      width: 0.1px;
      height: 0.1px;
      opacity: 0;
      overflow: hidden;
      position: absolute;
      z-index: -1;
    }
    
    .file-upload-label {
      padding: 0.5rem 1rem;
      background-color: #e5e7eb;
      border-radius: 0.375rem;
      cursor: pointer;
      transition: background-color 0.2s;
    }
    
    .file-upload-label:hover {
      background-color: #d1d5db;
    }
    
    .file-upload-name {
      margin-left: 1rem;
      color: #6b7280;
    }
    
    /* Preview image */
    .preview-container {
      margin-top: 1rem;
    }
    
    .preview-image {
      max-width: 150px;
      max-height: 150px;
      border-radius: 0.375rem;
      border: 1px solid #e5e7eb;
    }
  </style>
</head>
<body>
  <div class="content-container">
    <div class="flex justify-between items-center mb-6">
      <h1 class="text-2xl font-bold text-gray-800">Edit User</h1>
      <a href="admin_users.php" class="text-gray-600 hover:text-gray-800 flex items-center">
        <i class="fas fa-arrow-left mr-2"></i> Back to Users
      </a>
    </div>
    
    <!-- Display errors -->
    <?php if (!empty($errors)): ?>
      <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
        <?php foreach ($errors as $error): ?>
          <p><?php echo $error; ?></p>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
    
    <div class="bg-white rounded-lg shadow overflow-hidden p-6 form-container">
      <form action="admin_edit_user.php?id=<?php echo $user_id; ?>" method="POST" enctype="multipart/form-data">
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
          <!-- First Name -->
          <div class="form-group">
            <label for="firstname" class="form-label">First Name *</label>
            <input type="text" id="firstname" name="firstname" value="<?php echo htmlspecialchars($_POST['firstname'] ?? $user['firstname']); ?>" 
                   class="form-input <?php echo isset($errors['firstname']) ? 'form-input-error' : ''; ?>">
            <?php if (isset($errors['firstname'])): ?>
              <span class="error-message"><?php echo $errors['firstname']; ?></span>
            <?php endif; ?>
          </div>
          
          <!-- Last Name -->
          <div class="form-group">
            <label for="lastname" class="form-label">Last Name *</label>
            <input type="text" id="lastname" name="lastname" value="<?php echo htmlspecialchars($_POST['lastname'] ?? $user['lastname']); ?>" 
                   class="form-input <?php echo isset($errors['lastname']) ? 'form-input-error' : ''; ?>">
            <?php if (isset($errors['lastname'])): ?>
              <span class="error-message"><?php echo $errors['lastname']; ?></span>
            <?php endif; ?>
          </div>
          
          <!-- Email -->
          <div class="form-group">
            <label for="email" class="form-label">Email *</label>
            <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? $user['email']); ?>" 
                   class="form-input <?php echo isset($errors['email']) ? 'form-input-error' : ''; ?>">
            <?php if (isset($errors['email'])): ?>
              <span class="error-message"><?php echo $errors['email']; ?></span>
            <?php endif; ?>
          </div>
          
          <!-- Phone -->
          <div class="form-group">
            <label for="phone" class="form-label">Phone *</label>
            <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? $user['phone']); ?>" 
                   class="form-input <?php echo isset($errors['phone']) ? 'form-input-error' : ''; ?>">
            <?php if (isset($errors['phone'])): ?>
              <span class="error-message"><?php echo $errors['phone']; ?></span>
            <?php endif; ?>
          </div>
          
          <!-- Date of Birth -->
          <div class="form-group">
            <label for="dob" class="form-label">Date of Birth *</label>
            <input type="date" id="dob" name="dob" value="<?php echo htmlspecialchars($_POST['dob'] ?? $user['dob']); ?>" 
                   class="form-input <?php echo isset($errors['dob']) ? 'form-input-error' : ''; ?>">
            <?php if (isset($errors['dob'])): ?>
              <span class="error-message"><?php echo $errors['dob']; ?></span>
            <?php endif; ?>
          </div>
          
          <!-- Profile Picture -->
          <div class="form-group">
            <label for="profile_pic" class="form-label">Profile Picture</label>
            <div class="file-upload">
              <input type="file" id="profile_pic" name="profile_pic" class="file-upload-input" accept="image/*">
              <label for="profile_pic" class="file-upload-label">
                <i class="fas fa-upload mr-2"></i> Choose File
              </label>
              <span id="file-name" class="file-upload-name">No file chosen</span>
            </div>
            <?php if (isset($errors['profile_pic'])): ?>
              <span class="error-message"><?php echo $errors['profile_pic']; ?></span>
            <?php endif; ?>
            <div class="preview-container" id="preview-container">
              <?php if ($user['profile_pic']): ?>
                <img src="uploads/profiles/<?php echo $user['profile_pic']; ?>" id="preview-image" class="preview-image">
              <?php else: ?>
                <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['firstname'].' '.$user['lastname']); ?>&background=random" 
                     id="preview-image" class="preview-image">
              <?php endif; ?>
            </div>
          </div>
          
          <!-- Address -->
          <div class="form-group md:col-span-2">
            <label for="address" class="form-label">Address</label>
            <textarea id="address" name="address" rows="3" class="form-input"><?php 
              echo htmlspecialchars($_POST['address'] ?? $user['address']); 
            ?></textarea>
          </div>
          
          <!-- Password -->
          <div class="form-group">
            <label for="password" class="form-label">New Password (leave blank to keep current)</label>
            <input type="password" id="password" name="password" 
                   class="form-input <?php echo isset($errors['password']) ? 'form-input-error' : ''; ?>">
            <?php if (isset($errors['password'])): ?>
              <span class="error-message"><?php echo $errors['password']; ?></span>
            <?php endif; ?>
          </div>
          
          <!-- Confirm Password -->
          <div class="form-group">
            <label for="confirm_password" class="form-label">Confirm New Password</label>
            <input type="password" id="confirm_password" name="confirm_password" 
                   class="form-input <?php echo isset($errors['confirm_password']) ? 'form-input-error' : ''; ?>">
            <?php if (isset($errors['confirm_password'])): ?>
              <span class="error-message"><?php echo $errors['confirm_password']; ?></span>
            <?php endif; ?>
          </div>
        </div>
        
        <div class="mt-8 flex justify-end">
          <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md">
            <i class="fas fa-save mr-2"></i> Update User
          </button>
        </div>
      </form>
    </div>
  </div>
  
  <script>
    // File upload preview
    document.getElementById('profile_pic').addEventListener('change', function(e) {
      const file = e.target.files[0];
      const fileNameElement = document.getElementById('file-name');
      const previewContainer = document.getElementById('preview-container');
      const previewImage = document.getElementById('preview-image');
      
      if (file) {
        fileNameElement.textContent = file.name;
        
        const reader = new FileReader();
        reader.onload = function(e) {
          previewImage.src = e.target.result;
        }
        reader.readAsDataURL(file);
      } else {
        fileNameElement.textContent = 'No file chosen';
        <?php if ($user['profile_pic']): ?>
          previewImage.src = 'uploads/profiles/<?php echo $user['profile_pic']; ?>';
        <?php else: ?>
          previewImage.src = 'https://ui-avatars.com/api/?name=<?php echo urlencode($user['firstname'].' '.$user['lastname']); ?>&background=random';
        <?php endif; ?>
      }
    });
  </script>
</body>
</html>