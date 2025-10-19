<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$logged_in = isset($_SESSION['logged_in']) && $_SESSION['logged_in'];
$current_page = 'disease_detection.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Disease Detection - Green Legacy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .upload-area {
            border: 2px dashed #2E8B57;
            transition: all 0.3s ease;
        }
        .upload-area:hover {
            border-color: #256845;
            background-color: #f0fff4;
        }
        .upload-area.dragover {
            border-color: #256845;
            background-color: #e6ffed;
        }
        .result-card {
            transition: all 0.3s ease;
        }
        .severity-low { background-color: #d4edda; border-color: #c3e6cb; }
        .severity-medium { background-color: #fff3cd; border-color: #ffeaa7; }
        .severity-high { background-color: #f8d7da; border-color: #f5c6cb; }
    </style>
</head>
<body>
    <?php include 'navbar.php'; ?>

    <!-- Main Content -->
    <div class="container mx-auto px-4 py-8 mt-8">
        <!-- Header -->
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-black-700 mb-4">Plant Disease Detection</h1>
            <p class="text-gray-600 text-lg max-w-2xl mx-auto">
                Upload an image of your plant leaf to detect diseases and get severity analysis. 
                Our AI model supports 7 plant types and 35+ diseases with 95%+ accuracy.
            </p>
        </div>

        <!-- Upload Section -->
        <div class="max-w-4xl mx-auto bg-white rounded-xl shadow-lg p-8 mb-8">
            <div id="uploadArea" class="upload-area rounded-lg p-8 text-center cursor-pointer">
                <div class="mb-4">
                    <i class="fas fa-cloud-upload-alt text-5xl text-green-500 mb-4"></i>
                </div>
                <h3 class="text-xl font-semibold text-gray-700 mb-2">Upload Plant Image</h3>
                <p class="text-gray-500 mb-4">Drag & drop or click to upload an image of plant leaf</p>
                <p class="text-sm text-gray-400 mb-4">Supported formats: JPG, PNG, JPEG | Max size: 5MB</p>
                
                <form id="uploadForm" enctype="multipart/form-data">
                    <input type="file" id="plantImage" name="plantImage" accept=".jpg,.jpeg,.png" class="hidden">
                    <button type="button" onclick="document.getElementById('plantImage').click()" 
                            class="bg-green-500 hover:bg-green-600 text-white px-6 py-3 rounded-lg font-semibold transition duration-200">
                        Choose Image
                    </button>
                </form>
            </div>

            <!-- Preview Section -->
            <div id="previewSection" class="hidden mt-6 text-center">
                <div class="mb-4">
                    <img id="imagePreview" class="max-w-xs max-h-64 mx-auto rounded-lg shadow-md">
                </div>
                <button id="analyzeBtn" 
                        class="bg-green-600 hover:bg-green-700 text-white px-8 py-3 rounded-lg font-semibold transition duration-200">
                    <i class="fas fa-search mr-2"></i>Analyze Plant Health
                </button>
            </div>
        </div>

        <!-- Results Section -->
        <div id="resultsSection" class="max-w-4xl mx-auto hidden">
            <div class="bg-white rounded-xl shadow-lg p-8">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Analysis Results</h2>
                
                <!-- Loading -->
                <div id="loadingSection" class="text-center py-8 hidden">
                    <div class="inline-block animate-spin rounded-full h-12 w-12 border-b-2 border-green-600"></div>
                    <p class="text-gray-600 mt-4">Analyzing plant health...</p>
                </div>

                <!-- Results -->
                <div id="resultsContent" class="hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <!-- Plant Info -->
                        <div class="result-card bg-green-50 border border-green-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-green-800 mb-3">Plant Information</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Plant Type:</span>
                                    <span id="plantType" class="font-semibold text-green-700"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Confidence:</span>
                                    <span id="plantConfidence" class="font-semibold text-green-700"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Disease Info -->
                        <div class="result-card bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <h3 class="text-lg font-semibold text-blue-800 mb-3">Disease Analysis</h3>
                            <div class="space-y-2">
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Condition:</span>
                                    <span id="diseaseName" class="font-semibold text-blue-700"></span>
                                </div>
                                <div class="flex justify-between">
                                    <span class="text-gray-600">Confidence:</span>
                                    <span id="diseaseConfidence" class="font-semibold text-blue-700"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Severity -->
                    <div class="result-card mb-6">
                        <h3 class="text-lg font-semibold text-gray-800 mb-3">Severity Assessment</h3>
                        <div id="severityCard" class="rounded-lg p-4">
                            <div class="flex justify-between items-center">
                                <span id="severityLevel" class="text-lg font-semibold"></span>
                                <span id="severityConfidence" class="text-sm text-gray-600"></span>
                            </div>
                            <div class="w-full bg-gray-200 rounded-full h-2 mt-2">
                                <div id="severityBar" class="h-2 rounded-full transition-all duration-500"></div>
                            </div>
                        </div>
                    </div>

                    <!-- Recommendations -->
                    <div class="result-card bg-yellow-50 border border-yellow-200 rounded-lg p-6">
                        <h3 class="text-lg font-semibold text-yellow-800 mb-3">Recommendations</h3>
                        <div id="recommendations" class="space-y-2 text-gray-700"></div>
                    </div>

                    <!-- Try Another -->
                    <div class="text-center mt-8">
                        <button onclick="resetForm()" 
                                class="bg-gray-500 hover:bg-gray-600 text-white px-6 py-2 rounded-lg font-semibold transition duration-200">
                            <i class="fas fa-redo mr-2"></i>Analyze Another Image
                        </button>
                    </div>
                </div>

                <!-- Error Section -->
                <div id="errorSection" class="hidden text-center py-8">
                    <i class="fas fa-exclamation-triangle text-4xl text-red-500 mb-4"></i>
                    <h3 class="text-xl font-semibold text-red-600 mb-2">Analysis Failed</h3>
                    <p id="errorMessage" class="text-gray-600"></p>
                    <button onclick="resetForm()" 
                            class="mt-4 bg-red-500 hover:bg-red-600 text-white px-6 py-2 rounded-lg font-semibold transition duration-200">
                        Try Again
                    </button>
                </div>
            </div>
        </div>

        <!-- Supported Plants -->
        <div class="max-w-4xl mx-auto mt-12">
            <h2 class="text-2xl font-bold text-gray-800 mb-6 text-center">Supported Plants</h2>
            <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <i class="fas fa-seedling text-3xl text-green-600 mb-2"></i>
                    <p class="font-semibold">Tomato</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <i class="fas fa-seedling text-3xl text-green-600 mb-2"></i>
                    <p class="font-semibold">Potato</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <i class="fas fa-seedling text-3xl text-green-600 mb-2"></i>
                    <p class="font-semibold">Corn</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <i class="fas fa-seedling text-3xl text-green-600 mb-2"></i>
                    <p class="font-semibold">Rice</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <i class="fas fa-seedling text-3xl text-green-600 mb-2"></i>
                    <p class="font-semibold">Wheat</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <i class="fas fa-seedling text-3xl text-green-600 mb-2"></i>
                    <p class="font-semibold">Eggplant</p>
                </div>
                <div class="text-center p-4 bg-green-50 rounded-lg">
                    <i class="fas fa-seedling text-3xl text-green-600 mb-2"></i>
                    <p class="font-semibold">Cauliflower</p>
                </div>
            </div>
        </div>
    </div>
    <?php include 'footer.php'; ?>

    <script>
        // DOM Elements
        const uploadArea = document.getElementById('uploadArea');
        const plantImage = document.getElementById('plantImage');
        const previewSection = document.getElementById('previewSection');
        const imagePreview = document.getElementById('imagePreview');
        const analyzeBtn = document.getElementById('analyzeBtn');
        const resultsSection = document.getElementById('resultsSection');
        const loadingSection = document.getElementById('loadingSection');
        const resultsContent = document.getElementById('resultsContent');
        const errorSection = document.getElementById('errorSection');
        const errorMessage = document.getElementById('errorMessage');

        // Drag and drop functionality
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                handleImageSelection(files[0]);
            }
        });

        // File input change
        plantImage.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                handleImageSelection(e.target.files[0]);
            }
        });

        // Analyze button click
        analyzeBtn.addEventListener('click', analyzePlant);

        function handleImageSelection(file) {
            // Validate file type
            const validTypes = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!validTypes.includes(file.type)) {
                alert('Please upload a valid image file (JPG, JPEG, PNG)');
                return;
            }

            // Validate file size (5MB)
            if (file.size > 5 * 1024 * 1024) {
                alert('File size must be less than 5MB');
                return;
            }

            // Show preview
            const reader = new FileReader();
            reader.onload = (e) => {
                imagePreview.src = e.target.result;
                previewSection.classList.remove('hidden');
                uploadArea.classList.add('hidden');
            };
            reader.readAsDataURL(file);
        }

        function analyzePlant() {
            if (!plantImage.files[0]) {
                alert('Please select an image first');
                return;
            }

            // Show loading
            loadingSection.classList.remove('hidden');
            resultsContent.classList.add('hidden');
            errorSection.classList.add('hidden');
            resultsSection.classList.remove('hidden');

            const formData = new FormData();
            formData.append('plantImage', plantImage.files[0]);

            fetch('api/disease_detection.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                loadingSection.classList.add('hidden');
                
                if (data.success) {
                    displayResults(data);
                } else {
                    showError(data.error || 'Analysis failed');
                }
            })
            .catch(error => {
                loadingSection.classList.add('hidden');
                showError('Network error: ' + error.message);
            });
        }

        function displayResults(data) {
            // Plant information
            document.getElementById('plantType').textContent = data.plant_type;
            document.getElementById('plantConfidence').textContent = (data.plant_confidence * 100).toFixed(1) + '%';

            // Disease information
            document.getElementById('diseaseName').textContent = data.disease_name;
            document.getElementById('diseaseConfidence').textContent = (data.disease_confidence * 100).toFixed(1) + '%';

            // Severity information
            const severityCard = document.getElementById('severityCard');
            const severityLevel = document.getElementById('severityLevel');
            const severityConfidence = document.getElementById('severityConfidence');
            const severityBar = document.getElementById('severityBar');

            severityLevel.textContent = data.severity + ' Severity';
            severityConfidence.textContent = (data.severity_confidence * 100).toFixed(1) + '% confidence';

            // Set severity color and width
            let severityColor, barWidth;
            switch(data.severity.toLowerCase()) {
                case 'low':
                    severityColor = '#10b981';
                    barWidth = '33%';
                    severityCard.className = 'severity-low rounded-lg p-4';
                    break;
                case 'medium':
                    severityColor = '#f59e0b';
                    barWidth = '66%';
                    severityCard.className = 'severity-medium rounded-lg p-4';
                    break;
                case 'high':
                    severityColor = '#ef4444';
                    barWidth = '100%';
                    severityCard.className = 'severity-high rounded-lg p-4';
                    break;
            }

            severityBar.style.backgroundColor = severityColor;
            severityBar.style.width = barWidth;

            // Recommendations
            const recommendations = document.getElementById('recommendations');
            recommendations.innerHTML = generateRecommendations(data);

            // Show results
            resultsContent.classList.remove('hidden');
        }

        function generateRecommendations(data) {
            let recommendations = '';
            
            if (data.is_healthy) {
                recommendations = `
                    <p>✅ Your plant appears to be healthy! Continue with current care practices.</p>
                    <p>💡 Regular monitoring and proper nutrition will maintain plant health.</p>
                `;
            } else {
                recommendations = `
                    <p>🔍 <strong>Immediate Actions:</strong></p>
                    <p>• Isolate the affected plant to prevent spread</p>
                    <p>• Remove severely infected leaves carefully</p>
                    <p>• Adjust watering schedule to avoid moisture stress</p>
                    
                    <p class="mt-3">🌱 <strong>Treatment Options:</strong></p>
                    <p>• Apply appropriate fungicide/pesticide for ${data.disease_name}</p>
                    <p>• Improve air circulation around the plant</p>
                    <p>• Ensure proper sunlight and nutrition</p>
                    
                    <p class="mt-3">📊 <strong>Monitoring:</strong></p>
                    <p>• Check plant daily for changes</p>
                    <p>• Take follow-up photos to track progress</p>
                    <p>• Consult gardening expert if condition worsens</p>
                `;
            }
            
            return recommendations;
        }

        function showError(message) {
            errorMessage.textContent = message;
            errorSection.classList.remove('hidden');
        }

        function resetForm() {
            // Reset everything
            plantImage.value = '';
            previewSection.classList.add('hidden');
            resultsSection.classList.add('hidden');
            uploadArea.classList.remove('hidden');
            loadingSection.classList.add('hidden');
            resultsContent.classList.add('hidden');
            errorSection.classList.add('hidden');
        }
    </script>
</body>
</html>