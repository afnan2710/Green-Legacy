<?php
session_start();

// Database connection
require_once 'db_connect.php';

// Initialize variables
$error = '';

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize inputs
    $email = trim($_POST['email']);
    $password = trim($_POST['password']);
    
    // Validate inputs
    if (empty($email) || empty($password)) {
        $error = "Please enter both email and password";
    } else {
        // Prepare SQL to prevent SQL injection
        $stmt = $conn->prepare("SELECT admin_id, username, email, password, role FROM admins WHERE email = ? AND role = 'consultant'");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $consultant = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $consultant['password'])) {
                // Set session variables
                $_SESSION['consultant_id'] = $consultant['admin_id'];
                $_SESSION['consultant_username'] = $consultant['username'];
                $_SESSION['consultant_email'] = $consultant['email'];
                $_SESSION['consultant_role'] = $consultant['role'];
                $_SESSION['consultant_logged_in'] = true;
                
                // Redirect to consultant dashboard
                header("Location: consultant_dashboard.php");
                exit();
            } else {
                $error = "Invalid email or password";
            }
        } else {
            $error = "Invalid email or password";
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Consultant Login - Green Legacy</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #2E8B57, #1e5a3a);
    }
    
    .login-container {
      position: relative;
      z-index: 10;
      box-shadow: 0 0 30px rgba(0, 0, 0, 0.2);
      animation: slideIn 0.5s ease-out;
    }
    
    @keyframes slideIn {
      from {
        transform: translateY(50px);
        opacity: 0;
      }
      to {
        transform: translateY(0);
        opacity: 1;
      }
    }
  </style>
</head>
<body>
  <div class="bg-white p-10 rounded-2xl shadow-2xl w-96 login-container">
    <!-- Logo -->
    <img src="lg-logo.png" alt="Green Legacy Logo" class="mx-auto mb-6 w-24 h-auto">

    <!-- Title -->
    <h1 class="text-3xl font-bold text-center text-green-700 mb-2">Consultant Portal</h1>
    <p class="text-center text-gray-500 mb-6">Please sign in to continue</p>

    <!-- Error message -->
    <?php if (!empty($error)): ?>
      <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
      <div class="mb-4">
        <label for="email" class="block text-gray-700 text-sm font-medium mb-2">Email</label>
        <input
          type="email"
          id="email"
          name="email"
          placeholder="consultant@example.com"
          class="w-full px-4 py-3 text-gray-700 placeholder-gray-400 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400"
          required
        />
      </div>

      <div class="mb-4">
        <label for="password" class="block text-gray-700 text-sm font-medium mb-2">Password</label>
        <input
          type="password"
          id="password"
          name="password"
          placeholder="••••••••"
          class="w-full px-4 py-3 text-gray-700 placeholder-gray-400 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400"
          required
        />
      </div>

      <button
        type="submit"
        class="w-full py-3 bg-green-600 hover:bg-green-700 text-white font-bold rounded-lg transition duration-300"
      >
        Sign In
      </button>
    </form>
  </div>
</body>
</html>