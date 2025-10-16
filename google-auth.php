<?php
session_start();
require_once 'db_connect.php';

// Google OAuth configuration
$clientID = '21830070433-8ntms8h3k10jtqamv9tumv769cp3bm2r.apps.googleusercontent.com';
$clientSecret = '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';
$redirectUri = 'http://localhost/greenlegacy/google-auth.php';

// Error reporting for development (remove in production)
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    if (isset($_GET['code'])) {
        // Exchange authorization code for access token
        $tokenUrl = 'https://oauth2.googleapis.com/token';
        $postData = [
            'code' => $_GET['code'],
            'client_id' => $clientID,
            'client_secret' => $clientSecret,
            'redirect_uri' => $redirectUri,
            'grant_type' => 'authorization_code'
        ];

        // Use cURL to get access token
        $ch = curl_init($tokenUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            throw new Exception('cURL error: ' . curl_error($ch));
        }

        curl_close($ch);

        $tokenData = json_decode($response, true);

        if (!isset($tokenData['access_token'])) {
            throw new Exception('Failed to get access token from Google');
        }

        // Get user info from Google
        $userInfoUrl = 'https://www.googleapis.com/oauth2/v2/userinfo?access_token=' . $tokenData['access_token'];
        $userInfoResponse = file_get_contents($userInfoUrl);

        if ($userInfoResponse === false) {
            throw new Exception('Failed to get user info from Google');
        }

        $userInfo = json_decode($userInfoResponse, true);

        if (!isset($userInfo['email'])) {
            throw new Exception('Google did not provide user email');
        }

        // Check if user already exists
        $stmt = $conn->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->bind_param("s", $userInfo['email']);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 0) {
            // User doesn't exist, create new account
            $nameParts = explode(' ', $userInfo['name'] ?? 'User');
            $firstname = $nameParts[0];
            $lastname = $nameParts[1] ?? '';

            // Generate a random password (won't be used for OAuth login)
            $password = bin2hex(random_bytes(16));
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $profilePic = $userInfo['picture'] ?? null;

            // Insert new user
            $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, email, password, is_verified, profile_pic, oauth_provider) VALUES (?, ?, ?, ?, 1, ?, 'google')");
            $stmt->bind_param("sssss", $firstname, $lastname, $userInfo['email'], $hashed_password, $profilePic);

            if (!$stmt->execute()) {
                throw new Exception('Failed to create user account: ' . $stmt->error);
            }

            $user_id = $stmt->insert_id;
            // Add 100 reward points for signing up
            $initial_points = 100;
            $stmt = $conn->prepare("UPDATE users SET reward_point = ? WHERE id = ?");
            $stmt->bind_param("ii", $initial_points, $user_id);
            $stmt->execute();
        } else {
            // User exists, get their data
            $user = $result->fetch_assoc();
            $user_id = $user['id'];
            $firstname = $user['firstname'];
            $lastname = $user['lastname'];
            $profilePic = $user['profile_pic'] ?? $userInfo['picture'] ?? null;

            // Update profile picture if not set but available from Google
            if (empty($user['profile_pic']) && !empty($userInfo['picture'])) {
                $updateStmt = $conn->prepare("UPDATE users SET profile_pic = ?, oauth_provider = 'google' WHERE id = ?");
                $updateStmt->bind_param("si", $userInfo['picture'], $user_id);
                $updateStmt->execute();
                $profilePic = $userInfo['picture'];
            }
        }

        // Set comprehensive session variables
        $_SESSION = [
            'logged_in' => true,
            'user_id' => $user_id,
            'user_email' => $userInfo['email'],
            'user_firstname' => $firstname,
            'user_lastname' => $lastname,
            'user_name' => $firstname . ' ' . $lastname,
            'profile_pic' => $profilePic,
            'oauth_provider' => 'google',
            'is_verified' => 1
        ];

        // Redirect to dashboard
        header('Location: index.php');
        exit;
    } elseif (isset($_GET['error'])) {
        // Handle OAuth errors
        throw new Exception('Google OAuth error: ' . htmlspecialchars($_GET['error']));
    } else {
        // Redirect to Google OAuth login
        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query([
            'client_id' => $clientID,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/userinfo.profile https://www.googleapis.com/auth/userinfo.email',
            'access_type' => 'online',
            'prompt' => 'select_account'
        ]);

        header('Location: ' . $authUrl);
        exit;
    }
} catch (Exception $e) {
    // Log the error
    error_log('Google OAuth Error: ' . $e->getMessage());

    // Store error in session and redirect to login page
    $_SESSION['oauth_error'] = 'Failed to authenticate with Google. Please try again or use email signup.';
    header('Location: login.php');
    exit;
}
