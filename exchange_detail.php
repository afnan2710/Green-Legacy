<?php
require_once 'db_connect.php';
require_once 'navbar.php';
// Check if exchange ID is provided
if (!isset($_GET['id'])) {
    header("Location: exchange.php");
    exit();
}

$exchange_id = intval($_GET['id']);

// Get the exchange offer details
$query = "SELECT e.*, u.firstname, u.lastname, u.profile_pic, u.email, u.phone 
          FROM exchange_offers e
          JOIN users u ON e.user_id = u.id
          WHERE e.id = ? AND e.status = 'approved'";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $exchange_id);
$stmt->execute();
$result = $stmt->get_result();
$offer = $result->fetch_assoc();

if (!$offer) {
    $_SESSION['error_msg'] = "Exchange offer not found or not approved.";
    echo '<script>window.location.href = "exchange.php";</script>';
    exit();
}

// Check if the current user is the owner of the offer
$is_owner = (isset($_SESSION['user_id']) && $_SESSION['user_id'] == $offer['user_id']);

// Handle interest submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['submit_interest']) && !$is_owner) {
    $user_id = $_SESSION['user_id'];
    $offer_type = $_POST['offer_type'];
    $offered_plant = !empty($_POST['offered_plant']) ? $_POST['offered_plant'] : null;
    $offered_amount = !empty($_POST['offered_amount']) ? floatval($_POST['offered_amount']) : null;
    $message = !empty($_POST['message']) ? $_POST['message'] : null;

    // Validate based on offer type
    if ($offer_type == 'plant' && empty($offered_plant)) {
        $_SESSION['error_msg'] = "Please specify the plant you're offering.";
    } elseif ($offer_type == 'money' && empty($offered_amount)) {
        $_SESSION['error_msg'] = "Please specify the amount you're offering.";
    } elseif ($offer_type == 'both' && (empty($offered_plant) || empty($offered_amount))) {
        $_SESSION['error_msg'] = "Please specify both plant and amount you're offering.";
    } else {
        // Insert interest
        $query = "INSERT INTO exchange_interests 
                  (exchange_id, interested_user_id, offer_type, offered_plant, offered_amount, message)
                  VALUES (?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("iissds", $exchange_id, $user_id, $offer_type, $offered_plant, $offered_amount, $message);

        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Your interest has been submitted successfully!";
            echo '<script>window.location.href = "exchange_detail.php?id=' . $exchange_id . '";</script>';
            exit();
        } else {
            $_SESSION['error_msg'] = "Error submitting your interest. Please try again.";
        }
    }
}

