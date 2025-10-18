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

// Fetch all available consultants
$consultants = [];
$stmt = $conn->prepare("SELECT a.admin_id, a.username, a.email, a.role, 
                        ca.days, ca.time_slots, ca.notes as availability_notes
                        FROM admins a
                        LEFT JOIN consultant_availability ca ON a.admin_id = ca.consultant_id
                        WHERE a.role = 'consultant'");
$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $consultants[] = $row;
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

include 'navbar.php';
?>

<div class="container mx-auto px-4 py-8">
    <h1 class="text-3xl font-bold text-gray-800 text-center mt-10 mb-6">Book a Consultation</h1>
    
    <div class="bg-white shadow rounded-lg p-6 mb-8">
        <h2 class="text-xl font-semibold mb-4">Available Consultants</h2>
        <p class="text-gray-600 mb-6">Select a consultant to view their availability and book an appointment.</p>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php foreach ($consultants as $consultant): ?>
                <div class="border rounded-lg p-4 hover:shadow-md transition-shadow">
                    <div class="flex items-center mb-3">
                        <div class="bg-gray-200 rounded-full w-12 h-12 flex items-center justify-center mr-3">
                            <span class="text-lg font-medium text-gray-600"><?php echo substr($consultant['username'], 0, 1); ?></span>
                        </div>
                        <div>
                            <h3 class="font-semibold"><?php echo htmlspecialchars($consultant['username']); ?></h3>
                            <p class="text-sm text-gray-500">Gardening Consultant</p>
                        </div>
                    </div>
                    
                    <?php if (!empty($consultant['days'])): ?>
                        <?php 
                        $available_days = json_decode($consultant['days']);
                        $available_slots = json_decode($consultant['time_slots']);
                        ?>
                        <div class="mb-3">
                            <p class="text-sm font-medium">Available Days:</p>
                            <p class="text-sm"><?php echo implode(", ", $available_days); ?></p>
                        </div>
                        
                        <div class="mb-4">
                            <p class="text-sm font-medium">Available Time Slots:</p>
                            <div class="flex flex-wrap gap-1 mt-1">
                                <?php foreach ($available_slots as $slot): ?>
                                    <span class="bg-green-100 text-green-800 text-xs px-2 py-1 rounded"><?php echo $time_slots[$slot]; ?></span>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php else: ?>
                        <p class="text-sm text-yellow-600">Availability not set</p>
                    <?php endif; ?>
                    
                    <?php if (!empty($consultant['availability_notes'])): ?>
                        <p class="text-sm text-gray-600 mb-3"><?php echo htmlspecialchars($consultant['availability_notes']); ?></p>
                    <?php endif; ?>
                    
                    <a href="book_appointment_slot.php?consultant_id=<?php echo $consultant['admin_id']; ?>" 
                       class="inline-block w-full text-center bg-green-600 hover:bg-green-700 text-white font-medium py-2 px-4 rounded transition-colors">
                        Book Appointment
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<?php include 'footer.php'; ?>