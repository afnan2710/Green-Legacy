<?php
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}
$user_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    header("Location: appointments.php");
    exit();
}

$appointment_id = intval($_GET['id']);

// Fetch appointment details with payment info
$stmt = $conn->prepare("SELECT a.*, ad.username as consultant_name, ad.email as consultant_email,
                       cf.fee_id, cf.amount, cf.payment_status
                       FROM appointments a
                       JOIN admins ad ON a.consultant_id = ad.admin_id
                       LEFT JOIN consultant_fee cf ON a.id = cf.appointment_id
                       WHERE a.id = ? AND a.user_id = ?");
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if (!$appointment) {
    header("Location: appointments.php");
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

// Handle cancellation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['cancel_appointment'])) {
    $stmt = $conn->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND user_id = ?");
    $stmt->bind_param("ii", $appointment_id, $user_id);

    if ($stmt->execute()) {
        header("Location: appointment_details.php?id=$appointment_id");
        exit();
    } else {
        $error = "Failed to cancel appointment. Please try again.";
    }
}

// Handle payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_fee'])) {
    // In a real application, you would integrate with a payment gateway here
    // For demonstration, we'll just update the payment status

    $stmt = $conn->prepare("UPDATE consultant_fee SET payment_status = 'paid' WHERE fee_id = ? AND appointment_id = ?");
    $stmt->bind_param("ii", $appointment['fee_id'], $appointment_id);

    if ($stmt->execute()) {
        header("Location: appointment_details.php?id=$appointment_id");
        exit();
    } else {
        $error = "Failed to process payment. Please try again.";
    }
}

include 'navbar.php';

?>
<div class="container mx-auto px-4 py-8">

    <div class="max-w-4xl mx-auto">
        <div class="flex items-center mt-10 mb-6">
            <a href="appointments.php" class="text-green-600 hover:text-green-800 mr-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M9.707 16.707a1 1 0 01-1.414 0l-6-6a1 1 0 010-1.414l6-6a1 1 0 011.414 1.414L5.414 9H17a1 1 0 110 2H5.414l4.293 4.293a1 1 0 010 1.414z" clip-rule="evenodd" />
                </svg>
            </a>
            <h1 class="text-3xl font-bold text-gray-800">Appointment Details</h1>
        </div>

        <?php if (isset($error)): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="bg-white shadow rounded-lg overflow-hidden mb-8">
            <div class="p-6 border-b border-gray-200">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-xl font-semibold text-gray-800">Consultation with <?php echo htmlspecialchars($appointment['consultant_name']); ?></h2>
                        <p class="text-gray-600">Appointment ID: #<?php echo $appointment['id']; ?></p>
                    </div>
                    <span class="px-3 py-1 rounded-full text-sm font-medium 
                        <?php echo $appointment['status'] === 'confirmed' ? 'bg-green-100 text-green-800' : ($appointment['status'] === 'pending' ? 'bg-yellow-100 text-yellow-800' : ($appointment['status'] === 'completed' ? 'bg-blue-100 text-blue-800' : 'bg-red-100 text-red-800')); ?>">
                        <?php echo ucfirst($appointment['status']); ?>
                    </span>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6 p-6">
                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Appointment Information</h3>
                    <div class="space-y-2">
                        <div class="flex justify-between">
                            <span class="text-gray-600">Date:</span>
                            <span class="font-medium"><?php echo date('F j, Y', strtotime($appointment['date'])); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Time:</span>
                            <span class="font-medium"><?php echo $time_slots[$appointment['time_slot']]; ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Consultant:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($appointment['consultant_name']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Email:</span>
                            <span class="font-medium"><?php echo htmlspecialchars($appointment['consultant_email']); ?></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600">Booked On:</span>
                            <span class="font-medium"><?php echo date('F j, Y g:i A', strtotime($appointment['created_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <div>
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Your Notes</h3>
                    <?php if (!empty($appointment['notes'])): ?>
                        <p class="text-gray-700"><?php echo nl2br(htmlspecialchars($appointment['notes'])); ?></p>
                    <?php else: ?>
                        <p class="text-gray-500">No notes provided</p>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Payment Section -->
            <?php if ($appointment['status'] === 'completed' && isset($appointment['fee_id'])): ?>
                <div class="border-t border-gray-200 p-6">
                    <h3 class="text-lg font-medium text-gray-900 mb-3">Payment Information</h3>
                    <div class="flex justify-between items-center">
                        <div>
                            <p class="text-gray-600">Consultation Fee: <span class="font-medium">$<?php echo number_format($appointment['amount'], 2); ?></span></p>
                            <p class="text-gray-600">Status:
                                <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                    <?php echo $appointment['payment_status'] === 'paid' ? 'bg-green-100 text-green-800' : 'bg-yellow-100 text-yellow-800'; ?>">
                                    <?php echo ucfirst($appointment['payment_status']); ?>
                                </span>
                            </p>
                        </div>
                        <?php if ($appointment['payment_status'] === 'pending'): ?>
                            <form method="POST" action="">
                                <button type="submit" name="pay_fee"
                                    class="bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded-md shadow-sm transition-colors">
                                    Pay Now
                                </button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endif; ?>

            <div class="bg-gray-50 px-6 py-4 flex flex-col sm:flex-row justify-between items-center">
                <?php if ($appointment['status'] === 'pending'): ?>
                    <form method="POST" action="" class="w-full sm:w-auto">
                        <button type="submit" name="cancel_appointment"
                            class="w-full bg-red-600 hover:bg-red-700 text-white font-medium py-2 px-4 rounded-md shadow-sm transition-colors">
                            Cancel Appointment
                        </button>
                    </form>
                <?php endif; ?>

                <a href="appointments.php"
                    class="mt-3 sm:mt-0 w-full sm:w-auto inline-flex justify-center items-center px-4 py-2 border border-gray-300 shadow-sm text-sm font-medium rounded-md text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Back to Appointments
                </a>
            </div>
        </div>
        <!-- Add this in the actions section where you want the video call button -->
        <?php if ($appointment['status'] === 'confirmed'): ?>
            <div class="mt-6 p-4 bg-green-50 rounded-lg border border-green-200">
                <h3 class="text-lg font-medium text-green-800 mb-3">Video Consultation</h3>
                <p class="text-green-700 mb-4">Join your scheduled video call with the consultant.</p>
                <a href="user_video_call.php?appointment_id=<?php echo $appointment_id; ?>"
                    class="inline-flex items-center justify-center px-6 py-3 bg-green-600 hover:bg-green-700 text-white font-medium rounded-md shadow-sm transition-colors">
                    <i class="fas fa-video mr-2"></i> Join Video Call
                </a>
                <p class="text-sm text-green-600 mt-2">
                    Available during your scheduled appointment time:
                    <?php echo date('F j, Y', strtotime($appointment['date'])); ?>
                    at <?php echo $time_slots[$appointment['time_slot']]; ?>
                </p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php include 'footer.php'; ?>