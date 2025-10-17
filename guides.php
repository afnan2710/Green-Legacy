<?php
require_once 'db_connect.php';
include 'navbar.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    echo "<script>window.location.href='login.php';</script>";
    exit();
}

$user_id = $_SESSION['user_id'];

// Fetch user data including location
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

// Default recommendations (will be overridden if location is available)
$season = 'year-round';
$climate_zone = 'temperate';
$weather_tips = 'Moderate climate suitable for most plants';
$recommended_plants = [];
$gardening_tasks = [];

// Get weather and climate data if location is available
if ($user['latitude'] && $user['longitude']) {
    // Determine season based on current month and hemisphere
    $current_month = date('n');
    $is_northern = $user['latitude'] > 0; // Northern hemisphere

    if ($is_northern) {
        if ($current_month >= 3 && $current_month <= 5) {
            $season = 'spring';
        } elseif ($current_month >= 6 && $current_month <= 8) {
            $season = 'summer';
        } elseif ($current_month >= 9 && $current_month <= 11) {
            $season = 'autumn';
        } else {
            $season = 'winter';
        }
    } else {
        // Southern hemisphere seasons are opposite
        if ($current_month >= 3 && $current_month <= 5) {
            $season = 'autumn';
        } elseif ($current_month >= 6 && $current_month <= 8) {
            $season = 'winter';
        } elseif ($current_month >= 9 && $current_month <= 11) {
            $season = 'spring';
        } else {
            $season = 'summer';
        }
    }

    // Determine climate zone based on latitude
    $abs_latitude = abs($user['latitude']);
    if ($abs_latitude < 23.5) {
        $climate_zone = 'tropical';
        $weather_tips = 'Warm and humid year-round. Focus on heat-tolerant plants and regular watering.';
    } elseif ($abs_latitude < 35) {
        $climate_zone = 'subtropical';
        $weather_tips = 'Mild winters and hot summers. Great for a wide variety of plants.';
    } elseif ($abs_latitude < 50) {
        $climate_zone = 'temperate';
        $weather_tips = 'Four distinct seasons. Rotate plants according to the season.';
    } elseif ($abs_latitude < 66.5) {
        $climate_zone = 'continental';
        $weather_tips = 'Cold winters and warm summers. Choose cold-hardy varieties.';
    } else {
        $climate_zone = 'polar';
        $weather_tips = 'Short growing season. Focus on cold-resistant plants and indoor gardening.';
    }

    // Get recommended plants for this climate and season
    $stmt = $conn->prepare("
        SELECT p.id, p.name, p.description, 
            COALESCE(pi.image_path, 'default-plant.jpg') as image_path 
        FROM products p 
        LEFT JOIN product_images pi ON p.id = pi.product_id AND pi.is_primary = 1
        WHERE p.product_type = 'plant'
        AND (p.climate_zone LIKE ? OR p.climate_zone = 'all')
        AND (p.season LIKE ? OR p.season = 'all')
    ");
    $climate_param = "%$climate_zone%";
    $season_param = "%$season%";
    $stmt->bind_param("ss", $climate_param, $season_param);
    $stmt->execute();
    $recommended_plants = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

    // Get seasonal gardening tasks
    $gardening_tasks = getSeasonalTasks($season, $climate_zone);
}


function getDiseaseRisks($conn, $climate_zone, $season, $plant_types = ['plant'])
{
    $placeholders = implode(',', array_fill(0, count($plant_types), '?'));
    $types = str_repeat('s', count($plant_types));

    $stmt = $conn->prepare("
        SELECT * FROM plant_diseases 
        WHERE (FIND_IN_SET(?, climate_zones) > 0 OR climate_zones = 'all')
        AND (FIND_IN_SET(?, seasons) > 0 OR seasons = 'all')
        AND (FIND_IN_SET(?, plant_types) > 0 OR plant_types = 'all')
        ORDER BY FIELD(risk_level, 'high', 'medium', 'low')
    ");

    $params = array_merge([$climate_zone, $season], $plant_types);
    $stmt->bind_param(str_repeat('s', 2 + count($plant_types)), ...$params);

    $stmt->execute();
    return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
}

function getSeasonalTasks($season, $climate_zone)
{
    $tasks = [];

    if ($season == 'spring') {
        $tasks = [
            'Prepare garden beds by removing weeds and adding compost',
            'Start seeds indoors for summer vegetables',
            'Plant cool-season crops like lettuce and peas',
            'Prune winter-damaged branches from trees and shrubs',
            'Divide and transplant perennials'
        ];
    } elseif ($season == 'summer') {
        $tasks = [
            'Water plants deeply in the early morning',
            'Mulch to conserve moisture and suppress weeds',
            'Harvest vegetables regularly to encourage production',
            'Deadhead flowers to promote more blooms',
            'Watch for pests and treat organically when possible'
        ];
    } elseif ($season == 'autumn') {
        $tasks = [
            'Plant spring-flowering bulbs',
            'Rake and compost fallen leaves',
            'Protect tender plants from early frosts',
            'Plant cover crops to enrich soil',
            'Clean and store garden tools properly'
        ];
    } else { // winter
        if ($climate_zone == 'tropical' || $climate_zone == 'subtropical') {
            $tasks = [
                'Continue planting and harvesting warm-season crops',
                'Protect plants from occasional cold snaps',
                'Prune fruit trees during dormancy',
                'Plan next season\'s garden layout',
                'Maintain irrigation systems'
            ];
        } else {
            $tasks = [
                'Protect plants with mulch or covers',
                'Plan next season\'s garden',
                'Start seeds indoors for early spring planting',
                'Prune dormant trees and shrubs',
                'Maintain and repair garden tools'
            ];
        }
    }

    return $tasks;
}
?>

<div class="container mx-auto mt-8 px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">Personalized Gardening Guide</h1>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                Your customized gardening recommendations based on <?php echo $user['latitude'] && $user['longitude'] ? 'your location' : 'general best practices'; ?>
            </p>
        </div>

        <?php if (!$user['latitude'] || !$user['longitude']): ?>
            <div class="bg-yellow-50 border-l-4 border-yellow-400 p-4 mb-8">
                <div class="flex">
                    <div class="flex-shrink-0">
                        <svg class="h-5 w-5 text-yellow-400" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                        </svg>
                    </div>
                    <div class="ml-3">
                        <p class="text-sm text-yellow-700">
                            You haven't set your location yet. <a href="profile.php" class="font-medium underline text-yellow-700 hover:text-yellow-600">Set your location</a> to get personalized recommendations based on your local climate and season.
                        </p>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <!-- Weather and Climate Info -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-3 rounded-full bg-blue-100 text-blue-600 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-gray-500">Current Season</p>
                            <h3 class="text-xl font-bold text-gray-700 capitalize"><?php echo $season; ?></h3>
                        </div>
                    </div>
                    <p class="text-gray-600"><?php echo ucfirst($season); ?> gardening activities are ideal now.</p>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-3 rounded-full bg-green-100 text-green-600 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-gray-500">Climate Zone</p>
                            <h3 class="text-xl font-bold text-gray-700 capitalize"><?php echo $climate_zone; ?></h3>
                        </div>
                    </div>
                    <p class="text-gray-600"><?php echo $weather_tips; ?></p>
                </div>

                <div class="bg-white rounded-lg shadow-md p-6">
                    <div class="flex items-center mb-4">
                        <div class="p-3 rounded-full bg-purple-100 text-purple-600 mr-4">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 3.055A9.001 9.001 0 1020.945 13H11V3.055z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.488 9H15V3.512A9.025 9.025 0 0120.488 9z" />
                            </svg>
                        </div>
                        <div>
                            <p class="text-gray-500">Your Coordinates</p>
                            <h3 class="text-lg font-bold text-gray-700">
                                <?php echo round($user['latitude'], 4); ?>, <?php echo round($user['longitude'], 4); ?>
                            </h3>
                        </div>
                    </div>
                    <p class="text-gray-600">Location last updated: <?php echo $user['location_updated_at'] ? date('M j, Y', strtotime($user['location_updated_at'])) : 'Never'; ?></p>
                </div>
            </div>

            <!-- Map Display -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h2 class="text-2xl font-semibold text-gray-800 mb-4">Your Location</h2>
                <div id="map" class="h-64 w-full rounded-lg"></div>
            </div>
        <?php endif; ?>

        <!-- Recommended Plants -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-2xl font-semibold text-gray-800">Recommended Plants</h2>
                <a href="shop.php" class="text-green-600 hover:text-green-800">View All Plants</a>
            </div>

            <?php if (!empty($recommended_plants)): ?>
                <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <?php foreach ($recommended_plants as $plant): ?>
                        <div class="bg-gray-50 rounded-lg overflow-hidden hover:shadow-lg transition-shadow duration-300">
                            <a href="product.php?id=<?php echo $plant['id']; ?>">
                                <?php
                                // Determine the correct image path
                                $image_path = 'uploads/products/' . basename($plant['image_path']);
                                $final_path = file_exists($image_path) ? $image_path : 'images/default-plant.jpg';
                                ?>
                                <img src="<?php echo htmlspecialchars($final_path); ?>"
                                    alt="<?php echo htmlspecialchars($plant['name']); ?>"
                                    class="w-full h-48 object-cover">
                                <div class="p-4">
                                    <h3 class="font-semibold text-gray-800"><?php echo htmlspecialchars($plant['name']); ?></h3>
                                    <p class="text-gray-600 text-sm mt-1 line-clamp-2"><?php echo htmlspecialchars($plant['description']); ?></p>
                                </div>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">No specific recommendations</h3>
                    <p class="mt-1 text-gray-500">
                        <?php echo $user['latitude'] && $user['longitude'] ? 'We couldn\'t find plants specifically for your current season and climate.' : 'Set your location to get personalized plant recommendations.'; ?>
                    </p>
                    <div class="mt-6">
                        <a href="shop.php" class="inline-flex items-center px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                            Browse All Plants
                        </a>
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Seasonal Tasks -->
        <div class="bg-white rounded-xl shadow-md p-6 mb-8">
            <h2 class="text-2xl font-semibold text-gray-800 mb-6">Seasonal Gardening Tasks</h2>

            <?php if (!empty($gardening_tasks)): ?>
                <div class="space-y-4">
                    <?php foreach ($gardening_tasks as $task): ?>
                        <div class="flex items-start">
                            <div class="flex-shrink-0 mt-1">
                                <svg class="h-5 w-5 text-green-500" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div class="ml-3">
                                <p class="text-gray-700"><?php echo $task; ?></p>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-8">
                    <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.663 17h4.673M12 3v1m6.364 1.636l-.707.707M21 12h-1M4 12H3m3.343-5.657l-.707-.707m2.828 9.9a5 5 0 117.072 0l-.548.547A3.374 3.374 0 0014 18.469V19a2 2 0 11-4 0v-.531c0-.895-.356-1.754-.988-2.386l-.548-.547z"></path>
                    </svg>
                    <h3 class="mt-2 text-lg font-medium text-gray-900">General Gardening Tips</h3>
                    <p class="mt-1 text-gray-500">
                        <?php echo $user['latitude'] && $user['longitude'] ? 'No specific tasks for your current season.' : 'Set your location to get personalized gardening tasks.'; ?>
                    </p>
                </div>
            <?php endif; ?>
        </div>


        <?php if ($user['latitude'] && $user['longitude']): ?>
            <!-- Disease Risk Alerts -->
            <div class="bg-white rounded-xl shadow-md p-6 mb-8">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Disease Risk Alerts - Based on your location & season</h2>

                <?php
                $disease_risks = getDiseaseRisks($conn, $climate_zone, $season, ['plant']);
                if (!empty($disease_risks)):
                ?>
                    <div class="space-y-4">
                        <?php foreach ($disease_risks as $disease): ?>
                            <div class="border-l-4 p-4 <?php echo $disease['risk_level'] == 'high' ? 'border-red-500 bg-red-50' : ($disease['risk_level'] == 'medium' ? 'border-yellow-500 bg-yellow-50' :
                                                                'border-blue-500 bg-blue-50'); ?>">
                                <div class="flex flex-col md:flex-row gap-4">
                                    <!-- Disease Image -->
                                    <?php if (!empty($disease['image_path']) && file_exists($disease['image_path'])): ?>
                                        <div class="md:w-1/4">
                                            <img src="<?php echo htmlspecialchars($disease['image_path']); ?>"
                                                alt="<?php echo htmlspecialchars($disease['name']); ?>"
                                                class="w-60 h-60 rounded-lg object-cover">
                                        </div>
                                    <?php endif; ?>

                                    <div class="flex-1">
                                        <div class="flex justify-between items-start">
                                            <div>
                                                <h3 class="font-bold text-lg <?php echo $disease['risk_level'] == 'high' ? 'text-red-800' : ($disease['risk_level'] == 'medium' ? 'text-yellow-800' :
                                                                                        'text-blue-800'); ?>">
                                                    <?php echo htmlspecialchars($disease['name']); ?>
                                                    <span class="text-sm font-normal capitalize">(<?php echo $disease['risk_level']; ?> risk)</span>
                                                </h3>
                                                <p class="text-gray-700 mt-1"><?php echo htmlspecialchars($disease['description']); ?></p>
                                            </div>
                                            <span class="px-2 py-1 text-xs font-semibold rounded-full 
                                      <?php echo $disease['risk_level'] == 'high' ? 'bg-red-100 text-red-800' : ($disease['risk_level'] == 'medium' ? 'bg-yellow-100 text-yellow-800' :
                                                'bg-blue-100 text-blue-800'); ?>">
                                                <?php echo strtoupper($disease['risk_level']); ?>
                                            </span>
                                        </div>

                                        <div class="mt-3 grid grid-cols-1 md:grid-cols-3 gap-4">
                                            <div>
                                                <h4 class="font-semibold text-sm text-gray-600">Symptoms</h4>
                                                <p class="text-sm"><?php echo htmlspecialchars($disease['symptoms']); ?></p>
                                            </div>
                                            <div>
                                                <h4 class="font-semibold text-sm text-gray-600">Prevention</h4>
                                                <p class="text-sm"><?php echo htmlspecialchars($disease['prevention']); ?></p>
                                            </div>
                                            <div>
                                                <h4 class="font-semibold text-sm text-gray-600">Treatment</h4>
                                                <p class="text-sm"><?php echo htmlspecialchars($disease['treatment']); ?></p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-8">
                        <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                        </svg>
                        <h3 class="mt-2 text-lg font-medium text-gray-900">No significant disease risks detected</h3>
                        <p class="mt-1 text-gray-500">Current conditions don't show high risks for common plant diseases.</p>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Weather Tips -->
        <div class="bg-gradient-to-r from-green-50 to-blue-50 rounded-xl shadow-md p-6">
            <h2 class="text-2xl font-semibold text-gray-800 mb-4">Weather & Gardening Tips</h2>
            <div class="prose max-w-none text-gray-700">
                <?php if ($user['latitude'] && $user['longitude']): ?>
                    <p>Based on your location in a <strong><?php echo $climate_zone; ?></strong> climate zone during <strong><?php echo $season; ?></strong>, here are some specialized gardening tips:</p>

                    <?php if ($climate_zone == 'tropical'): ?>
                        <ul>
                            <li>Focus on heat-tolerant plants that can handle high humidity</li>
                            <li>Water deeply but less frequently to encourage deep root growth</li>
                            <li>Provide afternoon shade for delicate plants</li>
                            <li>Watch for fungal diseases in the humid conditions</li>
                            <li>Consider planting tropical fruits like mango, papaya, or banana</li>
                        </ul>
                    <?php elseif ($climate_zone == 'subtropical'): ?>
                        <ul>
                            <li>Take advantage of the long growing season with successive plantings</li>
                            <li>Protect plants from occasional frosts in winter</li>
                            <li>Mulch heavily to conserve moisture in summer</li>
                            <li>Good time to plant citrus trees and other subtropical fruits</li>
                            <li>Rotate crops to prevent soil-borne diseases</li>
                        </ul>
                    <?php elseif ($climate_zone == 'temperate'): ?>
                        <ul>
                            <li>Rotate plants according to the season</li>
                            <li>Start seeds indoors before last frost for a head start</li>
                            <li>Use cold frames to extend the growing season</li>
                            <li>Great for a wide variety of vegetables, fruits and ornamentals</li>
                            <li>Prepare for distinct seasonal changes in plant care</li>
                        </ul>
                    <?php elseif ($climate_zone == 'continental'): ?>
                        <ul>
                            <li>Choose cold-hardy varieties that can handle temperature swings</li>
                            <li>Use season extenders like row covers and greenhouses</li>
                            <li>Plant quick-maturing varieties to beat the short season</li>
                            <li>Protect plants from late spring and early fall frosts</li>
                            <li>Focus on cool-season crops in spring and fall</li>
                        </ul>
                    <?php else: // polar 
                    ?>
                        <ul>
                            <li>Grow cold-resistant plants like kale, spinach, and root vegetables</li>
                            <li>Consider indoor gardening or greenhouses</li>
                            <li>Use raised beds to help soil warm faster in spring</li>
                            <li>Take advantage of long summer daylight hours</li>
                            <li>Protect plants from harsh winds with barriers</li>
                        </ul>
                    <?php endif; ?>
                <?php else: ?>
                    <p>Without your location data, we can provide only general gardening advice:</p>
                    <ul>
                        <li>Test your soil and amend it with compost regularly</li>
                        <li>Water plants deeply but infrequently to encourage deep roots</li>
                        <li>Rotate crops each year to prevent disease buildup</li>
                        <li>Mulch around plants to conserve moisture and suppress weeds</li>
                        <li>Choose plants suited to your hardiness zone</li>
                    </ul>
                    <p class="mt-4 font-medium">Set your location in your <a href="profile.php" class="text-green-600 hover:text-green-800">profile settings</a> to get personalized recommendations.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Include Leaflet JS for maps if location is set -->
<?php if ($user['latitude'] && $user['longitude']): ?>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize map
            const map = L.map('map').setView([<?php echo $user['latitude']; ?>, <?php echo $user['longitude']; ?>], 10);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);

            // Add marker for user's location
            L.marker([<?php echo $user['latitude']; ?>, <?php echo $user['longitude']; ?>]).addTo(map)
                .bindPopup('Your location<br>Lat: <?php echo round($user['latitude'], 4); ?>, Lng: <?php echo round($user['longitude'], 4); ?>')
                .openPopup();

            // Add circle to show approximate area
            L.circle([<?php echo $user['latitude']; ?>, <?php echo $user['longitude']; ?>], {
                color: '#4ade80',
                fillColor: '#4ade80',
                fillOpacity: 0.2,
                radius: 5000
            }).addTo(map);
        });
    </script>
<?php endif; ?>

<?php include 'footer.php'; ?>