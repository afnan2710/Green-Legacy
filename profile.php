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
$error = '';
$success = '';

// Fetch user data
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();

if (!$user) {
    header("Location: logout.php");
    exit();
}

// Handle profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $firstname = trim($_POST['firstname']);
    $lastname = trim($_POST['lastname']);
    $dob = $_POST['dob'];
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    
    // Validate inputs
    if (empty($firstname) || empty($lastname) || empty($dob) || empty($phone)) {
        $error = "Please fill in all required fields.";
    } else {
        // Handle profile picture upload
        $profile_pic = $user['profile_pic'];
        if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] === UPLOAD_ERR_OK) {
            $upload_dir = 'uploads/profile_pics/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_ext = pathinfo($_FILES['profile_pic']['name'], PATHINFO_EXTENSION);
            $filename = 'user_' . $user_id . '_' . time() . '.' . $file_ext;
            $target_file = $upload_dir . $filename;
            
            // Check if image file is valid
            $check = getimagesize($_FILES['profile_pic']['tmp_name']);
            if ($check !== false) {
                if (move_uploaded_file($_FILES['profile_pic']['tmp_name'], $target_file)) {
                    // Delete old profile pic if it exists and isn't the default
                    if ($profile_pic && $profile_pic !== 'default-user.png' && file_exists($profile_pic)) {
                        unlink($profile_pic);
                    }
                    $profile_pic = $target_file;
                } else {
                    $error = "Sorry, there was an error uploading your file.";
                }
            } else {
                $error = "File is not an image.";
            }
        }
        
        if (empty($error)) {
            $stmt = $conn->prepare("UPDATE users SET firstname=?, lastname=?, dob=?, address=?, phone=?, profile_pic=? WHERE id=?");
            $stmt->bind_param("ssssssi", $firstname, $lastname, $dob, $address, $phone, $profile_pic, $user_id);
            
            if ($stmt->execute()) {
                $_SESSION['user_name'] = $firstname . ' ' . $lastname;
                $_SESSION['profile_pic'] = $profile_pic;
                $success = "Profile updated successfully!";
                // Refresh user data
                $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
                $stmt->bind_param("i", $user_id);
                $stmt->execute();
                $result = $stmt->get_result();
                $user = $result->fetch_assoc();
            } else {
                $error = "Error updating profile: " . $conn->error;
            }
        }
    }
}

// Handle password change
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {
    $current_password = $_POST['current_password'];
    $new_password = $_POST['new_password'];
    $confirm_password = $_POST['confirm_password'];
    
    if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
        $error = "All password fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error = "New password and confirmation password don't match.";
    } elseif (strlen($new_password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif (!password_verify($current_password, $user['password'])) {
        $error = "Current password is incorrect.";
    } else {
        // Password strength validation
        $uppercase = preg_match('@[A-Z]@', $new_password);
        $lowercase = preg_match('@[a-z]@', $new_password);
        $number = preg_match('@[0-9]@', $new_password);
        $specialChars = preg_match('@[^\w]@', $new_password);
        
        if (!$uppercase || !$lowercase || !$number || !$specialChars) {
            $error = "Password should include at least one uppercase letter, one lowercase letter, one number, and one special character.";
        } else {
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $conn->prepare("UPDATE users SET password=? WHERE id=?");
            $stmt->bind_param("si", $hashed_password, $user_id);
            
            if ($stmt->execute()) {
                $success = "Password changed successfully!";
            } else {
                $error = "Error changing password: " . $conn->error;
            }
        }
    }
}

// Handle location update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_location'])) {
    $latitude = $_POST['latitude'];
    $longitude = $_POST['longitude'];
    
    if (!empty($latitude) && !empty($longitude)) {
        $stmt = $conn->prepare("UPDATE users SET latitude=?, longitude=?, location_updated_at=NOW() WHERE id=?");
        $stmt->bind_param("ddi", $latitude, $longitude, $user_id);
        
        if ($stmt->execute()) {
            $success = "Location updated successfully! We'll use this to suggest plants suitable for your area.";
            // Refresh user data to show updated location
            $stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $result = $stmt->get_result();
            $user = $result->fetch_assoc();
        } else {
            $error = "Error updating location: " . $conn->error;
        }
    } else {
        $error = "Location data is required.";
    }
}
?>

