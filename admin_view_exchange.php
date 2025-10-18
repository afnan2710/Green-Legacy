<?php
require_once 'db_connect.php';
require 'PHPMailer/src/PHPMailer.php';
require 'PHPMailer/src/SMTP.php';
require 'PHPMailer/src/Exception.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

session_start();

// Check if admin is logged in
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit();
}

// Check if exchange ID is provided
if (!isset($_GET['id'])) {
    $_SESSION['error_msg'] = "Exchange ID not provided.";
    header("Location: admin_exchanges.php");
    exit();
}

$exchange_id = intval($_GET['id']);

// Get the exchange offer details
$query = "SELECT e.*, u.firstname, u.lastname, u.email as user_email, u.phone, u.profile_pic as user_image,
          (SELECT username FROM admins WHERE admin_id = e.admin_id) as admin_name
          FROM exchange_offers e
          JOIN users u ON e.user_id = u.id
          WHERE e.id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $exchange_id);
$stmt->execute();
$result = $stmt->get_result();
$offer = $result->fetch_assoc();

if (!$offer) {
    $_SESSION['error_msg'] = "Exchange offer not found.";
    header("Location: admin_exchanges.php");
    exit();
}

// Get all interests for this exchange
$query = "SELECT ei.*, u.firstname, u.lastname, u.email, u.phone, u.profile_pic
          FROM exchange_interests ei
          JOIN users u ON ei.interested_user_id = u.id
          WHERE ei.exchange_id = ?
          ORDER BY 
            CASE ei.status
                WHEN 'accepted' THEN 1
                WHEN 'pending' THEN 2
                WHEN 'rejected' THEN 3
            END,
            ei.created_at DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $exchange_id);
$stmt->execute();
$interests = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Handle mark as completed
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['mark_completed'])) {
    $final_interest_id = (int)$_POST['final_interest_id'];
    $admin_id = $_SESSION['admin_id'];
    
    // Verify that the selected interest exists and is accepted
    $query = "SELECT ei.*, u.email as interested_user_email, u.firstname as interested_firstname, 
                     u.lastname as interested_lastname, eo.user_email as offerer_email,
                     eo.firstname as offerer_firstname, eo.lastname as offerer_lastname
              FROM exchange_interests ei
              JOIN users u ON ei.interested_user_id = u.id
              JOIN (SELECT e.id, u.email as user_email, u.firstname, u.lastname 
                    FROM exchange_offers e 
                    JOIN users u ON e.user_id = u.id 
                    WHERE e.id = ?) as eo ON ei.exchange_id = eo.id
              WHERE ei.id = ? AND ei.status = 'accepted'";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("ii", $exchange_id, $final_interest_id);
    $stmt->execute();
    $final_interest = $stmt->get_result()->fetch_assoc();
    
    if (!$final_interest) {
        $_SESSION['error_msg'] = "Selected interest not found or not accepted.";
    } else {
        // Update exchange status to completed
        $query = "UPDATE exchange_offers 
                  SET status = 'completed', admin_id = ?, updated_at = CURRENT_TIMESTAMP 
                  WHERE id = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("ii", $admin_id, $exchange_id);
        
        if ($stmt->execute()) {
            // Send confirmation emails
            if (sendCompletionEmails($final_interest, $offer)) {
                $_SESSION['success_msg'] = "Exchange marked as completed and confirmation emails sent to both parties!";
            } else {
                $_SESSION['success_msg'] = "Exchange marked as completed but there was an issue sending confirmation emails.";
            }
        } else {
            $_SESSION['error_msg'] = "Error updating exchange status.";
        }
    }
    
    header("Location: admin_view_exchange.php?id=" . $exchange_id);
    exit();
}

