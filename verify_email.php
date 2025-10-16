<?php
require_once 'db_connect.php';

$message = '';
$is_error = false;

if (isset($_GET['token'])) {
    $token = $_GET['token'];
    
    // Check if token exists and is not expired
    $stmt = $conn->prepare("SELECT id, email, verification_token_expires FROM users WHERE verification_token = ?");
    $stmt->bind_param("s", $token);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        
        // Check if token is expired
        $now = new DateTime();
        $expires = new DateTime($user['verification_token_expires']);
        
        if ($now < $expires) {
            // Verify the user
            $update_stmt = $conn->prepare("UPDATE users SET is_verified = 1, verification_token = NULL, verification_token_expires = NULL WHERE id = ?");
            $update_stmt->bind_param("i", $user['id']);
            
            if ($update_stmt->execute()) {
                $message = "Your email has been verified successfully! You can now login.";
            } else {
                $message = "Error updating verification status. Please contact support.";
                $is_error = true;
            }
        } else {
            $message = "Verification link has expired. <a href='resend_verification.php?email=" . urlencode($user['email']) . "' class='text-green-600 hover:underline'>Click here to get a new one</a>.";
            $is_error = true;
        }
    } else {
        $message = "Invalid verification link. Please make sure you're using the latest link sent to your email.";
        $is_error = true;
    }
} else {
    $message = "No verification token provided.";
    $is_error = true;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Email Verification - Green Legacy</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
  <style>
    body {
      font-family: 'Poppins', sans-serif;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      background: linear-gradient(135deg, #a8e6cf, #dcedc1, #ffd3b6, #ffaaa5);
      background-size: 400% 400%;
      animation: gradientFlow 15s ease infinite;
    }
    @keyframes gradientFlow {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }
    .verification-container {
      background: white;
      padding: 2rem;
      border-radius: 1rem;
      box-shadow: 0 0 30px rgba(46, 204, 113, 0.3);
      max-width: 500px;
      width: 90%;
    }
  </style>
</head>
<body>
  <div class="verification-container text-center">
    <img src="lg-logo.png" alt="Green Legacy Logo" class="mx-auto mb-6 w-24 h-auto">
    <h1 class="text-2xl font-bold text-green-700 mb-4">Email Verification</h1>
    <div class="mb-4 p-3 <?php echo $is_error ? 'bg-red-100 text-red-700' : 'bg-green-100 text-green-700'; ?> rounded-lg">
      <?php echo $message; ?>
    </div>
    <a href="login.php" class="inline-block px-6 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition">Back to Login</a>
  </div>
</body>
</html>