<?php include 'navbar.php'; ?>

<div class="container mx-auto px-4 py-8">
    <div class="max-w-6xl mx-auto">
        <h1 class="text-3xl font-bold text-gray-800 mt-10 mb-6">My Profile</h1>
        
        <?php if ($error): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <div class="flex flex-col md:flex-row gap-8">
            <!-- Profile Update Section -->
            <div class="w-full md:w-2/3 bg-white rounded-lg shadow-md p-6">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Personal Information</h2>
                
                <form method="POST" enctype="multipart/form-data">
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="profile_pic">Profile Picture</label>
                        <div class="flex items-center space-x-4">
                            <img src="<?php echo htmlspecialchars($user['profile_pic'] ?? 'default-user.png'); ?>" 
                                 alt="Profile Picture" 
                                 class="w-20 h-20 rounded-full object-cover border-2 border-gray-200">
                            <input type="file" name="profile_pic" id="profile_pic" 
                                   class="text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-50 file:text-green-700 hover:file:bg-green-100">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="firstname">First Name</label>
                            <input type="text" name="firstname" id="firstname" 
                                   value="<?php echo htmlspecialchars($user['firstname']); ?>" 
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="lastname">Last Name</label>
                            <input type="text" name="lastname" id="lastname" 
                                   value="<?php echo htmlspecialchars($user['lastname']); ?>" 
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="email">Email</label>
                            <input type="email" id="email" 
                                   value="<?php echo htmlspecialchars($user['email']); ?>" 
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 bg-gray-100 leading-tight" disabled>
                        </div>
                        <div>
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="phone">Phone</label>
                            <input type="text" name="phone" id="phone" 
                                   value="<?php echo htmlspecialchars($user['phone']); ?>" 
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                    </div>
                    
                    <div class="mb-4">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="dob">Date of Birth</label>
                        <input type="date" name="dob" id="dob" 
                               value="<?php echo htmlspecialchars($user['dob']); ?>" 
                               class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                    </div>
                    
                    <div class="mb-6">
                        <label class="block text-gray-700 text-sm font-bold mb-2" for="address">Address</label>
                        <textarea name="address" id="address" rows="3" 
                                  class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline"><?php echo htmlspecialchars($user['address']); ?></textarea>
                    </div>
                    
                    <button type="submit" name="update_profile" 
                            class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                        Update Profile
                    </button>
                </form>
                
                <!-- Password Change Section -->
                <div class="mt-8 pt-6 border-t border-gray-200">
                    <h2 class="text-xl font-semibold text-gray-700 mb-4">Change Password</h2>
                    
                    <form method="POST" id="password-form">
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="current_password">Current Password</label>
                            <input type="password" name="current_password" id="current_password" 
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                        </div>
                        
                        <div class="mb-4">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="new_password">New Password</label>
                            <input type="password" name="new_password" id="new_password" 
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <div class="mt-2">
                                <div id="password-strength-bar" class="h-2 bg-gray-200 rounded overflow-hidden">
                                    <div id="password-strength" class="h-full transition-all duration-300" style="width: 0%"></div>
                                </div>
                                <p id="password-strength-text" class="text-xs text-gray-500 mt-1">Password strength: <span id="strength-text">Very Weak</span></p>
                                <ul id="password-requirements" class="text-xs text-gray-500 mt-1 list-disc list-inside">
                                    <li id="req-length">At least 8 characters</li>
                                    <li id="req-uppercase">1 uppercase letter</li>
                                    <li id="req-lowercase">1 lowercase letter</li>
                                    <li id="req-number">1 number</li>
                                    <li id="req-special">1 special character</li>
                                </ul>
                            </div>
                        </div>
                        
                        <div class="mb-6">
                            <label class="block text-gray-700 text-sm font-bold mb-2" for="confirm_password">Confirm New Password</label>
                            <input type="password" name="confirm_password" id="confirm_password" 
                                   class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                            <div id="password-match" class="text-xs mt-1 hidden"></div>
                        </div>
                        
                        <button type="submit" name="change_password" 
                                class="bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                            Change Password
                        </button>
                    </form>
                </div>
            </div>
            
            <!-- Location Section -->
            <div class="w-full md:w-1/3 bg-white rounded-lg shadow-md p-6">
    <h2 class="text-xl font-semibold text-gray-700 mb-4">Location Settings</h2>
    <p class="text-gray-600 mb-4">Update your location to get personalized plant recommendations based on your local climate and weather.</p>
    
    <div id="location-container">
        <?php if ($user['latitude'] && $user['longitude']): ?>
            <div class="mb-4 p-4 bg-green-50 rounded-lg">
                <p class="text-green-700 font-medium">Current Location:</p>
                <p class="text-gray-700">Latitude: <?php echo $user['latitude']; ?></p>
                <p class="text-gray-700">Longitude: <?php echo $user['longitude']; ?></p>
                <p class="text-gray-500 text-sm">Last updated: <?php echo $user['location_updated_at'] ? date('M j, Y g:i a', strtotime($user['location_updated_at'])) : 'Never'; ?></p>
            </div>
        <?php else: ?>
            <div class="mb-4 p-4 bg-yellow-50 rounded-lg">
                <p class="text-yellow-700">No location data saved yet.</p>
            </div>
        <?php endif; ?>
        
        <div id="map-container" class="h-64 mb-4 rounded-lg overflow-hidden <?php echo ($user['latitude'] && $user['longitude']) ? '' : 'hidden'; ?>">
            <div id="map" class="h-full w-full"></div>
        </div>
        
        <div class="flex space-x-2 mb-4">
            <button id="get-location-btn" 
                    class="flex-1 bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-location-arrow mr-2"></i>Detect My Location
            </button>
            <button id="manual-location-btn" 
                    class="flex-1 bg-purple-500 hover:bg-purple-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline">
                <i class="fas fa-map-marker-alt mr-2"></i>Set Manually
            </button>
        </div>
        
        <form method="POST" id="location-form">
            <div class="grid grid-cols-2 gap-2 mb-4 hidden" id="manual-coords">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1" for="manual-latitude">Latitude</label>
                    <input type="text" id="manual-latitude" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-1" for="manual-longitude">Longitude</label>
                    <input type="text" id="manual-longitude" 
                           class="shadow appearance-none border rounded w-full py-2 px-3 text-gray-700 leading-tight focus:outline-none focus:shadow-outline">
                </div>
            </div>
            
            <input type="hidden" name="latitude" id="latitude" value="<?php echo $user['latitude'] ?? ''; ?>">
            <input type="hidden" name="longitude" id="longitude" value="<?php echo $user['longitude'] ?? ''; ?>">
            
            <button type="submit" name="update_location" id="save-location-btn" 
                    class="w-full bg-green-500 hover:bg-green-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:shadow-outline disabled:bg-gray-300"
                    <?php echo ($user['latitude'] && $user['longitude']) ? '' : 'disabled'; ?>>
                <i class="fas fa-save mr-2"></i>Save Location
            </button>
        </form>
        
        <div id="location-error" class="text-red-500 text-sm mt-2 hidden"></div>
    </div>
    
    <div class="mt-6">
        <h3 class="font-medium text-gray-700 mb-2">Privacy Note</h3>
        <p class="text-sm text-gray-500">Your location data is only used to provide personalized plant recommendations and will not be shared with third parties.</p>
    </div>