// Function to send completion emails
function sendCompletionEmails($interest, $offer) {
    $mail_sent = true;
    
    try {
        // Email to offerer
        $mail1 = new PHPMailer(true);
        $mail1->isSMTP();
        $mail1->Host = 'smtp.gmail.com';
        $mail1->SMTPAuth = true;
        $mail1->Username = 'info.afnan27@gmail.com';
        $mail1->Password = 'rokp jusi apxx wjkn';
        $mail1->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail1->Port = 587;
        
        $mail1->setFrom('info.afnan27@gmail.com', 'Green Legacy');
        $mail1->addAddress($offer['user_email']);
        $mail1->isHTML(true);
        $mail1->Subject = 'Exchange Completed - Green Legacy';
        
        $mail1->Body = "
            <h2>Exchange Completed Successfully!</h2>
            <p>Dear {$offer['firstname']} {$offer['lastname']},</p>
            <p>Your exchange offer for <strong>{$offer['plant_name']}</strong> has been marked as completed.</p>
            
            <h3>Exchange Details:</h3>
            <ul>
                <li><strong>Your Plant:</strong> {$offer['plant_name']} ({$offer['plant_type']})</li>
                <li><strong>Accepted By:</strong> {$interest['interested_firstname']} {$interest['interested_lastname']}</li>
                <li><strong>Contact Email:</strong> {$interest['interested_user_email']}</li>
        ";
        
        if ($interest['offered_plant']) {
            $mail1->Body .= "<li><strong>You will receive:</strong> {$interest['offered_plant']}</li>";
        }
        if ($interest['offered_amount']) {
            $mail1->Body .= "<li><strong>Amount:</strong> $" . number_format($interest['offered_amount'], 2) . "</li>";
        }
        
        $mail1->Body .= "
            </ul>
            
            <p>Please coordinate with {$interest['interested_firstname']} to complete the exchange.</p>
            <p>Thank you for using Green Legacy!</p>
            <br>
            <p><strong>Green Legacy Team</strong></p>
        ";
        
        $mail1->send();
        
        // Email to interested user
        $mail2 = new PHPMailer(true);
        $mail2->isSMTP();
        $mail2->Host = 'smtp.gmail.com';
        $mail2->SMTPAuth = true;
        $mail2->Username = 'info.afnan27@gmail.com';
        $mail2->Password = 'rokp jusi apxx wjkn';
        $mail2->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail2->Port = 587;
        
        $mail2->setFrom('info.afnan27@gmail.com', 'Green Legacy');
        $mail2->addAddress($interest['interested_user_email']);
        $mail2->isHTML(true);
        $mail2->Subject = 'Exchange Completed - Green Legacy';
        
        $mail2->Body = "
            <h2>Exchange Completed Successfully!</h2>
            <p>Dear {$interest['interested_firstname']} {$interest['interested_lastname']},</p>
            <p>Your interest in the exchange for <strong>{$offer['plant_name']}</strong> has been accepted and the exchange is now completed.</p>
            
            <h3>Exchange Details:</h3>
            <ul>
                <li><strong>Plant You'll Receive:</strong> {$offer['plant_name']} ({$offer['plant_type']})</li>
                <li><strong>From:</strong> {$offer['firstname']} {$offer['lastname']}</li>
                <li><strong>Contact Email:</strong> {$offer['user_email']}</li>
        ";
        
        if ($interest['offered_plant']) {
            $mail2->Body .= "<li><strong>You offered:</strong> {$interest['offered_plant']}</li>";
        }
        if ($interest['offered_amount']) {
            $mail2->Body .= "<li><strong>Amount:</strong> $" . number_format($interest['offered_amount'], 2) . "</li>";
        }
        
        $mail2->Body .= "
            </ul>
            
            <p>Please coordinate with {$offer['firstname']} to complete the exchange.</p>
            <p>Thank you for using Green Legacy!</p>
            <br>
            <p><strong>Green Legacy Team</strong></p>
        ";
        
        $mail2->send();
        
    } catch (Exception $e) {
        error_log("Mailer Error: " . $e->getMessage());
        $mail_sent = false;
    }
    
    return $mail_sent;
}
?>

<?php include 'admin_sidebar.php'; ?>
<style>
    .main-content {
        margin-left: 280px;
    }
    .plant-image {
        height: 400px;
        object-fit: cover;
        width: 100%;
    }
    .thumbnail {
        height: 80px;
        width: 80px;
        object-fit: cover;
        cursor: pointer;
        border: 2px solid transparent;
    }
    .thumbnail:hover, .thumbnail.active {
        border-color: #10B981;
    }
    .user-image {
        width: 50px;
        height: 50px;
        object-fit: cover;
    }
    .interest-card {
        border-left: 4px solid #e5e7eb;
    }
    .interest-card.accepted {
        border-left-color: #10B981;
    }
    .interest-card.pending {
        border-left-color: #f59e0b;
    }
    .interest-card.rejected {
        border-left-color: #ef4444;
    }
