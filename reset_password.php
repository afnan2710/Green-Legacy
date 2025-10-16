<?php
session_start();
require_once 'db_connect.php';

$token = $_GET['token'] ?? '';
$error = '';
$success = false;

// Form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['token'];
    $new_password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    if (empty($new_password) || empty($confirm_password)) {
        $error = "Please fill in both fields.";
    } elseif ($new_password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (strlen($new_password) < 6) {
        $error = "Password must be at least 6 characters.";
    } else {
        // Check token
        $stmt = $conn->prepare("SELECT email, expires_at FROM password_resets WHERE token = ?");
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $row = $result->fetch_assoc();
            $expires_at = strtotime($row['expires_at']);
            $now = time();

            if ($now > $expires_at) {
                $error = "This link has expired.";
            } else {
                // Token valid, update password
                $email = $row['email'];
                $hashed = password_hash($new_password, PASSWORD_DEFAULT);

                $update = $conn->prepare("UPDATE users SET password = ? WHERE email = ?");
                $update->bind_param("ss", $hashed, $email);
                $update->execute();

                // Delete the token
                $delete = $conn->prepare("DELETE FROM password_resets WHERE token = ?");
                $delete->bind_param("s", $token);
                $delete->execute();

                $success = true;
            }
        } else {
            $error = "Invalid or expired token.";
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Reset Password - Green Legacy</title>

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

    <h1 class="text-2xl font-bold text-center text-green-700 mb-2">Reset Password</h1>
    <p class="text-center text-gray-500 mb-6">Enter a new password</p>

    <?php if (!empty($error)): ?>
      <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
        <?php echo htmlspecialchars($error); ?>
      </div>
    <?php elseif ($success): ?>
      <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg text-sm">
        Password has been reset successfully. <a href="login.php" class="text-green-700 font-semibold">Login now →</a>
      </div>
    <?php endif; ?>

    <?php if (!$success): ?>
    <form method="POST">
      <input type="hidden" name="token" value="<?php echo htmlspecialchars($token); ?>" />

      <input
        type="password"
        name="password"
        placeholder="New password"
        class="w-full px-4 py-3 mb-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400"
        required
      />
      <input
        type="password"
        name="confirm_password"
        placeholder="Confirm password"
        class="w-full px-4 py-3 mb-4 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400"
        required
      />
      <button
        type="submit"
        class="w-full py-3 bg-green-500 hover:bg-green-600 hover:scale-105 transform text-white font-bold rounded-lg transition duration-300"
      >
        Reset Password
      </button>
    </form>
    <?php endif; ?>
  </div>
  <script>
  const passwordInput = document.querySelector('input[name="password"]');
  const confirmInput = document.querySelector('input[name="confirm_password"]');
  const form = document.querySelector('form');

  // Create strength meter
  const strengthMeter = document.createElement('div');
  strengthMeter.className = 'h-2 w-full rounded mb-4 bg-gray-200 overflow-hidden';
  const strengthBar = document.createElement('div');
  strengthBar.className = 'h-full transition-all duration-500';
  strengthMeter.appendChild(strengthBar);
  passwordInput.parentNode.insertBefore(strengthMeter, confirmInput);

  // Feedback text
  const feedback = document.createElement('p');
  feedback.className = 'text-sm font-medium mb-2';
  passwordInput.parentNode.insertBefore(feedback, confirmInput);

  passwordInput.addEventListener('input', () => {
    const val = passwordInput.value;
    let strength = 0;
    let color = 'bg-red-500';
    let text = 'Too Weak';

    if (val.length >= 6) strength++;
    if (/[a-z]/.test(val) && /[A-Z]/.test(val)) strength++;
    if (/\d/.test(val)) strength++;
    if (/[\W_]/.test(val)) strength++;

    switch (strength) {
      case 0:
      case 1:
        color = 'bg-red-500';
        text = 'Weak';
        break;
      case 2:
        color = 'bg-yellow-400';
        text = 'Moderate';
        break;
      case 3:
        color = 'bg-green-400';
        text = 'Strong';
        break;
      case 4:
        color = 'bg-green-600';
        text = 'Very Strong';
        break;
    }

    const percent = (strength / 4) * 100;
    strengthBar.style.width = `${percent}%`;
    strengthBar.className = `h-full ${color} transition-all duration-500`;
    feedback.textContent = `Strength: ${text}`;
    feedback.className = `text-sm font-medium mb-2 ${color.includes('red') ? 'text-red-500' : color.includes('yellow') ? 'text-yellow-500' : 'text-green-600'}`;
  });
</script>

</body>
</html>
