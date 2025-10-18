<?php
require_once 'db_connect.php';
session_start();

// Redirect if not logged in
if (!isset($_SESSION['user_id'])) {
    $_SESSION['error_msg'] = "Please login to create an exchange offer";
    header("Location: login.php");
    exit();
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $user_id = $_SESSION['user_id'];
    $title = trim($_POST['title']);
    $description = trim($_POST['description']);
    $plant_name = trim($_POST['plant_name']);
    $plant_type = trim($_POST['plant_type']);
    $plant_age = trim($_POST['plant_age']);
    $plant_health = $_POST['plant_health'];
    $exchange_type = $_POST['exchange_type'];
    $expected_amount = ($exchange_type == 'with_money') ? floatval($_POST['expected_amount']) : NULL;
    $expected_plant = trim($_POST['expected_plant']);
    $location = trim($_POST['location']);
    $latitude = $_POST['latitude'] ?: NULL;
    $longitude = $_POST['longitude'] ?: NULL;

    // Handle image upload
    $images = [];
    if (!empty($_FILES['images']['name'][0])) {
        $uploadDir = 'uploads/exchange/';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        foreach ($_FILES['images']['tmp_name'] as $key => $tmpName) {
            if ($_FILES['images']['error'][$key] === UPLOAD_ERR_OK) {
                $fileName = uniqid() . '_' . basename($_FILES['images']['name'][$key]);
                $targetPath = $uploadDir . $fileName;

                if (move_uploaded_file($tmpName, $targetPath)) {
                    $images[] = $targetPath;
                }
            }
        }
    }

    // Insert into database
    $stmt = $conn->prepare("INSERT INTO exchange_offers (
        user_id, title, description, plant_name, plant_type, plant_age, plant_health, 
        images, exchange_type, expected_amount, expected_plant, location, 
        latitude, longitude, status
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending')");

    $imagesStr = !empty($images) ? implode(',', $images) : NULL;

    $stmt->bind_param(
        "issssssssdssdd",
        $user_id,
        $title,
        $description,
        $plant_name,
        $plant_type,
        $plant_age,
        $plant_health,
        $imagesStr,
        $exchange_type,
        $expected_amount,
        $expected_plant,
        $location,
        $latitude,
        $longitude
    );

    if ($stmt->execute()) {
        $_SESSION['success_msg'] = "Exchange offer submitted successfully! It will be visible after admin approval.";
        header("Location: exchange.php");
        exit();
    } else {
        $_SESSION['error_msg'] = "Error creating exchange offer: " . $conn->error;
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create Exchange Offer | Green Legacy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <style>
        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 10px;
        }

        .image-preview-item {
            position: relative;
            width: 100px;
            height: 100px;
        }

        .image-preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            border-radius: 4px;
        }

        .remove-image {
            position: absolute;
            top: -5px;
            right: -5px;
            background: red;
            color: white;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 12px;
        }

        .exchange-type-btn {
            transition: all 0.3s ease;
        }

        .exchange-type-btn.active {
            background-color: #2E8B57;
            color: white;
        }
    </style>
</head>

<body class="bg-gray-50">
    <?php include 'navbar.php'; ?>

    <main class="container mx-auto mt-8 px-4 py-8">
        <div class="max-w-3xl mx-auto bg-white rounded-lg shadow-md overflow-hidden">
            <div class="bg-green-600 text-white px-6 py-4">
                <h1 class="text-2xl font-bold">Create Exchange Offer</h1>
                <p class="text-green-100">Share your plant with the community</p>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3">
                    <?= $_SESSION['error_msg'];
                    unset($_SESSION['error_msg']); ?>
                </div>
            <?php endif; ?>

            <form action="create_exchange.php" method="POST" enctype="multipart/form-data" class="p-6">
                <!-- Basic Information -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Plant Information</h2>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="plant_name" class="block text-gray-700 mb-2">Plant Name*</label>
                            <input type="text" id="plant_name" name="plant_name" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                        </div>

                        <div>
                            <label for="plant_type" class="block text-gray-700 mb-2">Plant Type*</label>
                            <input type="text" id="plant_type" name="plant_type" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                        </div>
                    </div>

                    <div class="mt-4">
                        <label for="title" class="block text-gray-700 mb-2">Offer Title*</label>
                        <input type="text" id="title" name="title" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                            placeholder="e.g. Healthy Monstera Deliciosa for exchange">
                    </div>

                    <div class="mt-4">
                        <label for="description" class="block text-gray-700 mb-2">Description*</label>
                        <textarea id="description" name="description" rows="4" required
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                            placeholder="Tell us about your plant, its care requirements, why you're exchanging it, etc."></textarea>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label for="plant_age" class="block text-gray-700 mb-2">Plant Age</label>
                            <input type="text" id="plant_age" name="plant_age"
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                                placeholder="e.g. 2 years">
                        </div>

                        <div>
                            <label for="plant_health" class="block text-gray-700 mb-2">Plant Health*</label>
                            <select id="plant_health" name="plant_health" required
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                                <option value="excellent">Excellent</option>
                                <option value="good" selected>Good</option>
                                <option value="average">Average</option>
                                <option value="poor">Poor</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Images -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Plant Images</h2>
                    <p class="text-gray-600 mb-2">Upload clear photos of your plant (max 5 images)</p>

                    <div class="border-2 border-dashed border-gray-300 rounded-lg p-4 text-center">
                        <input type="file" id="images" name="images[]" multiple accept="image/*"
                            class="hidden" onchange="previewImages(this)">
                        <label for="images" class="cursor-pointer">
                            <i class="fas fa-camera text-3xl text-gray-400 mb-2"></i>
                            <p class="text-gray-500">Click to upload or drag and drop</p>
                            <p class="text-sm text-gray-400">PNG, JPG up to 5MB each</p>
                        </label>
                    </div>

                    <div id="imagePreview" class="image-preview"></div>
                </div>

                <!-- Exchange Type -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Exchange Type*</h2>

                    <div class="flex flex-wrap gap-4 mb-4">
                        <label class="exchange-type-btn px-6 py-3 border border-green-500 rounded-lg cursor-pointer">
                            <input type="radio" name="exchange_type" value="without_money" checked
                                class="hidden" onchange="toggleExchangeType()">
                            <i class="fas fa-exchange-alt mr-2"></i> Plant Exchange
                        </label>

                        <label class="exchange-type-btn px-6 py-3 border border-green-500 rounded-lg cursor-pointer">
                            <input type="radio" name="exchange_type" value="with_money"
                                class="hidden" onchange="toggleExchangeType()">
                            <i class="fas fa-money-bill-wave mr-2"></i> With Money
                        </label>
                    </div>

                    <div id="expectedPlantField" class="mt-4">
                        <label for="expected_plant" class="block text-gray-700 mb-2">Looking for (plant name)</label>
                        <input type="text" id="expected_plant" name="expected_plant"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                            placeholder="e.g. Snake Plant or any low-maintenance plant">
                    </div>

                    <div id="expectedAmountField" class="mt-4 hidden">
                        <label for="expected_amount" class="block text-gray-700 mb-2">Expected Amount ($)</label>
                        <input type="number" id="expected_amount" name="expected_amount" min="0" step="0.01"
                            class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                            placeholder="0.00">
                    </div>
                </div>

                <!-- Location -->
                <div class="mb-6">
                    <h2 class="text-xl font-semibold text-gray-800 mb-4">Location*</h2>

                    <div class="mb-4">
                        <label for="location" class="block text-gray-700 mb-2">Your Location*</label>
                        <div class="flex gap-2">
                            <input type="text" id="location" name="location" required
                                class="flex-1 px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                                placeholder="e.g. Bangalore, Karnataka">
                            <button type="button" onclick="getLocation()" class="bg-green-500 text-white px-4 py-2 rounded-lg hover:bg-green-600 transition">
                                <i class="fas fa-location-arrow mr-1"></i> Current
                            </button>
                        </div>
                    </div>

                    <!-- Map Interface -->
                    <div class="mb-4">
                        <label class="block text-gray-700 mb-2">Select Location on Map</label>
                        <div id="map" class="w-full h-64 rounded-lg border border-gray-300"></div>
                        <p class="text-sm text-gray-500 mt-2">
                            <i class="fas fa-info-circle mr-1"></i> Click on the map to set your precise location
                        </p>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="latitude" class="block text-gray-700 mb-2">Latitude</label>
                            <input type="number" id="latitude" name="latitude" step="any" readonly
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
                        </div>

                        <div>
                            <label for="longitude" class="block text-gray-700 mb-2">Longitude</label>
                            <input type="number" id="longitude" name="longitude" step="any" readonly
                                class="w-full px-4 py-2 border border-gray-300 rounded-lg bg-gray-50">
                        </div>
                    </div>
                </div>

                <!-- Submit Button -->
                <div class="mt-8">
                    <button type="submit" class="w-full bg-green-600 text-white py-3 px-6 rounded-lg hover:bg-green-700 transition font-semibold">
                        <i class="fas fa-paper-plane mr-2"></i> Submit Offer
                    </button>
                </div>
            </form>
        </div>
    </main>

    <?php include 'footer.php'; ?>

    <script>
        // Toggle exchange type fields
        function toggleExchangeType() {
            const exchangeType = document.querySelector('input[name="exchange_type"]:checked').value;
            const expectedPlantField = document.getElementById('expectedPlantField');
            const expectedAmountField = document.getElementById('expectedAmountField');
            const exchangeTypeBtns = document.querySelectorAll('.exchange-type-btn');

            // Update button styles
            exchangeTypeBtns.forEach(btn => {
                const input = btn.querySelector('input');
                if (input.value === exchangeType) {
                    btn.classList.add('active', 'bg-green-600', 'text-white');
                    btn.classList.remove('text-green-600');
                } else {
                    btn.classList.remove('active', 'bg-green-600', 'text-white');
                    btn.classList.add('text-green-600');
                }
            });

            // Toggle fields
            if (exchangeType === 'with_money') {
                expectedPlantField.style.display = 'block';
                expectedAmountField.classList.remove('hidden');
                document.getElementById('expected_plant').placeholder = "e.g. Snake Plant (optional)";
            } else {
                expectedPlantField.style.display = 'block';
                expectedAmountField.classList.add('hidden');
                document.getElementById('expected_plant').placeholder = "e.g. Snake Plant or any low-maintenance plant";
            }
        }

        // Initialize exchange type buttons
        document.addEventListener('DOMContentLoaded', function() {
            toggleExchangeType();
        });

        // Image preview functionality
        function previewImages(input) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';

            if (input.files) {
                const files = Array.from(input.files).slice(0, 5); // Limit to 5 images

                files.forEach(file => {
                    const reader = new FileReader();

                    reader.onload = function(e) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'image-preview-item';

                        const img = document.createElement('img');
                        img.src = e.target.result;
                        img.title = file.name;

                        const removeBtn = document.createElement('span');
                        removeBtn.className = 'remove-image';
                        removeBtn.innerHTML = '&times;';
                        removeBtn.onclick = function() {
                            previewItem.remove();
                            updateFileInput(input, files, file);
                        };

                        previewItem.appendChild(img);
                        previewItem.appendChild(removeBtn);
                        preview.appendChild(previewItem);
                    }

                    reader.readAsDataURL(file);
                });
            }
        }

        // Update file input when removing preview images
        function updateFileInput(input, files, fileToRemove) {
            const newFiles = Array.from(input.files).filter(f => f !== fileToRemove);

            // Create new DataTransfer to update files
            const dataTransfer = new DataTransfer();
            newFiles.forEach(file => dataTransfer.items.add(file));

            // Update files in input
            input.files = dataTransfer.files;
        }

        // Get current location
        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        document.getElementById('latitude').value = position.coords.latitude;
                        document.getElementById('longitude').value = position.coords.longitude;

                        // Reverse geocode to get address
                        fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${position.coords.latitude}&lon=${position.coords.longitude}`)
                            .then(response => response.json())
                            .then(data => {
                                if (data.address) {
                                    const address = [
                                        data.address.road,
                                        data.address.suburb,
                                        data.address.city,
                                        data.address.state,
                                        data.address.country
                                    ].filter(Boolean).join(', ');
                                    document.getElementById('location').value = address;
                                }
                            });
                    },
                    function(error) {
                        console.error("Error getting location:", error);
                    }
                );
            } else {
                alert("Geolocation is not supported by this browser.");
            }
        }

        // Map variables
        let map;
        let marker;
        let selectedLocation = false;

        // Initialize map
        function initMap() {
            // Default center (you can change this to your preferred default location)
            const defaultCenter = [12.9716, 77.5946]; // Bangalore coordinates

            map = L.map('map').setView(defaultCenter, 10);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '© OpenStreetMap contributors'
            }).addTo(map);

            // Add click event to map
            map.on('click', function(e) {
                setMapLocation(e.latlng.lat, e.latlng.lng);
                reverseGeocode(e.latlng.lat, e.latlng.lng);
            });

            // Try to get user's current location
            getLocation();
        }

        // Set location on map
        function setMapLocation(lat, lng) {
            // Remove existing marker
            if (marker) {
                map.removeLayer(marker);
            }

            // Add new marker
            marker = L.marker([lat, lng]).addTo(map)
                .bindPopup('Selected Location')
                .openPopup();

            // Update form fields
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;

            // Center map on selected location
            map.setView([lat, lng], 13);

            selectedLocation = true;
        }

        // Reverse geocode coordinates to address
        function reverseGeocode(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    if (data.address) {
                        const address = [
                            data.address.road,
                            data.address.suburb,
                            data.address.city,
                            data.address.state,
                            data.address.country
                        ].filter(Boolean).join(', ');
                        document.getElementById('location').value = address;
                    }
                })
                .catch(error => {
                    console.error("Error reverse geocoding:", error);
                });
        }

        // Get current location
        function getLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        setMapLocation(lat, lng);
                        reverseGeocode(lat, lng);
                    },
                    function(error) {
                        console.error("Error getting location:", error);
                        // If geolocation fails, just initialize map with default center
                        if (!selectedLocation) {
                            setMapLocation(12.9716, 77.5946);
                        }
                    }
                );
            } else {
                alert("Geolocation is not supported by this browser.");
                // Initialize map with default center if geolocation not supported
                if (!selectedLocation) {
                    setMapLocation(12.9716, 77.5946);
                }
            }
        }

        // Initialize exchange type buttons and map
        document.addEventListener('DOMContentLoaded', function() {
            toggleExchangeType();
            initMap();
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const latitude = document.getElementById('latitude').value;
            const longitude = document.getElementById('longitude').value;

            if (!latitude || !longitude) {
                e.preventDefault();
                alert('Please select your location on the map by clicking on your desired location.');
                return false;
            }
        });
    </script>
</body>
</html>