</style>

<div class="main-content">
    <div class="ml-0 lg:ml-[280px] min-h-screen bg-gray-50">
        <div class="p-6">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <div>
                    <h1 class="text-3xl font-bold text-gray-800">Exchange Offer Details</h1>
                    <p class="text-gray-600 mt-1">Manage and view complete exchange information</p>
                </div>
                <a href="admin_exchanges.php" class="bg-green-600 text-white px-4 py-2 rounded-lg hover:bg-green-700 transition">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Exchanges
                </a>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                    <?= $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                    <?= $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
                </div>
            <?php endif; ?>

            <!-- Exchange Offer Details -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <!-- Left Column - Offer Details -->
                <div class="lg:col-span-2 space-y-6">
                    <!-- Image Gallery -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Plant Images</h2>
                        <?php if ($offer['images']): ?>
                            <?php 
                                $images = explode(',', $offer['images']);
                                $mainImage = $images[0];
                            ?>
                            <img id="mainImage" src="<?= htmlspecialchars($mainImage) ?>" alt="<?= htmlspecialchars($offer['plant_name']) ?>" class="plant-image rounded-lg">
                            
                            <?php if (count($images) > 1): ?>
                                <div class="flex flex-wrap gap-2 mt-4">
                                    <?php foreach ($images as $index => $image): ?>
                                        <img src="<?= htmlspecialchars($image) ?>" 
                                             alt="Thumbnail <?= $index + 1 ?>" 
                                             class="thumbnail <?= $index === 0 ? 'active' : '' ?>" 
                                             onclick="changeMainImage('<?= htmlspecialchars($image) ?>', this)">
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="plant-image bg-gray-100 rounded-lg flex items-center justify-center">
                                <i class="fas fa-seedling text-6xl text-gray-300"></i>
                            </div>
                        <?php endif; ?>
                    </div>

                    <!-- Plant Details -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Plant Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Plant Name</label>
                                <p class="mt-1 text-lg font-semibold text-gray-900"><?= htmlspecialchars($offer['plant_name']) ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Plant Type</label>
                                <p class="mt-1 text-lg text-gray-900"><?= htmlspecialchars($offer['plant_type']) ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Plant Age</label>
                                <p class="mt-1 text-gray-900"><?= htmlspecialchars($offer['plant_age'] ?? 'Not specified') ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Plant Health</label>
                                <p class="mt-1">
                                    <span class="px-2 py-1 text-xs rounded-full 
                                        <?= $offer['plant_health'] == 'excellent' ? 'bg-green-100 text-green-800' : 
                                           ($offer['plant_health'] == 'good' ? 'bg-blue-100 text-blue-800' : 
                                           ($offer['plant_health'] == 'average' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800')) ?>">
                                        <?= ucfirst($offer['plant_health']) ?>
                                    </span>
                                </p>
                            </div>
                        </div>
                        
                        <div class="mt-4">
                            <label class="block text-sm font-medium text-gray-700">Description</label>
                            <p class="mt-1 text-gray-900 whitespace-pre-line"><?= htmlspecialchars($offer['description']) ?></p>
                        </div>
                    </div>

                    <!-- Exchange Details -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Exchange Details</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Exchange Type</label>
                                <p class="mt-1">
                                    <span class="px-2 py-1 text-sm rounded-full 
                                        <?= $offer['exchange_type'] == 'with_money' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?>">
                                        <?= $offer['exchange_type'] == 'with_money' ? 'With Money' : 'Free Exchange' ?>
                                    </span>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Looking For</label>
                                <p class="mt-1 text-gray-900">
                                    <?php if ($offer['exchange_type'] == 'with_money'): ?>
                                        $<?= number_format($offer['expected_amount'], 2) ?>
                                        <?php if ($offer['expected_plant']): ?>
                                            or <?= htmlspecialchars($offer['expected_plant']) ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <?= htmlspecialchars($offer['expected_plant'] ?? 'Any plant') ?>
                                    <?php endif; ?>
                                </p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Location</label>
                                <p class="mt-1 text-gray-900"><?= htmlspecialchars($offer['location']) ?></p>
                            </div>
                            <?php if ($offer['latitude'] && $offer['longitude']): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Coordinates</label>
                                    <p class="mt-1 text-gray-900"><?= $offer['latitude'] ?>, <?= $offer['longitude'] ?></p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Right Column - User Info and Actions -->
                <div class="space-y-6">
                    <!-- User Information -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Offerer Information</h2>
                        <div class="flex items-center mb-4">
                            <img src="<?= htmlspecialchars($offer['user_image'] ?? 'default-user.png') ?>" 
                                 alt="<?= htmlspecialchars($offer['firstname']) ?>" 
                                 class="user-image rounded-full mr-4">
                            <div>
                                <h3 class="font-semibold text-gray-900"><?= htmlspecialchars($offer['firstname'] . ' ' . $offer['lastname']) ?></h3>
                                <p class="text-sm text-gray-500">Offer Creator</p>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <p class="text-sm"><i class="fas fa-envelope mr-2 text-gray-400"></i> <?= htmlspecialchars($offer['user_email']) ?></p>
                            <?php if ($offer['phone']): ?>
                                <p class="text-sm"><i class="fas fa-phone mr-2 text-gray-400"></i> <?= htmlspecialchars($offer['phone']) ?></p>
                            <?php endif; ?>
                            <p class="text-sm"><i class="fas fa-map-marker-alt mr-2 text-gray-400"></i> <?= htmlspecialchars($offer['location']) ?></p>
                        </div>
                    </div>

                    <!-- Status Information -->
                    <div class="bg-white rounded-lg shadow p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Status Information</h2>
                        <div class="space-y-3">
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Current Status</label>
                                <p class="mt-1">
                                    <span class="px-3 py-1 text-sm rounded-full 
                                        <?= $offer['status'] == 'approved' ? 'bg-green-100 text-green-800' : 
                                           ($offer['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 
                                           ($offer['status'] == 'completed' ? 'bg-purple-100 text-purple-800' : 'bg-red-100 text-red-800')) ?>">
                                        <?= ucfirst($offer['status']) ?>
                                    </span>
                                </p>
                            </div>
                            <?php if ($offer['admin_name'] && $offer['status'] != 'pending'): ?>
                                <div>
                                    <label class="block text-sm font-medium text-gray-700">Processed By</label>
                                    <p class="mt-1 text-gray-900"><?= htmlspecialchars($offer['admin_name']) ?></p>
                                </div>
                            <?php endif; ?>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Created On</label>
                                <p class="mt-1 text-gray-900"><?= date('M j, Y \a\t g:i a', strtotime($offer['created_at'])) ?></p>
                            </div>
                            <div>
                                <label class="block text-sm font-medium text-gray-700">Last Updated</label>
                                <p class="mt-1 text-gray-900"><?= date('M j, Y \a\t g:i a', strtotime($offer['updated_at'])) ?></p>
                            </div>
                        </div>
                    </div>

                    <!-- Mark as Completed Section -->
                    <?php if ($offer['status'] == 'approved'): ?>
                        <div class="bg-white rounded-lg shadow p-6">
                            <h2 class="text-xl font-semibold text-gray-800 mb-4">Complete Exchange</h2>
                            <?php 
                                $accepted_interests = array_filter($interests, function($interest) {
                                    return $interest['status'] == 'accepted';
                                });
                            ?>
                            
                            <?php if (count($accepted_interests) > 0): ?>
                                <form method="post">
                                    <input type="hidden" name="mark_completed" value="1">
                                    <div class="mb-4">
                                        <label class="block text-sm font-medium text-gray-700 mb-2">
                                            Select Final Interest to Complete
                                        </label>
                                        <select name="final_interest_id" required class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-green-500 focus:border-green-500">
                                            <option value="">Select an accepted interest</option>
                                            <?php foreach ($accepted_interests as $interest): ?>
                                                <option value="<?= $interest['id'] ?>">
                                                    <?= htmlspecialchars($interest['firstname'] . ' ' . $interest['lastname']) ?>
                                                    <?php if ($interest['offered_plant']): ?>
                                                        - <?= htmlspecialchars($interest['offered_plant']) ?>
                                                    <?php endif; ?>
                                                    <?php if ($interest['offered_amount']): ?>
                                                        - $<?= number_format($interest['offered_amount'], 2) ?>
                                                    <?php endif; ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <p class="text-sm text-gray-600 mb-4">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        This will mark the exchange as completed and send confirmation emails to both parties.
                                    </p>
                                    <button type="submit" class="w-full bg-purple-600 text-white py-2 px-4 rounded-md hover:bg-purple-700 transition">
                                        <i class="fas fa-check-circle mr-2"></i> Mark as Completed
                                    </button>
                                </form>
                            <?php else: ?>
                                <div class="text-center py-4">
                                    <i class="fas fa-info-circle text-3xl text-gray-400 mb-2"></i>
                                    <p class="text-gray-600">No accepted interests yet. Cannot mark as completed.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Interests Section -->
            <div class="mt-8 bg-white rounded-lg shadow">
                <div class="p-6 border-b border-gray-200">
                    <h2 class="text-2xl font-semibold text-gray-800">Interests (<?= count($interests) ?>)</h2>
                    <p class="text-gray-600 mt-1">All interests expressed for this exchange offer</p>
                </div>
                
                <div class="p-6">
                    <?php if (empty($interests)): ?>
                        <div class="text-center py-8">
                            <i class="fas fa-handshake text-4xl text-gray-300 mb-3"></i>
                            <p class="text-gray-500">No interests expressed yet.</p>
                        </div>
                    <?php else: ?>
                        <div class="space-y-4">
                            <?php foreach ($interests as $interest): ?>
                                <div class="interest-card <?= $interest['status'] ?> bg-white border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-3">
                                        <div class="flex items-center">
                                            <img src="<?= htmlspecialchars($interest['profile_pic'] ?? 'default-user.png') ?>" 
                                                 alt="<?= htmlspecialchars($interest['firstname']) ?>" 
                                                 class="user-image rounded-full mr-3">
                                            <div>
                                                <h4 class="font-semibold text-gray-900"><?= htmlspecialchars($interest['firstname'] . ' ' . $interest['lastname']) ?></h4>
                                                <p class="text-sm text-gray-500">
                                                    <?= date('M j, Y \a\t g:i a', strtotime($interest['created_at'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                        <span class="px-3 py-1 text-sm rounded-full 
                                            <?= $interest['status'] == 'accepted' ? 'bg-green-100 text-green-800' : 
                                               ($interest['status'] == 'pending' ? 'bg-yellow-100 text-yellow-800' : 'bg-red-100 text-red-800') ?>">
                                            <?= ucfirst($interest['status']) ?>
                                        </span>
                                    </div>
                                    
                                    <div class="ml-14">
                                        <!-- Contact Information -->
                                        <div class="mb-3 p-3 bg-blue-50 rounded">
                                            <h5 class="font-medium text-blue-800 mb-1">Contact Information:</h5>
                                            <p class="text-sm"><i class="fas fa-envelope mr-2"></i> <?= htmlspecialchars($interest['email']) ?></p>
                                            <?php if ($interest['phone']): ?>
                                                <p class="text-sm mt-1"><i class="fas fa-phone mr-2"></i> <?= htmlspecialchars($interest['phone']) ?></p>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Offer Details -->
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-3">
                                            <?php if ($interest['offer_type'] == 'plant' || $interest['offer_type'] == 'both'): ?>
                                                <div>
                                                    <span class="font-medium text-gray-700">Offered Plant:</span>
                                                    <p class="text-gray-900"><?= htmlspecialchars($interest['offered_plant']) ?></p>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <?php if ($interest['offer_type'] == 'money' || $interest['offer_type'] == 'both'): ?>
                                                <div>
                                                    <span class="font-medium text-gray-700">Offered Amount:</span>
                                                    <p class="text-gray-900">$<?= number_format($interest['offered_amount'], 2) ?></p>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <?php if ($interest['message']): ?>
                                            <div class="mb-3">
                                                <span class="font-medium text-gray-700">Message:</span>
                                                <p class="text-gray-900 mt-1"><?= htmlspecialchars($interest['message']) ?></p>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Change main image when thumbnail is clicked
    function changeMainImage(src, element) {
        document.getElementById('mainImage').src = src;
        document.querySelectorAll('.thumbnail').forEach(thumb => {
            thumb.classList.remove('active');
        });
        element.classList.add('active');
    }
</script>
</body>
</html>