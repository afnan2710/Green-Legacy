<?php
// Database connection
require_once 'db_connect.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Initialize variables
$errors = [];
$success = false;

// Allowed email domains (add your allowed domains here)
$allowed_domains = ['gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'example.com'];

// Process form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
  // Validate and sanitize inputs
  $firstname = htmlspecialchars(trim($_POST['firstname']));
  $lastname = htmlspecialchars(trim($_POST['lastname']));
  $dob = $_POST['dob'];
  $address = htmlspecialchars(trim($_POST['address']));
  $email = filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL);
  $phone_code = $_POST['phone_code'];
  $phone = preg_replace('/[^0-9]/', '', $_POST['phone']);
  $password = $_POST['password'];
  $confirm_password = $_POST['confirm_password'];

  // Validate firstname and lastname
  if (empty($firstname)) {
    $errors['firstname'] = "First name is required";
  }
  if (empty($lastname)) {
    $errors['lastname'] = "Last name is required";
  }

  // Validate email
  if (empty($email)) {
    $errors['email'] = "Email is required";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = "Invalid email format";
  } else {
    // Check email domain
    $email_parts = explode('@', $email);
    $domain = strtolower(array_pop($email_parts));
    if (!in_array($domain, $allowed_domains)) {
      $errors['email'] = "We don't accept emails from this domain. Please use a different email provider.";
    } else {
      // Check if email already exists
      $stmt = $conn->prepare("SELECT id FROM users WHERE email = ?");
      $stmt->bind_param("s", $email);
      $stmt->execute();
      $stmt->store_result();
      if ($stmt->num_rows > 0) {
        $errors['email'] = "Email already exists";
      }
      $stmt->close();
    }
  }

  // Validate phone
  if (empty($phone)) {
    $errors['phone'] = "Phone number is required";
  } elseif (strlen($phone) < 8) {
    $errors['phone'] = "Phone number is too short";
  }

  // Validate password
  if (empty($password)) {
    $errors['password'] = "Password is required";
  } elseif (strlen($password) < 8) {
    $errors['password'] = "Password must be at least 8 characters";
  } elseif (!preg_match('/[A-Z]/', $password)) {
    $errors['password'] = "Password must contain at least one uppercase letter";
  } elseif (!preg_match('/[a-z]/', $password)) {
    $errors['password'] = "Password must contain at least one lowercase letter";
  } elseif (!preg_match('/[0-9]/', $password)) {
    $errors['password'] = "Password must contain at least one number";
  } elseif (!preg_match('/[^A-Za-z0-9]/', $password)) {
    $errors['password'] = "Password must contain at least one special character";
  } elseif ($password !== $confirm_password) {
    $errors['confirm_password'] = "Passwords do not match";
  }

  // Handle file upload
  $profile_pic = null;
  if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == UPLOAD_ERR_OK) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
    $file_type = $_FILES['profile_pic']['type'];

    if (in_array($file_type, $allowed_types)) {
      $upload_dir = 'uploads/profiles/';
      if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
      }

      $file_ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
      $filename = uniqid() . '.' . $file_ext;
      $destination = $upload_dir . $filename;

      if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $destination)) {
        $profile_pic = $destination;
      } else {
        $errors['profile_pic'] = "Failed to upload profile picture";
      }
    } else {
      $errors['profile_pic'] = "Only JPG, PNG, and GIF files are allowed";
    }
  }

  // If no errors, insert into database
  if (empty($errors)) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $full_phone = $phone_code . $phone;
    $verification_token = bin2hex(random_bytes(32));
    $verification_token_expires = date("Y-m-d H:i:s", time() + 3600); // 1 hour expiration

    $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, dob, address, email, phone, password, profile_pic, verification_token, verification_token_expires) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssssssss", $firstname, $lastname, $dob, $address, $email, $full_phone, $hashed_password, $profile_pic, $verification_token, $verification_token_expires);

    if ($stmt->execute()) {
      $user_id = $stmt->insert_id;

      // Add 100 reward points for signing up
      $initial_points = 100;
      $stmt = $conn->prepare("UPDATE users SET reward_point = ? WHERE id = ?");
      $stmt->bind_param("ii", $initial_points, $user_id);
      $stmt->execute();
      $stmt->close();

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

        $verification_link = "http://greenlegacy/verify_email.php?token=$verification_token";

        $mail->Subject = 'Green Legacy - Email Verification';
        $mail->Body = "
                    <h2>Welcome to Green Legacy!</h2>
                    <p>Thank you for registering. Please verify your email address by clicking the link below:</p>
                    <p><a href='$verification_link'>Verify Email Address</a></p>
                    <p>This link will expire in 1 hour.</p>
                    <p>If you didn't create an account with us, please ignore this email.</p>
                ";

        $mail->send();
        $success = true;
        // Clear form fields
        $_POST = array();
      } catch (Exception $e) {
        $errors['email'] = "Verification email could not be sent. Please contact support.";
      }
    } else {
      $errors['database'] = "Error: " . $stmt->error;
    }
  }
}

