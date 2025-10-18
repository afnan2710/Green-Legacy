<?php
require_once 'db_connect.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];

if (!isset($_GET['consultant_id'])) {
    header("Location: book_appointment.php");
    exit();
}

$consultant_id = intval($_GET['consultant_id']);

// Fetch consultant details
$stmt = $conn->prepare("SELECT a.admin_id, a.username, a.email, a.role, 
                        ca.days, ca.time_slots, ca.notes as availability_notes
                        FROM admins a
                        LEFT JOIN consultant_availability ca ON a.admin_id = ca.consultant_id
                        WHERE a.admin_id = ? AND a.role = 'consultant'");
$stmt->bind_param("i", $consultant_id);
$stmt->execute();
$result = $stmt->get_result();
$consultant = $result->fetch_assoc();

if (!$consultant) {
    header("Location: book_appointment.php");
    exit();
}

// Predefined time slots
$time_slots = [
    '9:00 AM - 10:00 AM',
    '10:00 AM - 11:00 AM',
    '11:00 AM - 12:00 PM',
    '1:00 PM - 2:00 PM',
    '2:00 PM - 3:00 PM',
    '3:00 PM - 4:00 PM',
    '4:00 PM - 5:00 PM'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $date = $_POST['date'];
    $time_slot_index = intval($_POST['time_slot']);
    $notes = $_POST['notes'] ?? '';
    
    // Validate date is not in the past
    $today = date('Y-m-d');
    if ($date < $today) {
        $error = "You cannot book an appointment in the past.";
    } else {
        // Check if consultant is available on this day and time slot
        $available_days = json_decode($consultant['days']);
        $available_slots = json_decode($consultant['time_slots']);
        
        $day_name = date('l', strtotime($date));
        
        if (!in_array($day_name, $available_days) || !in_array($time_slot_index, $available_slots)) {
            $error = "The selected consultant is not available on the chosen day/time.";
        } else {
            // Check if slot is already booked
            $stmt = $conn->prepare("SELECT id FROM appointments 
                                   WHERE consultant_id = ? AND date = ? AND time_slot = ?
                                   AND status IN ('pending', 'confirmed')");
            $stmt->bind_param("isi", $consultant_id, $date, $time_slot_index);
            $stmt->execute();
            $result = $stmt->get_result();
            
            if ($result->num_rows > 0) {
                $error = "This time slot is already booked. Please choose another time.";
            } else {
                // Create appointment
                $stmt = $conn->prepare("INSERT INTO appointments 
                                      (user_id, consultant_id, date, time_slot, notes, status)
                                      VALUES (?, ?, ?, ?, ?, 'pending')");
                $stmt->bind_param("iisis", $user_id, $consultant_id, $date, $time_slot_index, $notes);
                
                if ($stmt->execute()) {
                    $appointment_id = $conn->insert_id;
                    
                    // Get user details for email
                    $user_stmt = $conn->prepare("SELECT firstname, lastname, email FROM users WHERE id = ?");
                    $user_stmt->bind_param("i", $user_id);
                    $user_stmt->execute();
                    $user_result = $user_stmt->get_result();
                    $user = $user_result->fetch_assoc();
                    
                    // Send confirmation email
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
                        $mail->addAddress($user['email']);
                        $mail->addCC($consultant['email']);
                        $mail->isHTML(true);

                        $formatted_date = date('F j, Y', strtotime($date));
                        $time_slot_text = $time_slots[$time_slot_index];
                        $consultant_name = $consultant['username'];

                        $mail->Subject = 'Green Legacy - Appointment Confirmation';
                        $mail->Body = "
                            <h2>Appointment Confirmation</h2>
                            <p>Dear {$user['firstname']} {$user['lastname']},</p>
                            
                            <p>Your plant consultation appointment has been successfully booked with <strong>{$consultant_name}</strong>.</p>
                            
                            <h3>Appointment Details:</h3>
                            <ul>
                                <li><strong>Date:</strong> {$formatted_date}</li>
                                <li><strong>Time:</strong> {$time_slot_text}</li>
                                <li><strong>Consultant:</strong> {$consultant_name}</li>
                            </ul>
                            
                            <p>You'll receive a reminder email before your appointment with required details. If you need to cancel or reschedule, please contact us at least 24 hours in advance.</p>
                            
                            <p>Thank you for choosing Green Legacy!</p>
                            
                            <p><em>This is an automated message. Please do not reply directly to this email.</em></p>
                        ";

                        $mail->send();
                    } catch (Exception $e) {
                        // Log the error but don't show it to the user
                        error_log("Failed to send appointment confirmation email: " . $e->getMessage());
                    }
                    
                    header("Location: appointment_details.php?id=$appointment_id");
                    exit();
                } else {
                    $error = "There was an error booking your appointment. Please try again.";
                }
            }
        }
    }
}

include 'navbar.php';
?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-4xl mx-auto">
        <div class="flex items-center mt-10 mb-6">
            <a href="book_appointment.php" class="text-green-600 hover:text-green-800 mr-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Book Appointment with <?php echo htmlspecialchars($consultant['username']); ?></h1>
        </div>
        
        <div class="bg-white shadow rounded-lg p-6 mb-8">
            <?php if (isset($error)): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="date" class="block text-sm font-medium text-gray-700 mb-1">Appointment Date</label>
                        <input type="date" id="date" name="date" min="<?php echo date('Y-m-d'); ?>" 
                               class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500" required>
                    </div>
                    
                    <div>
                        <label for="time_slot" class="block text-sm font-medium text-gray-700 mb-1">Time Slot</label>
                        <select id="time_slot" name="time_slot" 
                                class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500" required>
                            <option value="">Select a time slot</option>
                            <?php 
                            $available_slots = json_decode($consultant['time_slots']);
                            foreach ($available_slots as $slot): ?>
                                <option value="<?php echo $slot; ?>"><?php echo $time_slots[$slot]; ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="md:col-span-2">
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-1">Notes (Optional)</label>
                        <textarea id="notes" name="notes" rows="3" 
                                 class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"></textarea>
                        <p class="mt-1 text-sm text-gray-500">Briefly describe what you'd like to discuss.</p>
                    </div>
                </div>
                
                <div class="mt-6">
                    <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md shadow-sm transition-colors">
                        Book Appointment
                    </button>
                </div>
            </form>
        </div>
        
        <div class="bg-white shadow rounded-lg p-6">
            <h2 class="text-xl font-semibold mb-4">Consultant Availability</h2>
            
            <?php if (!empty($consultant['days'])): ?>
                <div class="mb-4">
                    <p class="text-sm font-medium">Available Days:</p>
                    <p><?php echo implode(", ", json_decode($consultant['days'])); ?></p>
                </div>
                
                <div>
                    <p class="text-sm font-medium">Available Time Slots:</p>
                    <div class="flex flex-wrap gap-2 mt-2">
                        <?php foreach (json_decode($consultant['time_slots']) as $slot): ?>
                            <span class="bg-green-100 text-green-800 text-sm px-3 py-1 rounded-full"><?php echo $time_slots[$slot]; ?></span>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php else: ?>
                <p class="text-yellow-600">This consultant hasn't set their availability yet.</p>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.getElementById('date').addEventListener('change', function() {
    const dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];
    const selectedDate = new Date(this.value);
    const dayName = dayNames[selectedDate.getDay()];
    
    const availableDays = <?php echo $consultant['days'] ?? '[]'; ?>;
    
    if (availableDays.length > 0 && !availableDays.includes(dayName)) {
        alert('The consultant is not available on ' + dayName + '. Please choose another date.');
        this.value = '';
    }
});
</script>

<?php include 'footer.php'; ?>