</div>



<!-- Include Leaflet CSS and JS for maps -->
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.7.1/dist/leaflet.css" />
<script src="https://unpkg.com/leaflet@1.7.1/dist/leaflet.js"></script>
<!-- Include Font Awesome for icons -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" />

<script>
document.addEventListener('DOMContentLoaded', function() {
    const getLocationBtn = document.getElementById('get-location-btn');
    const manualLocationBtn = document.getElementById('manual-location-btn');
    const saveLocationBtn = document.getElementById('save-location-btn');
    const latitudeInput = document.getElementById('latitude');
    const longitudeInput = document.getElementById('longitude');
    const manualLatitudeInput = document.getElementById('manual-latitude');
    const manualLongitudeInput = document.getElementById('manual-longitude');
    const manualCoordsDiv = document.getElementById('manual-coords');
    const locationError = document.getElementById('location-error');
    const mapContainer = document.getElementById('map-container');
    let map;
    let marker;
    let isManualMode = false;
    
    // Initialize map if location is already set
    <?php if ($user['latitude'] && $user['longitude']): ?>
        initMap(<?php echo $user['latitude']; ?>, <?php echo $user['longitude']; ?>);
        mapContainer.classList.remove('hidden');
    <?php endif; ?>
    
    getLocationBtn.addEventListener('click', function() {
        if (navigator.geolocation) {
            getLocationBtn.disabled = true;
            getLocationBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Detecting...';
            locationError.classList.add('hidden');
            
            navigator.geolocation.getCurrentPosition(
                function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    updateLocation(lat, lng);
                    getLocationBtn.innerHTML = '<i class="fas fa-check-circle mr-2"></i>Location Detected!';
                    getLocationBtn.classList.remove('bg-blue-500', 'hover:bg-blue-600');
                    getLocationBtn.classList.add('bg-green-500', 'hover:bg-green-600');
                    
                    // Initialize or update map
                    if (!map) {
                        initMap(lat, lng);
                        mapContainer.classList.remove('hidden');
                    } else {
                        map.setView([lat, lng], 13);
                        marker.setLatLng([lat, lng]);
                    }
                    
                    // Disable manual mode if active
                    if (isManualMode) {
                        disableManualMode();
                    }
                },
                function(error) {
                    getLocationBtn.disabled = false;
                    getLocationBtn.innerHTML = '<i class="fas fa-location-arrow mr-2"></i>Detect My Location';
                    
                    let errorMessage = 'Error getting location: ';
                    switch(error.code) {
                        case error.PERMISSION_DENIED:
                            errorMessage += "You denied the request for Geolocation.";
                            break;
                        case error.POSITION_UNAVAILABLE:
                            errorMessage += "Location information is unavailable.";
                            break;
                        case error.TIMEOUT:
                            errorMessage += "The request to get location timed out.";
                            break;
                        case error.UNKNOWN_ERROR:
                            errorMessage += "An unknown error occurred.";
                            break;
                    }
                    
                    locationError.textContent = errorMessage;
                    locationError.classList.remove('hidden');
                }
            );
        } else {
            locationError.textContent = "Geolocation is not supported by this browser.";
            locationError.classList.remove('hidden');
        }
    });
    
    manualLocationBtn.addEventListener('click', function() {
        if (isManualMode) {
            disableManualMode();
        } else {
            enableManualMode();
        }
    });
    
    function enableManualMode() {
        isManualMode = true;
        manualCoordsDiv.classList.remove('hidden');
        manualLocationBtn.innerHTML = '<i class="fas fa-times mr-2"></i>Cancel';
        manualLocationBtn.classList.remove('bg-purple-500', 'hover:bg-purple-600');
        manualLocationBtn.classList.add('bg-red-500', 'hover:bg-red-600');
        
        // Initialize map if not already initialized
        if (!map) {
            const defaultLat = <?php echo $user['latitude'] ?? '0'; ?>;
            const defaultLng = <?php echo $user['longitude'] ?? '0'; ?>;
            initMap(defaultLat || 0, defaultLng || 0);
            mapContainer.classList.remove('hidden');
        }
        
        // Center on current location or default to world view
        if (latitudeInput.value && longitudeInput.value) {
            map.setView([latitudeInput.value, longitudeInput.value], 13);
        } else {
            map.setView([20, 0], 2);
        }
        
        // Update manual input fields
        manualLatitudeInput.value = latitudeInput.value || '';
        manualLongitudeInput.value = longitudeInput.value || '';
        
        // Enable marker dragging
        if (marker) {
            marker.draggable = true;
            marker.on('dragend', updateFromMarker);
        } else {
            // Add new draggable marker
            const center = map.getCenter();
            marker = L.marker([center.lat, center.lng], {
                draggable: true
            }).addTo(map);
            marker.on('dragend', updateFromMarker);
        }
        
        // Add click event to map
        map.on('click', updateFromMapClick);
    }
    
    function disableManualMode() {
        isManualMode = false;
        manualCoordsDiv.classList.add('hidden');
        manualLocationBtn.innerHTML = '<i class="fas fa-map-marker-alt mr-2"></i>Set Manually';
        manualLocationBtn.classList.remove('bg-red-500', 'hover:bg-red-600');
        manualLocationBtn.classList.add('bg-purple-500', 'hover:bg-purple-600');
        
        // Remove map click event
        map.off('click', updateFromMapClick);
        
        // Reset marker to not be draggable if we have a location
        if (marker && latitudeInput.value && longitudeInput.value) {
            marker.draggable = false;
            marker.off('dragend', updateFromMarker);
        }
    }
    
    function updateFromMarker(e) {
        const newLatLng = marker.getLatLng();
        updateLocation(newLatLng.lat, newLatLng.lng);
    }
    
    function updateFromMapClick(e) {
        const latLng = e.latlng;
        updateLocation(latLng.lat, latLng.lng);
        
        // Move marker to clicked location
        if (marker) {
            marker.setLatLng(latLng);
        } else {
            marker = L.marker(latLng, {
                draggable: true
            }).addTo(map);
            marker.on('dragend', updateFromMarker);
        }
    }
    
    function updateLocation(lat, lng) {
        latitudeInput.value = lat;
        longitudeInput.value = lng;
        manualLatitudeInput.value = lat;
        manualLongitudeInput.value = lng;
        saveLocationBtn.disabled = false;
    }
    
    function initMap(lat, lng) {
        map = L.map('map').setView([lat, lng], 13);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
        }).addTo(map);
        
        marker = L.marker([lat, lng], {
            draggable: false
        }).addTo(map);
    }
    
    // Manual coordinate input handling
    manualLatitudeInput.addEventListener('change', function() {
        if (isManualMode && manualLatitudeInput.value && manualLongitudeInput.value) {
            const lat = parseFloat(manualLatitudeInput.value);
            const lng = parseFloat(manualLongitudeInput.value);
            
            if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                updateLocation(lat, lng);
                
                // Update map view
                map.setView([lat, lng], 13);
                
                // Update marker position
                if (marker) {
                    marker.setLatLng([lat, lng]);
                } else {
                    marker = L.marker([lat, lng], {
                        draggable: true
                    }).addTo(map);
                    marker.on('dragend', updateFromMarker);
                }
                
                locationError.classList.add('hidden');
            } else {
                locationError.textContent = "Please enter valid coordinates (Latitude: -90 to 90, Longitude: -180 to 180)";
                locationError.classList.remove('hidden');
            }
        }
    });
    
    manualLongitudeInput.addEventListener('change', function() {
        if (isManualMode && manualLatitudeInput.value && manualLongitudeInput.value) {
            const lat = parseFloat(manualLatitudeInput.value);
            const lng = parseFloat(manualLongitudeInput.value);
            
            if (!isNaN(lat) && !isNaN(lng) && lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                updateLocation(lat, lng);
                
                // Update map view
                map.setView([lat, lng], 13);
                
                // Update marker position
                if (marker) {
                    marker.setLatLng([lat, lng]);
                } else {
                    marker = L.marker([lat, lng], {
                        draggable: true
                    }).addTo(map);
                    marker.on('dragend', updateFromMarker);
                }
                
                locationError.classList.add('hidden');
            } else {
                locationError.textContent = "Please enter valid coordinates (Latitude: -90 to 90, Longitude: -180 to 180)";
                locationError.classList.remove('hidden');
            }
        }
    });
    
    // Password strength checker
    const passwordInput = document.getElementById('new_password');
    const passwordStrength = document.getElementById('password-strength');
    const strengthText = document.getElementById('strength-text');
    const confirmPasswordInput = document.getElementById('confirm_password');
    const passwordMatch = document.getElementById('password-match');
    
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        let strength = 0;
        
        // Check length
        if (password.length >= 8) {
            strength += 1;
            document.getElementById('req-length').classList.add('text-green-500');
            document.getElementById('req-length').classList.remove('text-gray-500');
        } else {
            document.getElementById('req-length').classList.remove('text-green-500');
            document.getElementById('req-length').classList.add('text-gray-500');
        }
        
        // Check uppercase
        if (/[A-Z]/.test(password)) {
            strength += 1;
            document.getElementById('req-uppercase').classList.add('text-green-500');
            document.getElementById('req-uppercase').classList.remove('text-gray-500');
        } else {
            document.getElementById('req-uppercase').classList.remove('text-green-500');
            document.getElementById('req-uppercase').classList.add('text-gray-500');
        }
        
        // Check lowercase
        if (/[a-z]/.test(password)) {
            strength += 1;
            document.getElementById('req-lowercase').classList.add('text-green-500');
            document.getElementById('req-lowercase').classList.remove('text-gray-500');
        } else {
            document.getElementById('req-lowercase').classList.remove('text-green-500');
            document.getElementById('req-lowercase').classList.add('text-gray-500');
        }
        
        // Check number
        if (/[0-9]/.test(password)) {
            strength += 1;
            document.getElementById('req-number').classList.add('text-green-500');
            document.getElementById('req-number').classList.remove('text-gray-500');
        } else {
            document.getElementById('req-number').classList.remove('text-green-500');
            document.getElementById('req-number').classList.add('text-gray-500');
        }
        
        // Check special character
        if (/[^A-Za-z0-9]/.test(password)) {
            strength += 1;
            document.getElementById('req-special').classList.add('text-green-500');
            document.getElementById('req-special').classList.remove('text-gray-500');
        } else {
            document.getElementById('req-special').classList.remove('text-green-500');
            document.getElementById('req-special').classList.add('text-gray-500');
        }
        
        // Update strength bar and text
        const strengthPercent = (strength / 5) * 100;
        passwordStrength.style.width = strengthPercent + '%';
        
        if (strengthPercent < 40) {
            passwordStrength.className = 'h-full transition-all duration-300 bg-red-500';
            strengthText.textContent = 'Very Weak';
        } else if (strengthPercent < 60) {
            passwordStrength.className = 'h-full transition-all duration-300 bg-yellow-500';
            strengthText.textContent = 'Weak';
        } else if (strengthPercent < 80) {
            passwordStrength.className = 'h-full transition-all duration-300 bg-blue-500';
            strengthText.textContent = 'Good';
        } else {
            passwordStrength.className = 'h-full transition-all duration-300 bg-green-500';
            strengthText.textContent = 'Strong';
        }
    });
    
    // Password match checker
    confirmPasswordInput.addEventListener('input', function() {
        const password = passwordInput.value;
        const confirmPassword = this.value;
        
        if (confirmPassword.length === 0) {
            passwordMatch.classList.add('hidden');
        } else if (password === confirmPassword) {
            passwordMatch.textContent = 'Passwords match!';
            passwordMatch.className = 'text-xs mt-1 text-green-500';
            passwordMatch.classList.remove('hidden');
        } else {
            passwordMatch.textContent = 'Passwords do not match!';
            passwordMatch.className = 'text-xs mt-1 text-red-500';
            passwordMatch.classList.remove('hidden');
        }
    });
});
</script>

