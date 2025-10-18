<?php
require_once 'db_connect.php';
require_once 'navbar.php';

// Get all approved exchange offers
$query = "SELECT e.*, u.firstname, u.lastname, u.profile_pic 
          FROM exchange_offers e
          JOIN users u ON e.user_id = u.id
          WHERE e.status = 'approved'
          ORDER BY e.created_at DESC";
$result = $conn->query($query);
$offers = $result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Plant Exchange | Green Legacy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .exchange-card {
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        .exchange-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(46, 204, 113, 0.1);
        }
        .plant-image {
            height: 200px;
            object-fit: cover;
            width: 100%;
        }
        .user-image {
            width: 40px;
            height: 40px;
            object-fit: cover;
        }
    </style>
</head>
<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>
    
    <main class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mt-8 mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Plant Exchange</h1>
            <a href="create_exchange.php" class="px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                <i class="fas fa-plus mr-2"></i> Create Offer
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
        
        <!-- Exchange Offers Grid -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
            <?php if (empty($offers)): ?>
                <div class="col-span-full text-center py-12">
                    <i class="fas fa-seedling text-4xl text-gray-300 mb-4"></i>
                    <p class="text-gray-500">No exchange offers available at the moment.</p>
                    <a href="create_exchange.php" class="text-green-600 hover:underline mt-2 inline-block">
                        Be the first to create an offer!
                    </a>
                </div>
            <?php else: ?>
                <?php foreach ($offers as $offer): ?>
                    <div class="exchange-card bg-white rounded-lg overflow-hidden border border-gray-100">
                        <!-- Offer Images -->
                        <div class="relative">
                            <?php if ($offer['images']): ?>
                                <?php 
                                    $images = explode(',', $offer['images']);
                                    $firstImage = $images[0];
                                ?>
                                <img src="<?= htmlspecialchars($firstImage) ?>" alt="<?= htmlspecialchars($offer['plant_name']) ?>" class="plant-image">
                            <?php else: ?>
                                <div class="plant-image bg-gray-100 flex items-center justify-center">
                                    <i class="fas fa-seedling text-4xl text-gray-300"></i>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Exchange Type Badge -->
                            <div class="absolute top-2 right-2 px-3 py-1 rounded-full text-xs font-semibold 
                                <?= $offer['exchange_type'] == 'with_money' ? 'bg-yellow-100 text-yellow-800' : 'bg-green-100 text-green-800' ?>">
                                <?= $offer['exchange_type'] == 'with_money' ? 'With Money' : 'Free Exchange' ?>
                            </div>
                        </div>
                        
                        <!-- Offer Details -->
                        <div class="p-4">
                            <div class="flex items-start justify-between">
                                <div>
                                    <h3 class="text-xl font-semibold text-gray-800 mb-1"><?= htmlspecialchars($offer['plant_name']) ?></h3>
                                    <p class="text-sm text-gray-500 mb-2"><?= htmlspecialchars($offer['plant_type']) ?></p>
                                </div>
                                <div class="flex items-center">
                                    <img src="<?= htmlspecialchars($offer['profile_pic'] ?? 'default-user.png') ?>" 
                                         alt="<?= htmlspecialchars($offer['firstname']) ?>" 
                                         class="user-image rounded-full mr-2">
                                    <span class="text-sm"><?= htmlspecialchars($offer['firstname']) ?></span>
                                </div>
                            </div>
                            
                            <p class="text-gray-600 my-3 line-clamp-2"><?= htmlspecialchars($offer['description']) ?></p>
                            
                            <div class="flex items-center text-sm text-gray-500 mb-3">
                                <i class="fas fa-map-marker-alt mr-2"></i>
                                <span><?= htmlspecialchars($offer['location']) ?></span>
                            </div>
                            
                            <div class="flex flex-wrap gap-2 mb-4">
                                <span class="px-2 py-1 bg-gray-100 rounded text-xs">
                                    <i class="fas fa-leaf mr-1"></i> <?= ucfirst($offer['plant_health']) ?> condition
                                </span>
                                <?php if ($offer['plant_age']): ?>
                                    <span class="px-2 py-1 bg-gray-100 rounded text-xs">
                                        <i class="fas fa-calendar mr-1"></i> <?= htmlspecialchars($offer['plant_age']) ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                            
                            <!-- Exchange Details -->
                            <div class="border-t pt-3">
                                <p class="font-medium text-sm mb-2">
                                    Looking for:
                                    <?php if ($offer['exchange_type'] == 'with_money'): ?>
                                        <span class="text-green-600">$<?= number_format($offer['expected_amount'], 2) ?></span>
                                        <?php if ($offer['expected_plant']): ?>
                                            <span class="text-gray-500">or <?= htmlspecialchars($offer['expected_plant']) ?></span>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-green-600"><?= htmlspecialchars($offer['expected_plant'] ?? 'Any plant') ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="border-t px-4 py-3 bg-gray-50">
                            <a href="exchange_detail.php?id=<?= $offer['id'] ?>" class="block text-center px-4 py-2 bg-green-600 text-white rounded hover:bg-green-700 transition">
                                View Details
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </main>
    
    <?php include 'footer.php'; ?>
</body>
</html>