<?php
session_start();
require_once 'db_connect.php';

// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

// Manually include PHPMailer files
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Get application ID from URL
$application_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch application details
$application = [];
$job = [];
$stmt = $conn->prepare("SELECT ja.*, j.title AS job_title 
                       FROM job_applications ja
                       JOIN jobs j ON ja.job_id = j.id
                       WHERE ja.id = ?");
$stmt->bind_param("i", $application_id);
$stmt->execute();
$result = $stmt->get_result();
$application = $result->fetch_assoc();
$stmt->close();

if (!$application) {
    $_SESSION['message'] = "Application not found";
    $_SESSION['message_type'] = "error";
    header("Location: admin_jobs.php");
    exit;
}

// Update status if form submitted
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['status'])) {
    $new_status = $_POST['status'];
    $old_status = $application['status'];
    
    // Only proceed if status actually changed
    if ($new_status !== $old_status) {
        // Update status in database
        $stmt = $conn->prepare("UPDATE job_applications SET status = ? WHERE id = ?");
        $stmt->bind_param("si", $new_status, $application_id);
        $stmt->execute();
        $stmt->close();
        
        // Update application status in the local variable
        $application['status'] = $new_status;
        
        // Send email notification if status changed to something other than Submitted
        if ($new_status !== 'Submitted') {
            $mail = new PHPMailer(true);
            
            try {
                // Server settings
                $mail->isSMTP();
                $mail->Host       = 'smtp.gmail.com';
                $mail->SMTPAuth   = true;
                $mail->Username  = 'info.afnan27@gmail.com';
                $mail->Password   = 'rokp jusi apxx wjkn';
                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                $mail->Port       = 465;
                
                // Recipients
                $mail->setFrom('info.afnan27@gmail.com', 'Green Legacy Hiring Team');
                $mail->addAddress($application['applicant_email'], $application['applicant_name']);
                
                // Content
                $mail->isHTML(true);
                $mail->Subject = 'Update on Your Application for ' . $application['job_title'];
                
                // Email templates for different statuses
                $email_templates = [
                    'Under Review' => "
                        <p>Dear {$application['applicant_name']},</p>
                        <p>We're writing to inform you that your application for the position of <strong>{$application['job_title']}</strong> is now under review.</p>
                        <p>Our hiring team is currently evaluating all applications, and we appreciate your patience during this process.</p>
                        <p>If your qualifications match our requirements, we'll contact you for the next steps in the hiring process.</p>
                        <p>Best regards,<br>Green Legacy Hiring Team</p>
                    ",
                    'Interview' => "
                        <p>Dear {$application['applicant_name']},</p>
                        <p>Congratulations! We're pleased to inform you that you've been selected for an interview for the position of <strong>{$application['job_title']}</strong>.</p>
                        <p>A member of our hiring team will contact you shortly to schedule the interview and provide further details.</p>
                        <p>Please prepare any relevant documents or materials that might be needed for the interview.</p>
                        <p>We look forward to meeting you!</p>
                        <p>Best regards,<br>Green Legacy Hiring Team</p>
                    ",
                    'Rejected' => "
                        <p>Dear {$application['applicant_name']},</p>
                        <p>Thank you for applying for the position of <strong>{$application['job_title']}</strong> with Green Legacy.</p>
                        <p>After careful consideration, we regret to inform you that we've decided to move forward with other candidates whose qualifications more closely match our current needs.</p>
                        <p>We appreciate the time and effort you invested in your application and encourage you to apply for future positions that match your skills and experience.</p>
                        <p>Best regards,<br>Green Legacy Hiring Team</p>
                    ",
                    'Hired' => "
                        <p>Dear {$application['applicant_name']},</p>
                        <p>We're thrilled to inform you that you've been selected for the position of <strong>{$application['job_title']}</strong> at Green Legacy!</p>
                        <p>Congratulations on this exciting new opportunity. Our HR team will contact you shortly with the official offer letter and details about your onboarding process.</p>
                        <p>We're looking forward to having you join our team and contribute to our mission.</p>
                        <p>Welcome aboard!</p>
                        <p>Best regards,<br>Green Legacy Hiring Team</p>
                    "
                ];
                
                $mail->Body = $email_templates[$new_status];
                $mail->AltBody = strip_tags($email_templates[$new_status]);
                
                $mail->send();
                
                $_SESSION['message'] = "Application status updated successfully and notification sent";
            } catch (Exception $e) {
                $_SESSION['message'] = "Status updated but email could not be sent. Error: {$mail->ErrorInfo}";
                $_SESSION['message_type'] = "error";
            }
        } else {
            $_SESSION['message'] = "Application status updated successfully";
        }
        
        $_SESSION['message_type'] = "success";
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Application Details - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
        .application-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }
        .Submitted {
            background-color: #e0f2fe;
            color: #0369a1;
        }
        .Under-Review {
            background-color: #fef3c7;
            color: #92400e;
        }
        .Interview {
            background-color: #ede9fe;
            color: #5b21b6;
        }
        .Rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }
        .Hired {
            background-color: #d1fae5;
            color: #065f46;
        }
    </style>