// Country codes for phone input
$country_codes = [
  '+91' => 'India (+91)',
  '+49' => 'Germany (+49)',
  '+61' => 'Australia (+61)',
  '+33' => 'France (+33)',
  '+880' => 'Bangladesh (+880)',
  '+1' => 'USA (+1)',
  '+44' => 'UK (+44)',
  '+94' => 'Sri Lanka (+94)',
  '+60' => 'Malaysia (+60)',
  '+65' => 'Singapore (+65)',
  '+86' => 'China (+86)',
  '+81' => 'Japan (+81)',
  '+82' => 'South Korea (+82)',
  '+39' => 'Italy (+39)',
  '+34' => 'Spain (+34)',
  '+55' => 'Brazil (+55)',
  '+7' => 'Russia (+7)',
  '+34' => 'Spain (+34)',
  '+90' => 'Turkey (+90)',
  '+62' => 'Indonesia (+62)',
  '+351' => 'Portugal (+351)',
  '+30' => 'Greece (+30)',
  '+420' => 'Czech Republic (+420)',
  '+48' => 'Poland (+48)',
];
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Green Legacy - Sign Up</title>

  <!-- Tailwind CSS CDN -->
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">

  <!-- Google Fonts: Poppins -->
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">

  <style>
    body {
      font-family: 'Poppins', sans-serif;
      min-height: 100vh;
      position: relative;
      overflow-x: hidden;
      padding: 20px 0;

      /* 🌈 Vibrant nature gradient - fixed to viewport */
      background: linear-gradient(135deg, #a8e6cf, #dcedc1, #ffd3b6, #ffaaa5);
      background-attachment: fixed;
      background-size: 400% 400%;
      animation: gradientFlow 15s ease infinite;
    }

    /* 🍃 Floating leaves animation - fixed to viewport */
    .leaf {
      position: fixed;
      z-index: 0;
      pointer-events: none;
      will-change: transform;
      backface-visibility: hidden;
    }

    /* ✨ Pulsing glow effect on container */
    .signup-container {
      animation: slideIn 1s ease forwards, pulseGlow 4s ease-in-out infinite alternate;
      position: relative;
      z-index: 10;
      box-shadow: 0 0 30px rgba(46, 204, 113, 0.3);
      margin: 20px auto;
    }

    /* Keyframes */
    @keyframes gradientFlow {
      0% {
        background-position: 0% 50%;
      }

      50% {
        background-position: 100% 50%;
      }

      100% {
        background-position: 0% 50%;
      }
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
        transform: translate(0, 100vh) rotate(0deg);
      }

      100% {
        transform: translate(calc(var(--random-x) * 1px), -100px) rotate(360deg);
      }
    }

    @keyframes pulseGlow {
      0% {
        box-shadow: 0 0 20px rgba(46, 204, 113, 0.3);
      }

      100% {
        box-shadow: 0 0 40px rgba(46, 204, 113, 0.6);
      }
    }

    /* Password strength meter */
    .password-strength {
      height: 5px;
      background: #e0e0e0;
      margin-top: 5px;
      border-radius: 3px;
      overflow: hidden;
    }

    .password-strength-bar {
      height: 100%;
      width: 0%;
      transition: width 0.3s, background 0.3s;
    }

    /* Two column layout */
    .form-columns {
      display: grid;
      grid-template-columns: 1fr 1fr;
      gap: 2rem;
    }

    @media (max-width: 768px) {
      .form-columns {
        grid-template-columns: 1fr;
      }
    }
  </style>
