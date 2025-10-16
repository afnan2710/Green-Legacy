<?php
session_start();

// Database connection
require_once 'db_connect.php';

// Initialize variables
$error = '';
$login_success = false;

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Get and sanitize inputs
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);
    
    // Validate inputs
    if (empty($username) || empty($password)) {
        $error = "Please enter both username and password";
    } else {
        // Prepare SQL to prevent SQL injection - now including is_verified in the query
        $stmt = $conn->prepare("SELECT id, firstname, lastname, email, profile_pic, password, is_verified FROM users WHERE email = ? OR phone = ?");
        $stmt->bind_param("ss", $username, $username);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($result->num_rows == 1) {
            $user = $result->fetch_assoc();
            
            // Verify password
            if (password_verify($password, $user['password'])) {
                // Check if email is verified
                if (!$user['is_verified']) {
                    $error = "Your email is not verified. Please check your email for the verification link. <a href='resend_verification.php?email=" . urlencode($user['email']) . "' class='text-green-600 hover:underline'>Resend verification email</a>";
                } else {
                    // Set session variables
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['user_name'] = $user['firstname'] . ' ' . $user['lastname'];
                    $_SESSION['user_email'] = $user['email'];
                    $_SESSION['profile_pic'] = $user['profile_pic'];
                    $_SESSION['logged_in'] = true;
                    
                    // Redirect to index.php
                    header("Location: index.php");
                    exit();
                }
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }
        $stmt->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Green Legacy Login</title>

  <!-- Tailwind CSS CDN -->
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">

  <!-- Google Fonts: Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      position: relative;

      /* 🌈 Vibrant nature gradient */
      background: linear-gradient(135deg, #a8e6cf, #dcedc1, #ffd3b6, #ffaaa5);
      background-size: 400% 400%;
      animation: gradientFlow 15s ease infinite;
    }

    /* 🍃 Floating leaves animation */
    .leaf {
      position: absolute;
      background-size: contain;
      background-repeat: no-repeat;
      opacity: 0.7;
      z-index: 0;
      pointer-events: none;
    }

    /* ✨ Pulsing glow effect on container */
    .login-container {
      position: relative;
      z-index: 10;
      box-shadow: 0 0 30px rgba(46, 204, 113, 0.3);
    }

    /* Keyframes */
    @keyframes gradientFlow {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
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

    @keyframes floatLeaf {
      0% {
        transform: translateY(0) rotate(0deg);
      }
      100% {
        transform: translateY(-100vh) rotate(360deg);
      }
    }
  </style>
</head>
<body>
  <!-- Floating leaves (dynamically added via JS) -->
  <div id="leaves-container"></div>

  <div class="bg-white p-10 rounded-2xl shadow-2xl w-96 login-container transition-all duration-500">
    <!-- Logo -->
    <img src="lg-logo.png" alt="Green Legacy Logo" class="mx-auto mb-6 w-24 h-auto">

    <!-- Title -->
    <h1 class="text-3xl font-bold text-center text-green-700 mb-2">Welcome Back</h1>
    <p class="text-center text-gray-500 mb-6">Login to your account</p>

    <!-- Error message -->
    <?php if (!empty($error)): ?>
      <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
        <?php echo $error; ?>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
      <input
        type="text"
        name="username"
        placeholder="Email or Phone"
        class="w-full px-4 py-3 mb-4 text-gray-700 placeholder-gray-400 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition-all duration-300"
        required
      />

      <input
        type="password"
        name="password"
        placeholder="Password"
        class="w-full px-4 py-3 mb-2 text-gray-700 placeholder-gray-400 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition-all duration-300"
        required
      />

      <div class="text-right mb-4">
        <a href="forgot_password.php" class="text-sm text-green-500 hover:text-green-700 font-medium transition-colors duration-200">Forgot Password?</a>
      </div>

      <button
        type="submit"
        class="w-full py-3 bg-green-500 hover:bg-green-600 hover:scale-105 transform text-white font-bold rounded-lg transition duration-300"
      >
        Login
      </button>
    </form>

    <p class="mt-6 text-center text-gray-600">
      Don't have an account?
      <a href="signup.php" class="text-green-600 hover:text-green-800 font-semibold transition duration-200">Sign up</a>
    </p>
  </div>

  <!-- JavaScript for floating leaves -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const leavesContainer = document.getElementById('leaves-container');
      const leafTypes = ['🌱', '🌿', '🍃', '🍂', '🍁', '🌴', '🌾'];
      
      // Create 40 floating leaves (increased density)
      for (let i = 0; i < 40; i++) {
        const leaf = document.createElement('div');
        leaf.className = 'leaf';
        leaf.innerHTML = leafTypes[Math.floor(Math.random() * leafTypes.length)];
        
        // Random properties with larger sizes
        const size = Math.random() * 30 + 20; // Bigger emojis (20-50px)
        const left = Math.random() * 100;
        const delay = Math.random() * 5;
        const duration = Math.random() * 15 + 10; // Slower movement
        
        leaf.style.cssText = `
          font-size: ${size}px;
          left: ${left}%;
          top: 110%;
          animation: floatLeaf ${duration}s linear ${delay}s infinite;
          transform: rotate(${Math.random() * 360}deg);
          opacity: ${Math.random() * 0.5 + 0.3};
        `;
        
        leavesContainer.appendChild(leaf);
      }
    });
  </script>
</body>
</html>