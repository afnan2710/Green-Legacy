<?php
// Database connection
require_once 'db_connect.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['email'])) {
    $email = trim($_POST['email']);
    
    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        header('Location: index.php?subscription=1&status=error&message=Invalid email address');
        exit;
    }
    
    // Check if email already exists
    $stmt = $conn->prepare("SELECT id FROM newsletter_subscribers WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows > 0) {
        header('Location: index.php?subscription=1&status=error&message=You are already subscribed');
        exit;
    }
    
    // Insert new subscriber
    $stmt = $conn->prepare("INSERT INTO newsletter_subscribers (email, subscribed_at) VALUES (?, NOW())");
    $stmt->bind_param("s", $email);
    
    if ($stmt->execute()) {
        // Send welcome email with HTML design
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
            
            $mail->Subject = 'Subscription successful to Green Legacy Newsletter portal!';
            
            $mail->Body = '
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #2E8B57; padding: 20px; text-align: center; }
                    .header img { max-width: 150px; }
                    .content { padding: 20px; background-color: #f9f9f9; }
                    .footer { padding: 10px; text-align: center; font-size: 12px; color: #777; background-color: #eee; }
                    .button { display: inline-block; padding: 10px 20px; background-color: #2E8B57; color: white; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                    .social-icons { margin: 20px 0; }
                    .social-icons a { margin: 0 10px; color: #2E8B57; text-decoration: none; }
                </style>
            </head>
            <body>
                <div class="header">
                    <img src="lg-logo.png" alt="Green Legacy Logo">
                </div>
                <div class="content">
                    <h2>Welcome to Our Green Community!</h2>
                    <p>Hello Plant Lover,</p>
                    <p>Thank you for subscribing to the Green Legacy newsletter portal. You\'re now part of our growing community of plant enthusiasts!</p>
                    <p>Here\'s what you can expect:</p>
                    <ul>
                        <li>🌱 Monthly gardening tips and tricks</li>
                        <li>🌿 Exclusive offers on plants and tools</li>
                        <li>🌸 Seasonal planting guides</li>
                        <li>🌻 Early access to new arrivals</li>
                    </ul>
                    <p>Your first newsletter will arrive soon. In the meantime, why not check out our latest blog posts?</p>
                    <a href="https://greenlegacy.com/blogs" class="button">Visit Our Blog</a>
                    <div class="social-icons">
                        <p>Follow us for daily plant inspiration:</p>
                        <a href="https://facebook.com/greenlegacy">Facebook</a> | 
                        <a href="https://instagram.com/greenlegacy">Instagram</a> | 
                        <a href="https://pinterest.com/greenlegacy">Pinterest</a>
                    </div>
                </div>
                <div class="footer">
                    <p>&copy; '.date('Y').' Green Legacy. All rights reserved.</p>
                    <p><a href="https://greenlegacy.com/unsubscribe?email='.$email.'">Unsubscribe</a> if you no longer wish to receive our emails.</p>
                </div>
            </body>
            </html>
            ';
            
            // Plain text version for non-HTML email clients
            $mail->AltBody = "Welcome to Green Legacy!\n\nThank you for subscribing to our newsletter. You'll receive gardening tips, exclusive offers, and updates on new arrivals.\n\nVisit us at https://greenlegacy.com\n\nTo unsubscribe, reply to this email with 'UNSUBSCRIBE' in the subject line.";
            
            $mail->send();
            header('Location: index.php?subscription=1&status=success&message=Thank you for subscribing! A confirmation email has been sent to you.');
        } catch (Exception $e) {
            error_log('Mailer Error: ' . $mail->ErrorInfo);
            header('Location: index.php?subscription=1&status=success&message=Thank you for subscribing! (Confirmation email could not be sent)');
        }
    } else {
        header('Location: index.php?subscription=1&status=error&message=Subscription failed. Please try again.');
    }
    
    $stmt->close();
    exit;
}

header('Location: index.php');
?>