</head>

<body>
  <!-- Floating leaves (dynamically added via JS) -->
  <div id="leaves-container"></div>

  <div class="bg-white p-10 rounded-2xl shadow-2xl w-full max-w-4xl signup-container transition-all duration-500 my-10 mx-auto">
    <!-- Logo -->
    <img src="lg-logo.png" alt="Green Legacy Logo" class="mx-auto mb-6 w-24 h-auto">

    <!-- Title -->
    <h1 class="text-3xl font-bold text-center text-green-700 mb-2">Create Account</h1>
    <p class="text-center text-gray-500 mb-6">Join Green Legacy today</p>

    <?php if ($success): ?>
      <div class="mb-4 p-4 bg-green-100 text-green-700 rounded-lg">
        Registration successful! You can now <a href="login.php" class="font-semibold underline">login</a>.
      </div>
    <?php elseif (isset($errors['database'])): ?>
      <div class="mb-4 p-4 bg-red-100 text-red-700 rounded-lg">
        <?php echo htmlspecialchars($errors['database']); ?>
      </div>
    <?php endif; ?>

    <!-- Form -->
    <form method="POST" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" enctype="multipart/form-data" novalidate>
      <div class="form-columns">
        <!-- Left Column -->
        <div>
          <!-- Name Row -->
          <div class="flex gap-4 mb-4">
            <div class="flex-1">
              <label for="firstname" class="block text-sm font-medium text-gray-700 mb-1">First Name*</label>
              <input
                type="text"
                id="firstname"
                name="firstname"
                value="<?php echo isset($_POST['firstname']) ? htmlspecialchars($_POST['firstname']) : ''; ?>"
                class="w-full px-4 py-3 text-gray-700 placeholder-gray-400 border <?php echo isset($errors['firstname']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition-all duration-300"
                placeholder="John"
                required>
              <?php if (isset($errors['firstname'])): ?>
                <p class="mt-1 text-sm text-red-500"><?php echo $errors['firstname']; ?></p>
              <?php endif; ?>
            </div>
            <div class="flex-1">
              <label for="lastname" class="block text-sm font-medium text-gray-700 mb-1">Last Name*</label>
              <input
                type="text"
                id="lastname"
                name="lastname"
                value="<?php echo isset($_POST['lastname']) ? htmlspecialchars($_POST['lastname']) : ''; ?>"
                class="w-full px-4 py-3 text-gray-700 placeholder-gray-400 border <?php echo isset($errors['lastname']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition-all duration-300"
                placeholder="Doe"
                required>
              <?php if (isset($errors['lastname'])): ?>
                <p class="mt-1 text-sm text-red-500"><?php echo $errors['lastname']; ?></p>
              <?php endif; ?>
            </div>
          </div>

          <!-- DOB -->
          <div class="mb-4">
            <label for="dob" class="block text-sm font-medium text-gray-700 mb-1">Date of Birth</label>
            <input
              type="date"
              id="dob"
              name="dob"
              value="<?php echo isset($_POST['dob']) ? htmlspecialchars($_POST['dob']) : ''; ?>"
              class="w-full px-4 py-3 text-gray-700 placeholder-gray-400 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition-all duration-300"
              required>
          </div>

          <!-- Address -->
          <div class="mb-4">
            <label for="address" class="block text-sm font-medium text-gray-700 mb-1">Address</label>
            <textarea
              id="address"
              name="address"
              rows="3"
              class="w-full px-4 py-3 text-gray-700 placeholder-gray-400 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition-all duration-300"
              placeholder="Your full address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
          </div>

          <!-- Profile Picture -->
          <div class="mb-4">
            <label for="profile_pic" class="block text-sm font-medium text-gray-700 mb-1">Profile Picture (Optional)</label>
            <div class="flex items-center gap-4">
              <input
                type="file"
                id="profile_pic"
                name="profile_pic"
                accept="image/*"
                class="w-full px-4 py-2 text-gray-700 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition-all duration-300">
            </div>
            <?php if (isset($errors['profile_pic'])): ?>
              <p class="mt-1 text-sm text-red-500"><?php echo $errors['profile_pic']; ?></p>
            <?php endif; ?>
          </div>
        </div>

        <!-- Right Column -->
        <div>
          <!-- Email -->
          <div class="mb-4">
            <label for="email" class="block text-sm font-medium text-gray-700 mb-1">Email*</label>
            <input
              type="email"
              id="email"
              name="email"
              value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>"
              class="w-full px-4 py-3 text-gray-700 placeholder-gray-400 border <?php echo isset($errors['email']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition-all duration-300"
              placeholder="your@email.com"
              required>
            <?php if (isset($errors['email'])): ?>
              <p class="mt-1 text-sm text-red-500"><?php echo $errors['email']; ?></p>
            <?php endif; ?>
          </div>

          <!-- Phone Number -->
          <div class="mb-4">
            <label for="phone" class="block text-sm font-medium text-gray-700 mb-1">Phone Number*</label>
            <div class="flex gap-2">
              <select
                name="phone_code"
                class="w-1/4 px-3 py-3 text-gray-700 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition-all duration-300">
                <?php foreach ($country_codes as $code => $label): ?>
                  <option value="<?php echo $code; ?>" <?php echo (isset($_POST['phone_code']) && $_POST['phone_code'] == $code) ? 'selected' : ''; ?>>
                    <?php echo $label; ?>
                  </option>
                <?php endforeach; ?>
              </select>
              <input
                type="tel"
                id="phone"
                name="phone"
                value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>"
                class="flex-1 px-4 py-3 text-gray-700 placeholder-gray-400 border <?php echo isset($errors['phone']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition-all duration-300"
                placeholder="123456789"
                required>
            </div>
            <?php if (isset($errors['phone'])): ?>
              <p class="mt-1 text-sm text-red-500"><?php echo $errors['phone']; ?></p>
            <?php endif; ?>
          </div>

          <!-- Password -->
          <div class="mb-4">
            <label for="password" class="block text-sm font-medium text-gray-700 mb-1">Password*</label>
            <input
              type="password"
              id="password"
              name="password"
              class="w-full px-4 py-3 text-gray-700 placeholder-gray-400 border <?php echo isset($errors['password']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition-all duration-300"
              placeholder="••••••••"
              required
              oninput="checkPasswordStrength(this.value)">
            <div class="password-strength">
              <div id="password-strength-bar" class="password-strength-bar"></div>
            </div>
            <?php if (isset($errors['password'])): ?>
              <p class="mt-1 text-sm text-red-500"><?php echo $errors['password']; ?></p>
            <?php else: ?>
              <p class="mt-1 text-xs text-gray-500">Must be 8+ chars with uppercase, lowercase, number & special char</p>
            <?php endif; ?>
          </div>

          <!-- Confirm Password -->
          <div class="mb-6">
            <label for="confirm_password" class="block text-sm font-medium text-gray-700 mb-1">Confirm Password*</label>
            <input
              type="password"
              id="confirm_password"
              name="confirm_password"
              class="w-full px-4 py-3 text-gray-700 placeholder-gray-400 border <?php echo isset($errors['confirm_password']) ? 'border-red-500' : 'border-gray-300'; ?> rounded-lg focus:outline-none focus:ring-2 focus:ring-green-400 transition-all duration-300"
              placeholder="••••••••"
              required
              oninput="checkPasswordMatch()">
            <p id="password-match-feedback" class="mt-1 text-xs <?php echo isset($errors['confirm_password']) ? 'text-red-500' : 'text-gray-500'; ?>">
              <?php echo isset($errors['confirm_password']) ? $errors['confirm_password'] : 'Passwords must match'; ?>
            </p>
          </div>
        </div>
      </div>

      <button
        type="submit"
        class="w-full py-3 bg-green-500 hover:bg-green-600 hover:scale-105 transform text-white font-bold rounded-lg transition duration-300 mt-6">
        Sign Up

      </button>
      <div class="flex items-center my-4">
        <div class="flex-1 border-t border-gray-300"></div>
        <div class="px-4 text-gray-500">OR</div>
        <div class="flex-1 border-t border-gray-300"></div>
      </div>

      <a href="google-auth.php" class="w-full flex items-center justify-center gap-2 py-3 px-4 border border-gray-200 hover:border-gray-300 hover:scale-105 transform font-bold rounded-lg transition duration-300 mb-4 shadow-sm"
        style="background-color: #4285F4; color: white;">
        <div class="bg-white p-1 rounded-full">
          <img src="google-login.png"
            alt="Google logo"
            class="w-5 h-5">
        </div>
        <span>Sign up with Google</span>
      </a>
    </form>

    <p class="mt-6 text-center text-gray-600">
      Already have an account?
      <a href="login.php" class="text-green-600 hover:text-green-800 font-semibold transition duration-200">Log in</a>
    </p>
  </div>

  <!-- JavaScript for floating leaves -->
  <script>
    document.addEventListener('DOMContentLoaded', function() {
      const leavesContainer = document.getElementById('leaves-container');
      const leafTypes = ['🌱', '🌿', '🍃', '🍂', '🍁', '🌴', '🌾'];

      // Create floating leaves that work with scrolling
      function createLeaves() {
        const viewportWidth = window.innerWidth;
        const viewportHeight = window.innerHeight;
        const leavesCount = Math.min(Math.floor(viewportWidth / 15), 60); // Limit to 60 leaves max

        // Clear existing leaves
        leavesContainer.innerHTML = '';

        // Create new leaves
        for (let i = 0; i < leavesCount; i++) {
          const leaf = document.createElement('div');
          leaf.className = 'leaf';
          leaf.innerHTML = leafTypes[Math.floor(Math.random() * leafTypes.length)];

          // Random properties with CSS variables for better performance
          const size = Math.random() * 30 + 20;
          const startX = Math.random() * viewportWidth;
          const randomX = (Math.random() - 0.5) * 200; // Some horizontal movement
          const delay = Math.random() * 5;
          const duration = Math.random() * 15 + 10;
          const startY = viewportHeight + Math.random() * 500;
          const opacity = Math.random() * 0.5 + 0.3;

          leaf.style.cssText = `
            font-size: ${size}px;
            left: ${startX}px;
            top: 0;
            opacity: ${opacity};
            --random-x: ${randomX};
            animation: floatLeaf ${duration}s linear ${delay}s infinite;
            transform: translate(0, ${startY}px) rotate(${Math.random() * 360}deg);
          `;

          leavesContainer.appendChild(leaf);
        }
      }

      // Initial creation
      createLeaves();

      // Throttled resize handler
      let resizeTimeout;
      window.addEventListener('resize', function() {
        clearTimeout(resizeTimeout);
        resizeTimeout = setTimeout(createLeaves, 200);
      });
    });

    // Password strength checker
    function checkPasswordStrength(password) {
      const strengthBar = document.getElementById('password-strength-bar');
      let strength = 0;

      // Length
      if (password.length >= 8) strength += 20;
      if (password.length >= 12) strength += 20;

      // Contains uppercase
      if (/[A-Z]/.test(password)) strength += 20;

      // Contains lowercase
      if (/[a-z]/.test(password)) strength += 20;

      // Contains number
      if (/[0-9]/.test(password)) strength += 10;

      // Contains special char
      if (/[^A-Za-z0-9]/.test(password)) strength += 10;

      // Update bar
      strengthBar.style.width = strength + '%';

      // Update color
      if (strength < 40) {
        strengthBar.style.backgroundColor = '#ef4444'; // red
      } else if (strength < 70) {
        strengthBar.style.backgroundColor = '#f59e0b'; // amber
      } else {
        strengthBar.style.backgroundColor = '#10b981'; // emerald
      }
    }

    // Password match checker
    function checkPasswordMatch() {
      const password = document.getElementById('password').value;
      const confirmPassword = document.getElementById('confirm_password').value;
      const feedback = document.getElementById('password-match-feedback');

      if (confirmPassword.length === 0) {
        feedback.textContent = 'Passwords must match';
        feedback.className = 'mt-1 text-xs text-gray-500';
      } else if (password !== confirmPassword) {
        feedback.textContent = 'Passwords do not match';
        feedback.className = 'mt-1 text-xs text-red-500';
      } else {
        feedback.textContent = 'Passwords match!';
        feedback.className = 'mt-1 text-xs text-green-500';
      }
    }
  </script>
</body>

</html>

<?php
// Close database connection
$conn->close();
?>