// Get existing interests for this offer (only if user is owner)
$interests = [];
if ($is_owner) {
    $query = "SELECT ei.*, u.firstname, u.lastname, u.profile_pic, u.email, u.phone 
              FROM exchange_interests ei
              JOIN users u ON ei.interested_user_id = u.id
              WHERE ei.exchange_id = ?
              ORDER BY ei.created_at DESC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("i", $exchange_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $interests = $result->fetch_all(MYSQLI_ASSOC);
}

// Check if current user has already expressed interest (removed this check to allow multiple interests)
$has_expressed_interest = false; // Always false to allow multiple interests
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($offer['plant_name']) ?> Exchange | Green Legacy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .plant-image {
            height: 610px;
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

        .thumbnail:hover,
        .thumbnail.active {
            border-color: #10B981;
        }

        .user-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
        }

        .tab {
            cursor: pointer;
            padding: 0.5rem 1rem;
            border-bottom: 2px solid transparent;
        }

        .tab.active {
            border-color: #10B981;
            color: #10B981;
            font-weight: 600;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>

    <main class="container mx-auto mt-8 px-4 py-8">
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= $_SESSION['success_msg'];
                unset($_SESSION['success_msg']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= $_SESSION['error_msg'];
                unset($_SESSION['error_msg']); ?>
            </div>
        <?php endif; ?>

        <!-- Back Button -->
        <a href="exchange.php" class="inline-flex items-center text-green-600 hover:text-green-800 mb-6">
            <i class="fas fa-arrow-left mr-2"></i> Back to all exchanges
        </a>

        <!-- Exchange Offer Details -->
        <div class="bg-white rounded-lg shadow-md overflow-hidden">
            <!-- Image Gallery -->
            <div class="flex flex-col md:flex-row">
                <div class="md:w-2/3">
                    <?php if ($offer['images']): ?>
                        <?php
                        $images = explode(',', $offer['images']);
                        $mainImage = $images[0];
                        ?>
                        <img id="mainImage" src="<?= htmlspecialchars($mainImage) ?>" alt="<?= htmlspecialchars($offer['plant_name']) ?>" class="plant-image">
                    <?php else: ?>
                        <div class="plant-image bg-gray-100 flex items-center justify-center">
                            <i class="fas fa-seedling text-6xl text-gray-300"></i>
                        </div>
                    <?php endif; ?>
                </div>

                <div class="md:w-1/3 p-6 border-l border-gray-100">
                    <!-- Offer Title and User -->
                    <div class="flex items-center justify-between mb-4">
                        <h1 class="text-2xl font-bold text-gray-800"><?= htmlspecialchars($offer['plant_name']) ?></h1>
                        <div class="flex items-center">
                            <img src="<?= htmlspecialchars($offer['profile_pic'] ?? 'default-user.png') ?>"
                                alt="<?= htmlspecialchars($offer['firstname']) ?>"
                                class="user-image rounded-full mr-2">
                            <span><?= htmlspecialchars($offer['firstname'] . ' ' . $offer['lastname']) ?></span>
                        </div>
                    </div>

                    <!-- Plant Details -->
                    <div class="mb-6">
                        <div class="flex items-center text-sm text-gray-500 mb-2">
                            <i class="fas fa-seedling mr-2 w-5"></i>
                            <span><?= htmlspecialchars($offer['plant_type']) ?></span>
                        </div>
                        <div class="flex items-center text-sm text-gray-500 mb-2">
                            <i class="fas fa-heartbeat mr-2 w-5"></i>
                            <span><?= ucfirst($offer['plant_health']) ?> condition</span>
                        </div>
                        <?php if ($offer['plant_age']): ?>
                            <div class="flex items-center text-sm text-gray-500 mb-2">
                                <i class="fas fa-calendar mr-2 w-5"></i>
                                <span><?= htmlspecialchars($offer['plant_age']) ?> old</span>
                            </div>
                        <?php endif; ?>
                        <div class="flex items-center text-sm text-gray-500 mb-2">
                            <i class="fas fa-map-marker-alt mr-2 w-5"></i>
                            <span><?= htmlspecialchars($offer['location']) ?></span>
                        </div>
                        <?php if ($offer['latitude'] && $offer['longitude']): ?>
                            <div class="mt-4">
                                <a href="https://www.google.com/maps?q=<?= $offer['latitude'] ?>,<?= $offer['longitude'] ?>"
                                    target="_blank"
                                    class="text-green-600 hover:text-green-800 text-sm flex items-center">
                                    <i class="fas fa-map-marked-alt mr-2"></i> View on map
                                </a>
                            </div>
                        <?php endif; ?>

                    </div>

                    <!-- Exchange Type Badge -->
                    <div class="mb-6">
                        <span class="px-3 py-1 rounded-full text-sm font-semibold 
                            <?= $offer['exchange_type'] == 'with_money' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?>">
                            <?= $offer['exchange_type'] == 'with_money' ? 'With Money' : 'Free Exchange' ?>
                        </span>
                    </div>

                    <!-- Exchange Details -->
                    <div class="bg-gray-50 p-4 rounded-lg mb-6">
                        <h3 class="font-semibold text-gray-800 mb-2">Looking for:</h3>
                        <?php if ($offer['exchange_type'] == 'with_money'): ?>
                            <p class="text-green-600 font-medium">
                                $<?= number_format($offer['expected_amount'], 2) ?>
                                <?php if ($offer['expected_plant']): ?>
                                    <span class="text-gray-600">or</span> <?= htmlspecialchars($offer['expected_plant']) ?>
                                <?php endif; ?>
                            </p>
                        <?php else: ?>
                            <p class="text-green-600 font-medium">
                                <?= htmlspecialchars($offer['expected_plant'] ?? 'Any plant') ?>
                            </p>
                        <?php endif; ?>
                    </div>
                    <?php if (!$is_owner): ?>
                        <div class="border-t pt-4 mb-6">
                            <h3 class="font-semibold text-gray-800 mb-2">Offerer Contact Info:</h3>
                            <p class="text-sm"><i class="fas fa-envelope mr-2"></i> <?= htmlspecialchars($offer['email']) ?></p>
                            <?php if ($offer['phone']): ?>
                                <p class="text-sm mt-1"><i class="fas fa-phone mr-2"></i> <?= htmlspecialchars($offer['phone']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Contact Info (only for owner) -->
                    <?php if ($is_owner): ?>
                        <div class="border-t pt-4 mb-6">
                            <h3 class="font-semibold text-gray-800 mb-2">My Contact Info:</h3>
                            <p class="text-sm"><i class="fas fa-envelope mr-2"></i> <?= htmlspecialchars($offer['email']) ?></p>
                            <?php if ($offer['phone']): ?>
                                <p class="text-sm mt-1"><i class="fas fa-phone mr-2"></i> <?= htmlspecialchars($offer['phone']) ?></p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Action Buttons -->
                    <div class="mt-6">
                        <?php if ($is_owner): ?>
                            <a href="edit_exchange.php?id=<?= $offer['id'] ?>" class="block text-center px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700 transition mb-2">
                                <i class="fas fa-edit mr-2"></i> Edit Offer
                            </a>
                            <button onclick="confirmDelete()" class="w-full px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700 transition">
                                <i class="fas fa-trash mr-2"></i> Delete Offer
                            </button>
                        <?php elseif (isset($_SESSION['user_id'])): ?>
                            <!-- Always show Express Interest button - users can submit multiple interests -->
                            <button onclick="document.getElementById('interestModal').classList.remove('hidden')"
                                class="w-full px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
                                <i class="fas fa-handshake mr-2"></i> Express Interest
                            </button>
                        <?php else: ?>
                            <a href="login.php?redirect=exchange_detail.php?id=<?= $offer['id'] ?>"
                                class="block text-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
                                <i class="fas fa-sign-in-alt mr-2"></i> Login to Express Interest
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Thumbnail Gallery (if multiple images) -->
            <?php if ($offer['images'] && count($images) > 1): ?>
                <div class="flex flex-wrap gap-2 p-4 border-t">
                    <?php foreach ($images as $index => $image): ?>
                        <img src="<?= htmlspecialchars($image) ?>"
                            alt="Thumbnail <?= $index + 1 ?>"
                            class="thumbnail <?= $index === 0 ? 'active' : '' ?>"
                            onclick="changeMainImage('<?= htmlspecialchars($image) ?>', this)">
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Description Section -->
            <div class="p-6 border-t border-gray-100">
                <h2 class="text-xl font-semibold text-gray-800 mb-4">Description</h2>
                <p class="text-gray-700 whitespace-pre-line"><?= htmlspecialchars($offer['description']) ?></p>
            </div>

            <!-- Interests Section (only for owner) -->
            <?php if ($is_owner && !empty($interests)): ?>
                <div class="border-t border-gray-100">
                    <div class="flex border-b">
                        <div class="tab active" onclick="switchTab('interests')">Interests (<?= count($interests) ?>)</div>
                    </div>

                    <div id="interestsTab" class="p-6">
                        <h2 class="text-xl font-semibold text-gray-800 mb-4">Interests from Other Users</h2>

                        <div class="space-y-4">
                            <?php foreach ($interests as $interest): ?>
                                <div class="border border-gray-200 rounded-lg p-4">
                                    <div class="flex items-center justify-between mb-2">
                                        <div class="flex items-center">
                                            <img src="<?= htmlspecialchars($interest['profile_pic'] ?? 'default-user.png') ?>"
                                                alt="<?= htmlspecialchars($interest['firstname']) ?>"
                                                class="user-image rounded-full mr-3">
                                            <div>
                                                <h4 class="font-medium"><?= htmlspecialchars($interest['firstname'] . ' ' . $interest['lastname']) ?></h4>
                                                <p class="text-sm text-gray-500">
                                                    <?= date('M j, Y \a\t g:i a', strtotime($interest['created_at'])) ?>
                                                </p>
                                            </div>
                                        </div>
                                        <span class="px-2 py-1 text-xs rounded 
                                <?= $interest['status'] == 'accepted' ? 'bg-green-100 text-green-800' : ($interest['status'] == 'rejected' ? 'bg-red-100 text-red-800' : 'bg-gray-100 text-gray-800') ?>">
                                            <?= ucfirst($interest['status']) ?>
                                        </span>
                                    </div>

                                    <div class="ml-14">
                                        <!-- Contact Information for interested user -->
                                        <div class="mb-3 p-3 bg-blue-50 rounded">
                                            <h5 class="font-medium text-blue-800 mb-1">Contact Information:</h5>
                                            <p class="text-sm"><i class="fas fa-envelope mr-2"></i> <?= htmlspecialchars($interest['email']) ?></p>
                                            <?php if ($interest['phone']): ?>
                                                <p class="text-sm mt-1"><i class="fas fa-phone mr-2"></i> <?= htmlspecialchars($interest['phone']) ?></p>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($interest['offer_type'] == 'plant' || $interest['offer_type'] == 'both'): ?>
                                            <p class="mb-1">
                                                <span class="font-medium">Offered Plant:</span>
                                                <?= htmlspecialchars($interest['offered_plant']) ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if ($interest['offer_type'] == 'money' || $interest['offer_type'] == 'both'): ?>
                                            <p class="mb-1">
                                                <span class="font-medium">Offered Amount:</span>
                                                $<?= number_format($interest['offered_amount'], 2) ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if ($interest['message']): ?>
                                            <p class="mb-1">
                                                <span class="font-medium">Message:</span>
                                                <?= htmlspecialchars($interest['message']) ?>
                                            </p>
                                        <?php endif; ?>

                                        <?php if ($interest['status'] == 'pending'): ?>
                                            <div class="flex space-x-2 mt-3">
                                                <form method="post" action="process_interest.php">
                                                    <input type="hidden" name="interest_id" value="<?= $interest['id'] ?>">
                                                    <input type="hidden" name="action" value="accept">
                                                    <button type="submit" class="px-3 py-1 bg-green-600 text-white text-sm rounded hover:bg-green-700">
                                                        Accept
                                                    </button>
                                                </form>
                                                <form method="post" action="process_interest.php">
                                                    <input type="hidden" name="interest_id" value="<?= $interest['id'] ?>">
                                                    <input type="hidden" name="action" value="reject">
                                                    <button type="submit" class="px-3 py-1 bg-red-600 text-white text-sm rounded hover:bg-red-700">
                                                        Reject
                                                    </button>
                                                </form>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </main>

    <!-- Interest Modal -->
    <div id="interestModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">Express Interest</h3>
                    <button onclick="document.getElementById('interestModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <form method="post">
                    <input type="hidden" name="submit_interest" value="1">

                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">What are you offering?</label>
                        <select name="offer_type" id="offerType" class="w-full px-3 py-2 border rounded" onchange="updateOfferFields()" required>
                            <option value="">Select an option</option>
                            <?php if ($offer['exchange_type'] == 'with_money'): ?>
                                <option value="money">Money Only</option>
                                <option value="plant">Plant Only</option>
                                <option value="both">Both Money and Plant</option>
                            <?php else: ?>
                                <option value="plant">Plant</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div id="plantField" class="mb-4 hidden">
                        <label class="block text-gray-700 mb-2">Plant you're offering</label>
                        <input type="text" name="offered_plant" class="w-full px-3 py-2 border rounded" placeholder="e.g. Monstera Deliciosa">
                    </div>

                    <div id="amountField" class="mb-4 hidden">
                        <label class="block text-gray-700 mb-2">Amount you're offering ($)</label>
                        <input type="number" name="offered_amount" step="0.01" min="0" class="w-full px-3 py-2 border rounded" placeholder="e.g. 20.00">
                    </div>

                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Message (optional)</label>
                        <textarea name="message" rows="3" class="w-full px-3 py-2 border rounded" placeholder="Add a personal message..."></textarea>
                    </div>

                    <div class="flex justify-end space-x-3">
                        <button type="button" onclick="document.getElementById('interestModal').classList.add('hidden')" class="px-4 py-2 border rounded text-gray-700 hover:bg-gray-100">
                            Cancel
                        </button>
                        <button type="submit" class="px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700">
                            Submit Interest
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div id="deleteModal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 flex items-center justify-center p-4 z-50">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-md">
            <div class="p-6">
                <div class="flex justify-between items-center mb-4">
                    <h3 class="text-xl font-semibold text-gray-800">Confirm Deletion</h3>
                    <button onclick="document.getElementById('deleteModal').classList.add('hidden')" class="text-gray-500 hover:text-gray-700">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <p class="mb-6">Are you sure you want to delete this exchange offer? This action cannot be undone.</p>

                <div class="flex justify-end space-x-3">
                    <button onclick="document.getElementById('deleteModal').classList.add('hidden')" class="px-4 py-2 border rounded text-gray-700 hover:bg-gray-100">
                        Cancel
                    </button>
                    <a href="delete_exchange.php?id=<?= $offer['id'] ?>" class="px-4 py-2 bg-red-600 text-white rounded hover:bg-red-700">
                        Delete Offer
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Change main image when thumbnail is clicked
        function changeMainImage(src, element) {
            document.getElementById('mainImage').src = src;
            document.querySelectorAll('.thumbnail').forEach(thumb => {
                thumb.classList.remove('active');
            });
            element.classList.add('active');
        }

        // Switch between tabs
        function switchTab(tabName) {
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelector(`.tab[onclick="switchTab('${tabName}')"]`).classList.add('active');

            // Hide all tab contents and show the selected one
            document.getElementById(`${tabName}Tab`).classList.remove('hidden');
        }

        // Update offer fields based on selected type
        function updateOfferFields() {
            const offerType = document.getElementById('offerType').value;
            const plantField = document.getElementById('plantField');
            const amountField = document.getElementById('amountField');

            plantField.classList.add('hidden');
            amountField.classList.add('hidden');

            if (offerType === 'plant' || offerType === 'both') {
                plantField.classList.remove('hidden');
            }

            if (offerType === 'money' || offerType === 'both') {
                amountField.classList.remove('hidden');
            }
        }

        // Show delete confirmation modal
        function confirmDelete() {
            document.getElementById('deleteModal').classList.remove('hidden');
        }
    </script>
</body>

</html>