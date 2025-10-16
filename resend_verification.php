<?php
require_once 'db_connect.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$message = '';
$is_error = false;

if (isset($_GET['email'])) {
    $email = $_GET['email'];
    
    // Check if user exists
    $stmt = $conn->prepare("SELECT id, firstname FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $verification_token = bin2hex(random_bytes(32));
        $verification_token_expires = date("Y-m-d H:i:s", time() + 3600); // 1 hour expiration
        
        // Update user with new token
        $update_stmt = $conn->prepare("UPDATE users SET verification_token = ?, verification_token_expires = ? WHERE id = ?");
        $update_stmt->bind_param("ssi", $verification_token, $verification_token_expires, $user['id']);
        
        if ($update_stmt->execute()) {
            // Send verification email
            $mail = new PHPMailer(true);
            
            try {
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

                $verification_link = "http://" . $_SERVER['HTTP_HOST'] . "/greenlegacy/verify_email.php?token=$verification_token";

                $mail->Subject = 'Green Legacy - Email Verification';
                $mail->Body = "
                    <h2>Hello {$user['firstname']}!</h2>
                    <p>We've received a request to resend your email verification link.</p>
                    <p>Please verify your email address by clicking the link below:</p>
                    <p><a href='$verification_link'>Verify Email Address</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                ";

                $mail->send();
                $message = "A new verification link has been sent to your email. Please check your inbox (and spam folder).";
            } catch (Exception $e) {
                $message = "Verification email could not be sent. Error: " . $e->getMessage();
                $is_error = true;
            }
        } else {
            $message = "Error generating new verification link. Please try again later.";
            $is_error = true;
        }
    } else {
        $message = "No account found with this email address.";
        $is_error = true;
    }
} else {
    $message = "No email provided.";
    $is_error = true;
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Resend Verification - Green Legacy</title>
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
    <h1 class="text-2xl font-bold text-green-700 mb-4">Resend Verification</h1>
    <div class="mb-4 p-3 <?php echo $success ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?> rounded-lg">
      <?php echo $message; ?>
    </div>
    <a href="login.php" class="inline-block px-6 py-2 bg-green-500 text-white rounded-lg hover:bg-green-600 transition">Back to Login</a>
  </div>
</body>
</html>