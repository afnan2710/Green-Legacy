<?php
session_start();
require_once 'db_connect.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$msg = '';
$success = false;

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = trim($_POST['email']);

    if (empty($email)) {
        $msg = "Please enter your email.";
    } else {
        // Check if user exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $res = $stmt->get_result();

        if ($res->num_rows == 1) {
            // Generate token and expiry
            $token = bin2hex(random_bytes(32));
            $expires_at = date("Y-m-d H:i:s", time() + 1800); // 30 min

            // Store token
            $stmt2 = $conn->prepare("INSERT INTO password_resets (email, token, expires_at) VALUES (?, ?, ?)");
            $stmt2->bind_param("sss", $email, $token, $expires_at);
            $stmt2->execute();

            // Send email using PHPMailer
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

                $reset_link = "http://localhost/greenlegacy/reset_password.php?token=$token";

                $mail->Subject = 'Green Legacy - Password Reset';
                $mail->Body = "
                    <p>Hello, User!</p>
                    <p>Click the link below to reset your password. This link is valid for 30 minutes only.</p>
                    <p><a href='$reset_link'>$reset_link</a></p>
                    <p></p>
                ";

                $mail->send();
                $msg = "A reset link has been sent to your email.";
                $success = true;
            } catch (Exception $e) {
                $msg = "Email could not be sent. Error: " . $mail->ErrorInfo;
            }
        } else {
            $msg = "If this email exists, a reset link will be sent.";
        }
        $stmt->close();
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Forgot Password - Green Legacy</title>

  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      background: linear-gradient(135deg, #a8e6cf, #dcedc1, #ffd3b6, #ffaaa5);
      background-size: 400% 400%;
      animation: gradientFlow 15s ease infinite;
    }

    @keyframes gradientFlow {
      0% { background-position: 0% 50%; }
      50% { background-position: 100% 50%; }
      100% { background-position: 0% 50%; }
    }

    .login-container {
      position: relative;
      z-index: 10;
      box-shadow: 0 0 30px rgba(46, 204, 113, 0.3);
    }
  </style>
</head>
<body>

  <div class="bg-white p-10 rounded-2xl shadow-2xl w-96 login-container transition-all duration-500">
    <img src="lg-logo.png" alt="Green Legacy Logo" class="mx-auto mb-6 w-24 h-auto">

    <h1 class="text-2xl font-bold text-center text-green-700 mb-2">Forgot Password?</h1>
    <p class="text-center text-gray-500 mb-6">We'll send you a reset link</p>

    <?php if (!empty($msg)): ?>
      <div class="mb-4 p-3 <?php echo $success ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?> rounded-lg text-sm">
        <?php echo htmlspecialchars($msg); ?>
      </div>
    <?php endif; ?>

    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>">
      <input
        type="email"
        name="email"
        placeholder="Enter your email"
        class="w-full px-4 py-3 mb-4 text-gray-700 placeholder-gray-400 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition-all duration-300"
        required
      />

      <button
        type="submit"
        class="w-full py-3 bg-green-500 hover:bg-green-600 hover:scale-105 transform text-white font-bold rounded-lg transition duration-300"
      >
        Send Reset Link
      </button>
    </form>

    <p class="mt-6 text-center text-gray-600">
      <a href="login.php" class="text-green-600 hover:text-green-800 font-semibold transition duration-200">← Back to Login</a>
    </p>
  </div>
</body>
</html>