</head>
<body class="bg-gray-100 ml-72">
    <?php include 'admin_sidebar.php'; ?>
    
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Application Details</h1>
            <a href="admin_job_details.php?id=<?php echo $application['job_id']; ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Job
            </a>
        </div>

        <?php if (isset($_SESSION['message'])): ?>
            <div class="mb-4 p-4 <?php echo $_SESSION['message_type'] === 'success' ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700'; ?> rounded">
                <?php echo $_SESSION['message']; ?>
                <?php unset($_SESSION['message']); unset($_SESSION['message_type']); ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-center">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($application['applicant_name']); ?></h2>
                        <p class="text-gray-600">Applied for: <?php echo htmlspecialchars($application['job_title']); ?></p>
                    </div>
                    <span class="application-status <?php echo str_replace(' ', '-', $application['status']); ?>">
                        <?php echo htmlspecialchars($application['status']); ?>
                    </span>
                </div>
            </div>

            <div class="p-6 grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Applicant Information</h3>
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Full Name</label>
                            <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($application['applicant_name']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Email</label>
                            <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($application['applicant_email']); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Phone</label>
                            <p class="mt-1 text-sm text-gray-900"><?php echo htmlspecialchars($application['applicant_phone'] ?? 'N/A'); ?></p>
                        </div>
                        <div>
                            <label class="block text-sm font-medium text-gray-500">Applied On</label>
                            <p class="mt-1 text-sm text-gray-900"><?php echo date('M d, Y H:i', strtotime($application['applied_at'])); ?></p>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Application Details</h3>
                    <form method="POST" class="space-y-4">
                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 mb-1">Update Status</label>
                            <select id="status" name="status" class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:ring-2 focus:ring-green-500 focus:border-green-500">
                                <option value="Submitted" <?php echo $application['status'] === 'Submitted' ? 'selected' : ''; ?>>Submitted</option>
                                <option value="Under Review" <?php echo $application['status'] === 'Under Review' ? 'selected' : ''; ?>>Under Review</option>
                                <option value="Interview" <?php echo $application['status'] === 'Interview' ? 'selected' : ''; ?>>Interview</option>
                                <option value="Rejected" <?php echo $application['status'] === 'Rejected' ? 'selected' : ''; ?>>Rejected</option>
                                <option value="Hired" <?php echo $application['status'] === 'Hired' ? 'selected' : ''; ?>>Hired</option>
                            </select>
                        </div>
                        <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md">
                            Update Status
                        </button>
                    </form>

                    <?php if ($application['resume_path']): ?>
                        <div class="mt-6">
                            <label class="block text-sm font-medium text-gray-700 mb-2">Resume</label>
                            <a href="<?php echo htmlspecialchars($application['resume_path']); ?>" target="_blank" class="inline-flex items-center text-blue-600 hover:text-blue-800">
                                <i class="fas fa-file-pdf mr-2"></i> Download Resume
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (!empty($application['cover_letter'])): ?>
                <div class="p-6 border-t border-gray-200">
                    <h3 class="text-lg font-medium text-gray-900 mb-4">Cover Letter</h3>
                    <div class="prose max-w-none bg-gray-50 p-4 rounded-lg">
                        <?php echo nl2br(htmlspecialchars($application['cover_letter'])); ?>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>