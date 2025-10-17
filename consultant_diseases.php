<?php
require_once 'db_connect.php';
session_start();
// Check if consultant is logged in
if (!isset($_SESSION['consultant_logged_in'])) {
    header("Location: consultant_login.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_disease'])) {
        // Handle new disease submission
        $name = $_POST['name'];
        $description = $_POST['description'];
        $symptoms = $_POST['symptoms'];
        $prevention = $_POST['prevention'];
        $treatment = $_POST['treatment'];
        $climate_zones = implode(',', $_POST['climate_zones']);
        $seasons = implode(',', $_POST['seasons']);
        $plant_types = implode(',', $_POST['plant_types']);
        $risk_level = $_POST['risk_level'];
        
        // Handle image upload
        $image_path = '';
        if (isset($_FILES['disease_image']) && $_FILES['disease_image']['error'] == 0) {
            $target_dir = "uploads/diseases/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_ext = pathinfo($_FILES['disease_image']['name'], PATHINFO_EXTENSION);
            $filename = 'disease_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
            $target_file = $target_dir . $filename;
            
            // Check if image file is valid
            $check = getimagesize($_FILES['disease_image']['tmp_name']);
            if ($check !== false) {
                if (move_uploaded_file($_FILES['disease_image']['tmp_name'], $target_file)) {
                    $image_path = $target_file;
                }
            }
        }
        
        // Insert into database
        $stmt = $conn->prepare("INSERT INTO plant_diseases 
            (name, description, symptoms, prevention, treatment, climate_zones, seasons, plant_types, risk_level, image_path) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("ssssssssss", $name, $description, $symptoms, $prevention, $treatment, 
            $climate_zones, $seasons, $plant_types, $risk_level, $image_path);
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Disease added successfully!";
        } else {
            $_SESSION['error_msg'] = "Error adding disease: " . $conn->error;
        }
        
        header("Location: consultant_diseases.php");
        exit();
    }
    elseif (isset($_POST['update_disease'])) {
        // Handle disease update
        $id = $_POST['disease_id'];
        $name = $_POST['name'];
        $description = $_POST['description'];
        $symptoms = $_POST['symptoms'];
        $prevention = $_POST['prevention'];
        $treatment = $_POST['treatment'];
        $climate_zones = implode(',', $_POST['climate_zones']);
        $seasons = implode(',', $_POST['seasons']);
        $plant_types = implode(',', $_POST['plant_types']);
        $risk_level = $_POST['risk_level'];
        
        // Check if new image is uploaded
        if (isset($_FILES['disease_image']) && $_FILES['disease_image']['error'] == 0) {
            $target_dir = "uploads/diseases/";
            if (!file_exists($target_dir)) {
                mkdir($target_dir, 0777, true);
            }
            
            $file_ext = pathinfo($_FILES['disease_image']['name'], PATHINFO_EXTENSION);
            $filename = 'disease_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $file_ext;
            $target_file = $target_dir . $filename;
            
            // Check if image file is valid
            $check = getimagesize($_FILES['disease_image']['tmp_name']);
            if ($check !== false) {
                if (move_uploaded_file($_FILES['disease_image']['tmp_name'], $target_file)) {
                    // Delete old image if exists
                    $stmt = $conn->prepare("SELECT image_path FROM plant_diseases WHERE id = ?");
                    $stmt->bind_param("i", $id);
                    $stmt->execute();
                    $result = $stmt->get_result();
                    $old_image = $result->fetch_assoc()['image_path'];
                    
                    if ($old_image && file_exists($old_image)) {
                        unlink($old_image);
                    }
                    
                    $image_path = $target_file;
                    
                    // Update with new image
                    $stmt = $conn->prepare("UPDATE plant_diseases SET 
                        name = ?, description = ?, symptoms = ?, prevention = ?, treatment = ?, 
                        climate_zones = ?, seasons = ?, plant_types = ?, risk_level = ?, image_path = ?
                        WHERE id = ?");
                    $stmt->bind_param("ssssssssssi", $name, $description, $symptoms, $prevention, 
                        $treatment, $climate_zones, $seasons, $plant_types, $risk_level, $image_path, $id);
                }
            }
        } else {
            // Update without changing image
            $stmt = $conn->prepare("UPDATE plant_diseases SET 
                name = ?, description = ?, symptoms = ?, prevention = ?, treatment = ?, 
                climate_zones = ?, seasons = ?, plant_types = ?, risk_level = ?
                WHERE id = ?");
            $stmt->bind_param("sssssssssi", $name, $description, $symptoms, $prevention, 
                $treatment, $climate_zones, $seasons, $plant_types, $risk_level, $id);
        }
        
        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Disease updated successfully!";
        } else {
            $_SESSION['error_msg'] = "Error updating disease: " . $conn->error;
        }
        
        header("Location: consultant_diseases.php");
        exit();
    }
    elseif (isset($_POST['delete_disease'])) {
        // Handle disease deletion
        $id = $_POST['disease_id'];
        
        // First get image path to delete the file
        $stmt = $conn->prepare("SELECT image_path FROM plant_diseases WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        $image_path = $result->fetch_assoc()['image_path'];
        
        // Delete the disease record
        $stmt = $conn->prepare("DELETE FROM plant_diseases WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            // Delete the image file if it exists
            if ($image_path && file_exists($image_path)) {
                unlink($image_path);
            }
            $_SESSION['success_msg'] = "Disease deleted successfully!";
        } else {
            $_SESSION['error_msg'] = "Error deleting disease: " . $conn->error;
        }
        
        header("Location: consultant_diseases.php");
        exit();
    }
}

// Get all diseases for listing
$diseases = [];
$result = $conn->query("SELECT * FROM plant_diseases ORDER BY name ASC");
if ($result) {
    $diseases = $result->fetch_all(MYSQLI_ASSOC);
}

// Get disease details for editing
$edit_disease = null;
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $id = $_GET['edit'];
    $stmt = $conn->prepare("SELECT * FROM plant_diseases WHERE id = ?");
    $stmt->bind_param("i", $id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_disease = $result->fetch_assoc();
    
    if ($edit_disease) {
        // Convert comma-separated values to arrays for form display
        $edit_disease['climate_zones_array'] = explode(',', $edit_disease['climate_zones']);
        $edit_disease['seasons_array'] = explode(',', $edit_disease['seasons']);
        $edit_disease['plant_types_array'] = explode(',', $edit_disease['plant_types']);
    }
}

// Climate zones, seasons, and plant types for form options
$climate_zones = ['tropical', 'subtropical', 'temperate', 'continental', 'arid', 'polar'];
$seasons = ['spring', 'summer', 'autumn', 'winter', 'rainy', 'dry'];
$plant_types = ['plant', 'tool', 'pesticide', 'fertilizer', 'accessory'];
$risk_levels = ['low', 'medium', 'high'];
?>

<?php include 'consultant_sidebar.php'; ?>

<style>
    .main-content {
        margin-left: 280px;
    }
</style>
<div class="main-content">

    <div class="ml-0 lg:ml-[280px] min-h-screen bg-gray-50">
        <div class="p-6">
            <!-- Header -->
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-3xl font-bold text-gray-800">Plant Diseases</h1>
                <button onclick="document.getElementById('addDiseaseModal').classList.remove('hidden')" 
                    class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-lg flex items-center">
                    <i class="fas fa-plus mr-2"></i> Add New Disease
                </button>
            </div>

            <!-- Success/Error Messages -->
            <?php if (isset($_SESSION['success_msg'])): ?>
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-4">
                    <?php echo $_SESSION['success_msg']; unset($_SESSION['success_msg']); ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($_SESSION['error_msg'])): ?>
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-4">
                    <?php echo $_SESSION['error_msg']; unset($_SESSION['error_msg']); ?>
                </div>
            <?php endif; ?>

            <!-- Diseases Table -->
            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Image</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Climate Zones</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Seasons</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Risk Level</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php if (empty($diseases)): ?>
                                <tr>
                                    <td colspan="6" class="px-6 py-4 text-center text-gray-500">No diseases found</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($diseases as $disease): ?>
                                    <tr>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <?php if ($disease['image_path'] && file_exists($disease['image_path'])): ?>
                                                <img src="<?php echo htmlspecialchars($disease['image_path']); ?>" 
                                                    alt="<?php echo htmlspecialchars($disease['name']); ?>" 
                                                    class="h-10 w-10 rounded-full object-cover">
                                            <?php else: ?>
                                                <div class="h-10 w-10 rounded-full bg-gray-200 flex items-center justify-center">
                                                    <i class="fas fa-leaf text-gray-500"></i>
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($disease['name']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo str_replace(',', ', ', $disease['climate_zones']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <div class="text-sm text-gray-900">
                                                <?php echo str_replace(',', ', ', $disease['seasons']); ?>
                                            </div>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap">
                                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full 
                                                <?php echo $disease['risk_level'] == 'high' ? 'bg-red-100 text-red-800' : 
                                                    ($disease['risk_level'] == 'medium' ? 'bg-yellow-100 text-yellow-800' : 
                                                    'bg-green-100 text-green-800'); ?>">
                                                <?php echo ucfirst($disease['risk_level']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                            <a href="?edit=<?php echo $disease['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">Edit</a>
                                            <form action="consultant_diseases.php" method="POST" class="inline" onsubmit="return confirm('Are you sure you want to delete this disease?');">
                                                <input type="hidden" name="disease_id" value="<?php echo $disease['id']; ?>">
                                                <button type="submit" name="delete_disease" class="text-red-600 hover:text-red-900">Delete</button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Add/Edit Disease Modal -->
<div id="addDiseaseModal" class="<?php echo $edit_disease ? '' : 'hidden'; ?> fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
    <div class="relative top-20 mx-auto p-5 border w-full max-w-4xl shadow-lg rounded-md bg-white">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-xl font-bold text-gray-800">
                <?php echo $edit_disease ? 'Edit Disease' : 'Add New Disease'; ?>
            </h3>
            <button onclick="document.getElementById('addDiseaseModal').classList.add('hidden')" 
                class="text-gray-500 hover:text-gray-700">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <form action="consultant_diseases.php" method="POST" enctype="multipart/form-data">
            <?php if ($edit_disease): ?>
                <input type="hidden" name="disease_id" value="<?php echo $edit_disease['id']; ?>">
            <?php endif; ?>
            
            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Left Column -->
                <div>
                    <div class="mb-4">
                        <label for="name" class="block text-sm font-medium text-gray-700 mb-1">Disease Name*</label>
                        <input type="text" id="name" name="name" required
                            value="<?php echo $edit_disease ? htmlspecialchars($edit_disease['name']) : ''; ?>"
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500">
                    </div>

                    <div class="mb-4">
                        <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Description*</label>
                        <textarea id="description" name="description" rows="3" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"><?php echo $edit_disease ? htmlspecialchars($edit_disease['description']) : ''; ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="symptoms" class="block text-sm font-medium text-gray-700 mb-1">Symptoms*</label>
                        <textarea id="symptoms" name="symptoms" rows="3" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"><?php echo $edit_disease ? htmlspecialchars($edit_disease['symptoms']) : ''; ?></textarea>
                    </div>
                </div>

                <!-- Right Column -->
                <div>
                    <div class="mb-4">
                        <label for="prevention" class="block text-sm font-medium text-gray-700 mb-1">Prevention*</label>
                        <textarea id="prevention" name="prevention" rows="3" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"><?php echo $edit_disease ? htmlspecialchars($edit_disease['prevention']) : ''; ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label for="treatment" class="block text-sm font-medium text-gray-700 mb-1">Treatment*</label>
                        <textarea id="treatment" name="treatment" rows="3" required
                            class="w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-green-500 focus:border-green-500"><?php echo $edit_disease ? htmlspecialchars($edit_disease['treatment']) : ''; ?></textarea>
                    </div>

                    <div class="mb-4">
                        <label class="block text-sm font-medium text-gray-700 mb-1">Risk Level*</label>
                        <div class="flex space-x-4">
                            <?php foreach ($risk_levels as $level): ?>
                                <label class="inline-flex items-center">
                                    <input type="radio" name="risk_level" value="<?php echo $level; ?>" required
                                        <?php echo ($edit_disease && $edit_disease['risk_level'] == $level) ? 'checked' : ''; ?>
                                        <?php echo (!$edit_disease && $level == 'medium') ? 'checked' : ''; ?>
                                        class="h-4 w-4 text-green-600 focus:ring-green-500">
                                    <span class="ml-2 text-sm text-gray-700 capitalize"><?php echo $level; ?></span>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Multi-select options -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mt-4">
                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Climate Zones*</label>
                    <div class="space-y-2">
                        <?php foreach ($climate_zones as $zone): ?>
                            <label class="flex items-center">
                                <input type="checkbox" name="climate_zones[]" value="<?php echo $zone; ?>"
                                    <?php echo ($edit_disease && in_array($zone, $edit_disease['climate_zones_array'])) ? 'checked' : ''; ?>
                                    class="h-4 w-4 text-green-600 focus:ring-green-500">
                                <span class="ml-2 text-sm text-gray-700 capitalize"><?php echo $zone; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Seasons*</label>
                    <div class="space-y-2">
                        <?php foreach ($seasons as $season): ?>
                            <label class="flex items-center">
                                <input type="checkbox" name="seasons[]" value="<?php echo $season; ?>"
                                    <?php echo ($edit_disease && in_array($season, $edit_disease['seasons_array'])) ? 'checked' : ''; ?>
                                    class="h-4 w-4 text-green-600 focus:ring-green-500">
                                <span class="ml-2 text-sm text-gray-700 capitalize"><?php echo $season; ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="block text-sm font-medium text-gray-700 mb-1">Plant Types*</label>
                    <div class="space-y-2">
                        <?php foreach ($plant_types as $type): ?>
                            <label class="flex items-center">
                                <input type="checkbox" name="plant_types[]" value="<?php echo $type; ?>"
                                    <?php echo ($edit_disease && in_array($type, $edit_disease['plant_types_array'])) ? 'checked' : ''; ?>
                                    class="h-4 w-4 text-green-600 focus:ring-green-500">
                                <span class="ml-2 text-sm text-gray-700 capitalize"><?php echo str_replace('_', ' ', $type); ?></span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Image Upload -->
            <div class="mt-4">
                <label class="block text-sm font-medium text-gray-700 mb-1">Disease Image</label>
                <div class="flex items-center">
                    <div class="flex-1">
                        <input type="file" id="disease_image" name="disease_image" accept="image/*"
                            class="block w-full text-sm text-gray-500
                                file:mr-4 file:py-2 file:px-4
                                file:rounded-md file:border-0
                                file:text-sm file:font-semibold
                                file:bg-green-50 file:text-green-700
                                hover:file:bg-green-100">
                    </div>
                    <?php if ($edit_disease && $edit_disease['image_path'] && file_exists($edit_disease['image_path'])): ?>
                        <div class="ml-4">
                            <img src="<?php echo htmlspecialchars($edit_disease['image_path']); ?>" 
                                alt="Current image" class="h-16 w-16 object-cover rounded-md">
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="mt-6 flex justify-end space-x-3">
                <button type="button" onclick="document.getElementById('addDiseaseModal').classList.add('hidden')"
                    class="px-4 py-2 border border-gray-300 rounded-md shadow-sm text-sm font-medium text-gray-700 bg-white hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    Cancel
                </button>
                <button type="submit" name="<?php echo $edit_disease ? 'update_disease' : 'add_disease'; ?>"
                    class="px-4 py-2 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500">
                    <?php echo $edit_disease ? 'Update Disease' : 'Add Disease'; ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    // Close modal when clicking outside
    window.onclick = function(event) {
        const modal = document.getElementById('addDiseaseModal');
        if (event.target == modal) {
            modal.classList.add('hidden');
        }
    }

    // Open modal if editing
    <?php if ($edit_disease): ?>
        document.getElementById('addDiseaseModal').classList.remove('hidden');
    <?php endif; ?>
</script>
</body>
</html>