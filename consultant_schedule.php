<?php
require_once 'db_connect.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

// Check consultant authentication
session_start();
if (!isset($_SESSION['consultant_logged_in']) || $_SESSION['consultant_logged_in'] !== true) {
    header('Location: consultant_login.php');
    exit;
}

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

$consultant_id = $_SESSION['consultant_id'];

// Get filter parameters from URL
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-d');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-d', strtotime('+1 week'));

// Get week parameters for calendar view
$week_start = isset($_GET['week_start']) ? $_GET['week_start'] : date('Y-m-d', strtotime('monday this week'));
$week_end = isset($_GET['week_end']) ? $_GET['week_end'] : date('Y-m-d', strtotime('sunday this week'));

// Get consultant's appointments with date filtering
$appointments = [];
try {
    $stmt = $conn->prepare("SELECT 
        a.id, a.user_id, a.date, a.time_slot, a.status, 
        a.notes, a.created_at, 
        u.firstname, u.lastname, u.email, u.phone
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        WHERE a.consultant_id = ? 
        AND a.date BETWEEN ? AND ?
        ORDER BY a.date ASC, a.time_slot ASC");

    if (!$stmt) {
        throw new Exception("Prepare failed: " . $conn->error);
    }

    $stmt->bind_param("iss", $consultant_id, $start_date, $end_date);
    if (!$stmt->execute()) {
        throw new Exception("Execute failed: " . $stmt->error);
    }

    $result = $stmt->get_result();
    if (!$result) {
        throw new Exception("Get result failed: " . $stmt->error);
    }

    $appointments = array_map(function ($appt) {
        $appt['time_slot'] = (int)$appt['time_slot'];
        return $appt;
    }, $appointments);

    // Filter appointments for calendar view (add this before the calendar HTML)
    $filtered_appointments = array_filter($appointments, function ($a) use ($week_start, $week_end) {
        return $a['date'] >= $week_start && $a['date'] <= $week_end;
    });

    $appointments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    $_SESSION['error_message'] = "Error loading appointments. Please try again later.";
}

// Handle status update with validation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_status'])) {
    $appointment_id = filter_input(INPUT_POST, 'appointment_id', FILTER_VALIDATE_INT);
    $status = filter_input(INPUT_POST, 'status', FILTER_SANITIZE_STRING);

    if ($appointment_id && $status && in_array($status, ['pending', 'confirmed', 'completed', 'cancelled'])) {
        try {
            $stmt = $conn->prepare("UPDATE appointments SET status = ? WHERE id = ? AND consultant_id = ?");
            if (!$stmt) {
                throw new Exception("Prepare failed: " . $conn->error);
            }

            $stmt->bind_param("sii", $status, $appointment_id, $consultant_id);
            if (!$stmt->execute()) {
                throw new Exception("Execute failed: " . $stmt->error);
            }

            if ($stmt->affected_rows > 0) {
                $_SESSION['success_message'] = "Appointment status updated successfully!";

                // Update appointment status in the local array
                foreach ($appointments as &$appointment) {
                    if ($appointment['id'] == $appointment_id) {
                        $appointment['status'] = $status;

                        // Send confirmation email if status changed to 'confirmed'
                        if ($status === 'confirmed') {
                            sendConfirmationEmail($appointment);
                        }
                        break;
                    }
                }
            } else {
                $_SESSION['error_message'] = "No appointment found or you don't have permission to update it.";
            }

            $stmt->close();
        } catch (Exception $e) {
            error_log("Error updating appointment: " . $e->getMessage());
            $_SESSION['error_message'] = "Error updating appointment status. Please try again.";
        }
    } else {
        $_SESSION['error_message'] = "Invalid appointment data provided.";
    }

    // Redirect to prevent form resubmission
    header("Location: consultant_schedule.php");
    exit;
}

function sendConfirmationEmail($appointment)
{
    $mail = new PHPMailer(true);

    try {
        // Server settings
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'info.afnan27@gmail.com';
        $mail->Password = 'rokp jusi apxx wjkn';
        $mail->SMTPSecure = 'tls';
        $mail->Port = 587;

        // Recipients
        $mail->setFrom('no-reply@yourdomain.com', 'Green Legacy');
        $mail->addAddress($appointment['email'], $appointment['firstname'] . ' ' . $appointment['lastname']);

        // Content
        $mail->isHTML(true);
        $mail->Subject = 'Consultation Booking Has Been Confirmed';

        $time_slot = [
            '09:00 - 10:00',
            '10:00 - 11:00',
            '11:00 - 12:00',
            '13:00 - 14:00',
            '14:00 - 15:00',
            '15:00 - 16:00',
            '16:00 - 17:00'
        ];

        $mail->Body = '
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; line-height: 1.6; }
                    .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                    .header { background-color: #4CAF50; color: white; padding: 10px; text-align: center; }
                    .content { padding: 20px; }
                    .footer { margin-top: 20px; font-size: 0.8em; color: #666; }
                </style>
            </head>
            <body>
                <div class="container">
                    <div class="header">
                        <h2>Consultation Confirmed!!</h2>
                    </div>
                    <div class="content">
                        <p>Dear ' . htmlspecialchars($appointment['firstname']) . ',</p>
                        <p>Your consultation has been confirmed with the following details:</p>
                        
                        <p><strong>Date:</strong> ' . date('F j, Y', strtotime($appointment['date'])) . '</p>
                        <p><strong>Time:</strong> ' . $time_slot[$appointment['time_slot']] . '</p>
                        
                        <h3>Important Instructions for Video Call:</h3>
                        <ol>
                            <li>Please stay online and logged in to your Green Legacy account at the scheduled time</li>
                            <li>Wait for the call notification in your appointment details section</li>
                            <li>If you cannot access this page, please contact our support team</li>
                            <li>Alternatively, you can message our team through the live chat option</li>
                        </ol>
                        
                        <p>We look forward to speaking with you!</p>
                        <p>Best regards,<br>The Green Legacy Team</p>
                    </div>
                    <div class="footer">
                        <p>This is an automated message. Please do not reply directly to this email.</p>
                    </div>
                </div>
            </body>
            </html>
        ';

        $mail->AltBody = "Dear " . $appointment['firstname'] . ",\n\n"
            . "Your consultation has been confirmed for " . date('F j, Y', strtotime($appointment['date'])) . " at " . $time_slot[$appointment['time_slot']] . ".\n\n"
            . "Important Instructions for Video Call:\n"
            . "1. Please stay online and logged in to your Green Legacy account at the scheduled time\n"
            . "2. Wait for the call notification in your appointment details section\n"
            . "3. If you cannot access this page, please contact our support team\n"
            . "4. Alternatively, you can message our team through the live chat option\n\n"
            . "We look forward to speaking with you!\n\n"
            . "Best regards,\nThe Green Legacy Team";

        $mail->send();
        error_log("Confirmation email sent to " . $appointment['email']);
    } catch (Exception $e) {
        error_log("Error sending confirmation email: " . $mail->ErrorInfo);
    }
}

// Get consultant's availability settings
$availability = [
    'days' => [],
    'slots' => [],
    'notes' => ''
];

try {
    $stmt = $conn->prepare("SELECT days, time_slots, notes FROM consultant_availability WHERE consultant_id = ?");
    $stmt->bind_param("i", $consultant_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $data = $result->fetch_assoc();
        $availability['days'] = json_decode($data['days'], true) ?: [];
        $availability['slots'] = json_decode($data['time_slots'], true) ?: [];
        $availability['notes'] = htmlspecialchars($data['notes']);
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching availability: " . $e->getMessage());
}

// Time slots configuration
$time_slots = [
    '09:00 - 10:00',
    '10:00 - 11:00',
    '11:00 - 12:00',
    '13:00 - 14:00',
    '14:00 - 15:00',
    '15:00 - 16:00',
    '16:00 - 17:00'
];

// Days of the week
$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Schedule - Consultant Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
    <style>
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        @media (max-width: 1024px) {
            .main-content {
                margin-left: 0;
            }
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }

        .schedule-table th,
        .schedule-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .schedule-table th {
            background-color: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }

        .status-pending {
            background-color: #fef3c7;
            color: #92400e;
        }

        .status-confirmed {
            background-color: #d1fae5;
            color: #065f46;
        }

        .status-completed {
            background-color: #dbeafe;
            color: #1e40af;
        }

        .status-cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .badge {
            display: inline-block;
            padding: 0.25em 0.4em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }

        .fc-event {
            cursor: pointer;
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php include 'consultant_sidebar.php'; ?>

    <div class="main-content">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">My Schedule</h1>
            <div class="text-sm text-gray-500">
                <i class="far fa-calendar-alt mr-1"></i>
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_message'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <div class="flex justify-between items-center">
                    <span><?php echo $_SESSION['success_message'];
                            unset($_SESSION['success_message']); ?></span>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-green-700 hover:text-green-900">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <div class="flex justify-between items-center">
                    <span><?php echo $_SESSION['error_message'];
                            unset($_SESSION['error_message']); ?></span>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-red-700 hover:text-red-900">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <!-- Schedule Tabs -->
        <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
            <div class="border-b border-gray-200">
                <nav class="flex -mb-px">
                    <button id="listTab" class="tab-button active py-4 px-6 text-center border-b-2 font-medium text-sm border-blue-500 text-blue-600">
                        <i class="fas fa-list mr-2"></i>Appointment List
                    </button>
                    <button id="calendarTab" class="tab-button py-4 px-6 text-center border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="far fa-calendar-alt mr-2"></i>Calendar View
                    </button>
                    <button id="availabilityTab" class="tab-button py-4 px-6 text-center border-b-2 font-medium text-sm border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300">
                        <i class="fas fa-cog mr-2"></i>Availability Settings
                    </button>
                </nav>
            </div>

            <!-- Tab Contents -->
            <div class="p-6">
                <!-- Appointment List Tab -->
                <div id="listContent" class="tab-content active">
                    <div class="flex justify-between items-center mb-4">
                        <h2 class="text-xl font-semibold text-gray-800">Upcoming Appointments</h2>
                        <div class="flex items-center space-x-2">
                            <div class="text-sm text-gray-500">
                                Showing: <?php echo date('M j', strtotime($start_date)); ?> - <?php echo date('M j, Y', strtotime($end_date)); ?>
                                (including past appointments)
                            </div>
                            <div class="relative">
                                <input type="text" id="dateRangePicker" class="pl-10 pr-4 py-2 border rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-transparent" placeholder="Select date range">
                                <div class="absolute left-3 top-2.5 text-gray-400">
                                    <i class="far fa-calendar-alt"></i>
                                </div>
                            </div>
                            <button id="filterButton" class="px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                                Filter
                            </button>
                            <?php if ($start_date != date('Y-m-d') || $end_date != date('Y-m-d', strtotime('+1 week'))): ?>
                                <a href="consultant_schedule.php" class="px-4 py-2 bg-gray-200 text-gray-700 rounded-lg hover:bg-gray-300">
                                    Reset
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <?php if (empty($appointments)): ?>
                        <div class="text-center py-12 text-gray-500">
                            <i class="fas fa-calendar-alt text-4xl mb-3 text-gray-300"></i>
                            <p class="text-lg">No upcoming appointments scheduled</p>
                            <p class="text-sm mt-2">When you have appointments, they will appear here.</p>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto rounded-lg border border-gray-200">
                            <table class="schedule-table min-w-full divide-y divide-gray-200">
                                <thead class="bg-gray-50">
                                    <tr>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white divide-y divide-gray-200">
                                    <?php foreach ($appointments as $appointment): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-gray-900"><?php echo date('M j, Y', strtotime($appointment['date'])); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo date('D', strtotime($appointment['date'])); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo $time_slots[$appointment['time_slot']]; ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="flex items-center">
                                                    <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                                        <span class="text-blue-600 font-medium"><?php echo strtoupper(substr($appointment['firstname'], 0, 1) . substr($appointment['lastname'], 0, 1)); ?></span>
                                                    </div>
                                                    <div class="ml-4">
                                                        <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($appointment['firstname'] . ' ' . $appointment['lastname']); ?></div>
                                                    </div>
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm text-gray-900"><?php echo htmlspecialchars($appointment['email']); ?></div>
                                                <div class="text-sm text-gray-500"><?php echo htmlspecialchars($appointment['phone']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo 'status-' . $appointment['status']; ?>">
                                                    <?php echo ucfirst($appointment['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                                <div class="flex items-center space-x-2">
                                                    <form method="post" class="inline-flex">
                                                        <input type="hidden" name="appointment_id" value="<?php echo $appointment['id']; ?>">
                                                        <select name="status" onchange="this.form.submit()" class="text-sm border border-gray-300 rounded px-2 py-1 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                                            <option value="pending" <?php echo $appointment['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                            <option value="confirmed" <?php echo $appointment['status'] === 'confirmed' ? 'selected' : ''; ?>>Confirmed</option>
                                                            <option value="completed" <?php echo $appointment['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                            <option value="cancelled" <?php echo $appointment['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                        </select>
                                                        <input type="hidden" name="update_status" value="1">
                                                    </form>
                                                    <button class="p-1 text-blue-500 hover:text-blue-700 view-notes"
                                                        data-notes="<?php echo htmlspecialchars($appointment['notes']); ?>"
                                                        data-client="<?php echo htmlspecialchars($appointment['firstname'] . ' ' . $appointment['lastname']); ?>"
                                                        data-date="<?php echo date('M j, Y', strtotime($appointment['date'])); ?>"
                                                        data-time="<?php echo $time_slots[$appointment['time_slot']]; ?>">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>

                        <div class="mt-4 flex justify-between items-center text-sm text-gray-500">
                            <div>
                                Showing <span class="font-medium">1</span> to <span class="font-medium"><?php echo count($appointments); ?></span> of <span class="font-medium"><?php echo count($appointments); ?></span> appointments
                            </div>
                            <div class="flex space-x-2">
                                <button class="px-3 py-1 border rounded-md bg-white text-gray-700 disabled:opacity-50" disabled>
                                    Previous
                                </button>
                                <button class="px-3 py-1 border rounded-md bg-white text-gray-700 hover:bg-gray-50">
                                    Next
                                </button>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Calendar View Tab -->
                <div id="calendarContent" class="tab-content hidden">
                    <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                        <h2 class="text-xl font-semibold text-gray-800">Weekly Schedule</h2>

                        <div class="flex items-center gap-2">
                            <button id="prevWeek" class="p-2 bg-white border rounded-lg shadow-sm hover:bg-gray-50">
                                <i class="fas fa-chevron-left text-gray-600"></i>
                            </button>

                            <div id="weekDisplay" class="text-center px-4 py-2 bg-white rounded-lg shadow-sm font-medium">
                                <?php
                                $start = new DateTime($week_start);
                                $end = new DateTime($week_end);
                                echo $start->format('M j') . ' - ' . $end->format('M j, Y');
                                ?>
                            </div>

                            <button id="nextWeek" class="p-2 bg-white border rounded-lg shadow-sm hover:bg-gray-50">
                                <i class="fas fa-chevron-right text-gray-600"></i>
                            </button>

                            <button id="todayBtn" class="ml-2 px-4 py-2 bg-blue-500 text-white rounded-lg hover:bg-blue-600">
                                Today
                            </button>
                        </div>
                    </div>

                    <div class="bg-white rounded-xl shadow overflow-hidden">
                        <!-- Weekday Headers -->
                        <div class="grid grid-cols-7 border-b">
                            <?php
                            $days = [];
                            $current = new DateTime($week_start);
                            $end = new DateTime($week_end);

                            while ($current <= $end) {
                                $days[] = clone $current;
                                $current->modify('+1 day');
                            }

                            foreach ($days as $day):
                                $isToday = $day->format('Y-m-d') == date('Y-m-d');
                            ?>
                                <div class="py-3 text-center <?php echo $isToday ? 'bg-blue-50' : 'bg-gray-50'; ?>">
                                    <div class="text-sm font-medium text-gray-500">
                                        <?php echo $day->format('D'); ?>
                                    </div>
                                    <div class="mt-1 text-lg font-semibold <?php echo $isToday ? 'text-blue-600' : 'text-gray-800'; ?>">
                                        <?php echo $day->format('j'); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Time Slots -->
                        <div class="divide-y">
                            <?php foreach ($time_slots as $slot_index => $time_slot): ?>
                                <div class="grid grid-cols-7 min-h-20">
                                    <!-- Time Label -->
                                    <div class="border-r p-2 flex items-center justify-center bg-gray-50">
                                        <span class="text-sm font-medium text-gray-500">
                                            <?php echo explode(' ', $time_slot)[0]; ?>
                                        </span>
                                    </div>

                                    <!-- Day Columns -->
                                    <?php foreach ($days as $day):
                                        $day_str = $day->format('Y-m-d');
                                        $appointments_for_slot = array_filter($appointments, function ($a) use ($day_str, $slot_index) {
                                            return $a['date'] == $day_str && $a['time_slot'] == $slot_index;
                                        });
                                    ?>
                                        <div class="border-r p-1 relative">
                                            <?php if (!empty($appointments_for_slot)):
                                                $appt = reset($appointments_for_slot);
                                                $status_class = [
                                                    'pending' => 'bg-yellow-50 border-yellow-200',
                                                    'confirmed' => 'bg-green-50 border-green-200',
                                                    'completed' => 'bg-blue-50 border-blue-200',
                                                    'cancelled' => 'bg-gray-100 border-gray-300'
                                                ][$appt['status']];
                                            ?>
                                                <div class="absolute inset-1 border rounded-lg p-2 <?php echo $status_class; ?> hover:shadow-sm cursor-pointer appointment-card"
                                                    data-appointment='<?php echo htmlspecialchars(json_encode($appt), ENT_QUOTES, 'UTF-8'); ?>'>
                                                    <div class="flex items-center">
                                                        <div class="flex-shrink-0 h-8 w-8 rounded-full bg-white flex items-center justify-center border">
                                                            <span class="text-xs font-medium text-gray-600">
                                                                <?php echo strtoupper(substr($appt['firstname'], 0, 1) . substr($appt['lastname'], 0, 1)); ?>
                                                            </span>
                                                        </div>
                                                        <div class="ml-2 overflow-hidden">
                                                            <p class="text-sm font-medium text-gray-900 truncate">
                                                                <?php echo htmlspecialchars($appt['firstname'] . ' ' . $appt['lastname']); ?>
                                                            </p>
                                                            <p class="text-xs text-gray-500 truncate">
                                                                <?php echo $time_slot; ?>
                                                            </p>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <!-- Appointment Details Modal -->
                <div id="appointmentModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
                    <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
                        <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                            <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
                        </div>

                        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

                        <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                            <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                                <div class="sm:flex sm:items-start">
                                    <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                                        <h3 class="text-lg leading-6 font-medium text-gray-900" id="modalClientName"></h3>
                                        <div class="mt-1 text-sm text-gray-500" id="modalDateTime"></div>

                                        <div class="mt-4 grid grid-cols-2 gap-4">
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Status</label>
                                                <select id="modalStatus" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm rounded-md">
                                                    <option value="pending">Pending</option>
                                                    <option value="confirmed">Confirmed</option>
                                                    <option value="completed">Completed</option>
                                                    <option value="cancelled">Cancelled</option>
                                                </select>
                                            </div>
                                            <div>
                                                <label class="block text-sm font-medium text-gray-700">Time Slot</label>
                                                <p class="mt-1 text-sm text-gray-900" id="modalTimeSlot"></p>
                                            </div>
                                        </div>

                                        <div class="mt-4">
                                            <label class="block text-sm font-medium text-gray-700">Contact Info</label>
                                            <p class="mt-1 text-sm text-gray-900" id="modalContactInfo"></p>
                                        </div>

                                        <div class="mt-4">
                                            <label class="block text-sm font-medium text-gray-700">Notes</label>
                                            <p class="mt-1 text-sm text-gray-900 bg-gray-50 p-3 rounded" id="modalNotes"></p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                                <button type="button" id="saveChangesBtn" class="w-full inline-flex justify-center rounded-md border border-transparent shadow-sm px-4 py-2 bg-blue-600 text-base font-medium text-white hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:ml-3 sm:w-auto sm:text-sm">
                                    Save Changes
                                </button>
                                <button type="button" onclick="closeModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                                    Close
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Availability Settings Tab -->
                <div id="availabilityContent" class="tab-content hidden">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Set Your Availability</h2>
                    <form method="post" action="update_availability.php" id="availabilityForm">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Working Days</label>
                                <div class="space-y-2">
                                    <?php foreach ($days as $day): ?>
                                        <?php
                                        // If $day is a DateTime object, convert to string
                                        $day_str = is_object($day) && $day instanceof DateTime ? $day->format('l') : $day;
                                        ?>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="day_<?php echo strtolower($day_str); ?>" name="days[]" value="<?php echo $day_str; ?>"
                                                <?php echo in_array($day_str, $availability['days']) ? 'checked' : ''; ?>
                                                class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                            <label for="day_<?php echo strtolower($day_str); ?>" class="ml-2 block text-sm text-gray-700"><?php echo $day_str; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Available Time Slots</label>
                                <div class="space-y-2">
                                    <?php foreach ($time_slots as $index => $slot): ?>
                                        <div class="flex items-center">
                                            <input type="checkbox" id="slot_<?php echo $index; ?>" name="slots[]" value="<?php echo $index; ?>"
                                                <?php echo in_array($index, $availability['slots']) ? 'checked' : ''; ?>
                                                class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                            <label for="slot_<?php echo $index; ?>" class="ml-2 block text-sm text-gray-700"><?php echo $slot; ?></label>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div>
                                <label class="block text-sm font-medium text-gray-700 mb-2">Special Notes</label>
                                <textarea name="availability_notes" class="w-full border border-gray-300 rounded-md px-3 py-2 h-32 focus:outline-none focus:ring-1 focus:ring-blue-500" placeholder="Any special instructions or notes about your availability"><?php echo $availability['notes']; ?></textarea>
                                <p class="mt-1 text-xs text-gray-500">This will be shown to clients when they book appointments.</p>
                            </div>
                        </div>

                        <div class="mt-6 flex justify-end space-x-3">
                            <button type="reset" class="px-4 py-2 border border-gray-300 rounded-md text-gray-700 bg-white hover:bg-gray-50">
                                Reset
                            </button>
                            <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700">
                                Save Availability
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Notes Modal -->
    <div id="notesModal" class="fixed inset-0 z-50 hidden overflow-y-auto">
        <div class="flex items-center justify-center min-h-screen pt-4 px-4 pb-20 text-center sm:block sm:p-0">
            <div class="fixed inset-0 transition-opacity" aria-hidden="true">
                <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
            </div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div class="inline-block align-bottom bg-white rounded-lg text-left overflow-hidden shadow-xl transform transition-all sm:my-8 sm:align-middle sm:max-w-lg sm:w-full">
                <div class="bg-white px-4 pt-5 pb-4 sm:p-6 sm:pb-4">
                    <div class="sm:flex sm:items-start">
                        <div class="mt-3 text-center sm:mt-0 sm:ml-4 sm:text-left w-full">
                            <h3 class="text-lg leading-6 font-medium text-gray-900 mb-1" id="notesModalTitle"></h3>
                            <p class="text-sm text-gray-500 mb-3" id="notesModalDateTime"></p>
                            <div class="mt-2">
                                <p id="notesContent" class="text-sm text-gray-700 bg-gray-50 p-3 rounded"></p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="bg-gray-50 px-4 py-3 sm:px-6 sm:flex sm:flex-row-reverse">
                    <button type="button" onclick="closeNotesModal()" class="mt-3 w-full inline-flex justify-center rounded-md border border-gray-300 shadow-sm px-4 py-2 bg-white text-base font-medium text-gray-700 hover:bg-gray-50 sm:mt-0 sm:ml-3 sm:w-auto sm:text-sm">
                        Close
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- JavaScript Libraries -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.10.1/main.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>

    <script>
        // Mobile sidebar toggle
        document.getElementById('sidebarToggle').addEventListener('click', function() {
            document.getElementById('consultantSidebar').classList.toggle('open');
        });

        // Tab switching functionality
        const tabs = ['list', 'calendar', 'availability'];
        tabs.forEach(tab => {
            const tabButton = document.getElementById(`${tab}Tab`);
            const tabContent = document.getElementById(`${tab}Content`);

            tabButton.addEventListener('click', () => {
                // Update buttons
                document.querySelectorAll('.tab-button').forEach(btn => {
                    btn.classList.remove('active', 'border-blue-500', 'text-blue-600');
                    btn.classList.add('border-transparent', 'text-gray-500');
                });
                tabButton.classList.add('active', 'border-blue-500', 'text-blue-600');
                tabButton.classList.remove('border-transparent', 'text-gray-500');

                // Update content
                document.querySelectorAll('.tab-content').forEach(content => {
                    content.classList.add('hidden');
                    content.classList.remove('active');
                });
                tabContent.classList.remove('hidden');
                tabContent.classList.add('active');

                // Initialize calendar if needed
                if (tab === 'calendar' && !window.calendarInitialized) {
                    initCalendar();
                    window.calendarInitialized = true;
                }
            });
        });

        // Date range picker
        const dateRangePicker = flatpickr("#dateRangePicker", {
            mode: "range",
            dateFormat: "Y-m-d",
            defaultDate: ["<?php echo $start_date; ?>", "<?php echo $end_date; ?>"]
        });

        // Filter button functionality
        document.getElementById('filterButton').addEventListener('click', function() {
            const selectedDates = dateRangePicker.selectedDates;

            if (selectedDates.length === 2) {
                const startDate = formatDate(selectedDates[0]);
                const endDate = formatDate(selectedDates[1]);

                // Redirect with new date parameters
                window.location.href = `?start_date=${startDate}&end_date=${endDate}`;
            } else {
                Swal.fire({
                    icon: 'error',
                    title: 'Invalid Date Range',
                    text: 'Please select a valid date range',
                    confirmButtonColor: '#3085d6',
                });
            }
        });

        // Helper function to format date as YYYY-MM-DD
        function formatDate(date) {
            const d = new Date(date);
            let month = '' + (d.getMonth() + 1);
            let day = '' + d.getDate();
            const year = d.getFullYear();

            if (month.length < 2)
                month = '0' + month;
            if (day.length < 2)
                day = '0' + day;

            return [year, month, day].join('-');
        }

        // Notes modal handling
        const notesModal = document.getElementById('notesModal');

        document.querySelectorAll('.view-notes').forEach(button => {
            button.addEventListener('click', function() {
                const notes = this.getAttribute('data-notes');
                const client = this.getAttribute('data-client');
                const date = this.getAttribute('data-date');
                const time = this.getAttribute('data-time');

                document.getElementById('notesModalTitle').textContent = `Notes for ${client}`;
                document.getElementById('notesModalDateTime').textContent = `${date} at ${time}`;
                document.getElementById('notesContent').textContent = notes || 'No notes available for this appointment.';
                notesModal.classList.remove('hidden');
                document.body.classList.add('modal-open');
            });
        });

        function closeNotesModal() {
            notesModal.classList.add('hidden');
            document.body.classList.remove('modal-open');
        }

        // Close modal when clicking outside
        window.addEventListener('click', function(event) {
            if (event.target === notesModal) {
                closeNotesModal();
            }
        });

        // Calendar initialization
        function initCalendar() {
            const calendarEl = document.getElementById('calendar');
            const appointments = <?php echo json_encode($appointments); ?>;

            const calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                initialDate: '<?php echo $start_date; ?>',
                validRange: {
                    start: '<?php echo $start_date; ?>',
                    end: '<?php echo date("Y-m-d", strtotime($end_date . " +1 day")); ?>'
                },
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: appointments.map(appointment => {
                    const date = new Date(appointment.date);
                    const timeParts = appointment.time_slot.split('-')[0].split(':');
                    date.setHours(parseInt(timeParts[0]), parseInt(timeParts[1]));

                    return {
                        title: `${appointment.firstname} ${appointment.lastname}`,
                        start: date,
                        end: new Date(date.getTime() + 60 * 60 * 1000), // 1 hour duration
                        extendedProps: {
                            status: appointment.status,
                            notes: appointment.notes,
                            email: appointment.email,
                            phone: appointment.phone
                        },
                        backgroundColor: getStatusColor(appointment.status),
                        borderColor: getStatusColor(appointment.status)
                    };
                }),
                eventClick: function(info) {
                    const event = info.event;
                    const notes = event.extendedProps.notes || 'No notes available';

                    Swal.fire({
                        title: `${event.title}`,
                        html: `
                            <div class="text-left">
                                <p class="mb-2"><strong>Date:</strong> ${event.start.toLocaleDateString()}</p>
                                <p class="mb-2"><strong>Time:</strong> ${event.start.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} - ${event.end.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})}</p>
                                <p class="mb-2"><strong>Status:</strong> <span class="badge ${getStatusClass(event.extendedProps.status)}">${event.extendedProps.status}</span></p>
                                <p class="mb-2"><strong>Contact:</strong> ${event.extendedProps.email}<br>${event.extendedProps.phone}</p>
                                <p class="mt-4"><strong>Notes:</strong></p>
                                <div class="bg-gray-50 p-3 rounded text-sm">${notes}</div>
                            </div>
                        `,
                        showCancelButton: true,
                        confirmButtonText: 'View Details',
                        cancelButtonText: 'Close',
                        confirmButtonColor: '#3085d6',
                    }).then((result) => {
                        if (result.isConfirmed) {
                            // Redirect to detailed view if needed
                        }
                    });
                }
            });

            calendar.render();

            // Navigation buttons
            document.getElementById('prevMonth').addEventListener('click', () => {
                calendar.prev();
            });

            document.getElementById('nextMonth').addEventListener('click', () => {
                calendar.next();
            });

            document.getElementById('todayBtn').addEventListener('click', () => {
                calendar.today();
            });
        }

        function getStatusColor(status) {
            switch (status) {
                case 'confirmed':
                    return '#10B981';
                case 'completed':
                    return '#3B82F6';
                case 'cancelled':
                    return '#EF4444';
                default:
                    return '#F59E0B';
            }
        }

        function getStatusClass(status) {
            switch (status) {
                case 'confirmed':
                    return 'bg-green-100 text-green-800';
                case 'completed':
                    return 'bg-blue-100 text-blue-800';
                case 'cancelled':
                    return 'bg-red-100 text-red-800';
                default:
                    return 'bg-yellow-100 text-yellow-800';
            }
        }

        // Form submission handling
        document.getElementById('availabilityForm').addEventListener('submit', function(e) {
            const daysChecked = document.querySelectorAll('input[name="days[]"]:checked').length;
            const slotsChecked = document.querySelectorAll('input[name="slots[]"]:checked').length;

            if (daysChecked === 0 || slotsChecked === 0) {
                e.preventDefault();
                Swal.fire({
                    icon: 'warning',
                    title: 'Incomplete Availability',
                    text: 'Please select at least one working day and one time slot.',
                    confirmButtonColor: '#3085d6',
                });
            }
        });

        // Week navigation
        document.getElementById('prevWeek').addEventListener('click', function() {
            const prevWeekStart = new Date('<?php echo $week_start; ?>');
            prevWeekStart.setDate(prevWeekStart.getDate() - 7);

            const prevWeekEnd = new Date('<?php echo $week_end; ?>');
            prevWeekEnd.setDate(prevWeekEnd.getDate() - 7);

            window.location.href = `?week_start=${formatDate(prevWeekStart)}&week_end=${formatDate(prevWeekEnd)}`;
        });

        document.getElementById('nextWeek').addEventListener('click', function() {
            const nextWeekStart = new Date('<?php echo $week_start; ?>');
            nextWeekStart.setDate(nextWeekStart.getDate() + 7);

            const nextWeekEnd = new Date('<?php echo $week_end; ?>');
            nextWeekEnd.setDate(nextWeekEnd.getDate() + 7);

            window.location.href = `?week_start=${formatDate(nextWeekStart)}&week_end=${formatDate(nextWeekEnd)}`;
        });

        document.getElementById('todayBtn').addEventListener('click', function() {
            const today = new Date();
            const currentWeekStart = new Date(today);
            currentWeekStart.setDate(today.getDate() - today.getDay() + (today.getDay() === 0 ? -6 : 1));

            const currentWeekEnd = new Date(currentWeekStart);
            currentWeekEnd.setDate(currentWeekStart.getDate() + 6);

            window.location.href = `?week_start=${formatDate(currentWeekStart)}&week_end=${formatDate(currentWeekEnd)}`;
        });

        // Appointment card click handler
        document.querySelectorAll('.appointment-card').forEach(card => {
            card.addEventListener('click', function() {
                const appointment = JSON.parse(this.getAttribute('data-appointment'));
                openModal(appointment);
            });
        });

        // Modal functions
        let currentAppointmentId = null;

        function openModal(appointment) {
            currentAppointmentId = appointment.id;

            document.getElementById('modalClientName').textContent =
                `${appointment.firstname} ${appointment.lastname}`;

            document.getElementById('modalDateTime').textContent =
                `${new Date(appointment.date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })}`;

            document.getElementById('modalTimeSlot').textContent =
                `<?php echo $time_slots[0]; ?>`.replace('0', appointment.time_slot);

            document.getElementById('modalContactInfo').innerHTML =
                `${appointment.email}<br>${appointment.phone || 'No phone provided'}`;

            document.getElementById('modalNotes').textContent =
                appointment.notes || 'No notes available';

            document.getElementById('modalStatus').value = appointment.status;

            document.getElementById('appointmentModal').classList.remove('hidden');
        }

        function closeModal() {
            document.getElementById('appointmentModal').classList.add('hidden');
        }

        // Save changes handler
        document.getElementById('saveChangesBtn').addEventListener('click', function() {
            const newStatus = document.getElementById('modalStatus').value;

            // Create a form and submit it
            const form = document.createElement('form');
            form.method = 'POST';
            form.action = '';

            const appointmentId = document.createElement('input');
            appointmentId.type = 'hidden';
            appointmentId.name = 'appointment_id';
            appointmentId.value = currentAppointmentId;

            const status = document.createElement('input');
            status.type = 'hidden';
            status.name = 'status';
            status.value = newStatus;

            const update = document.createElement('input');
            update.type = 'hidden';
            update.name = 'update_status';
            update.value = '1';

            form.appendChild(appointmentId);
            form.appendChild(status);
            form.appendChild(update);
            document.body.appendChild(form);
            form.submit();
        });

        // Helper function to format date as YYYY-MM-DD
        function formatDate(date) {
            const d = new Date(date);
            let month = '' + (d.getMonth() + 1);
            let day = '' + d.getDate();
            const year = d.getFullYear();

            if (month.length < 2) month = '0' + month;
            if (day.length < 2) day = '0' + day;

            return [year, month, day].join('-');
        }
    </script>
</body>

</html>