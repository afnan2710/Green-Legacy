<?php
session_start();
// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header("Location: admin_login.php");
    exit;
}

require_once 'db_connect.php';
include 'admin_sidebar.php';

// Get job ID from URL
$job_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fetch job details
$stmt = $conn->prepare("SELECT * FROM jobs WHERE id = ?");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$job = $result->fetch_assoc();
$stmt->close();

if (!$job) {
    $_SESSION['message'] = "Job not found";
    $_SESSION['message_type'] = "error";
    header("Location: admin_jobs.php");
    exit;
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $department = $_POST['department'];
    $location = $_POST['location'];
    $job_type = $_POST['job_type'];
    $salary_range = $_POST['salary_range'];
    $description = $_POST['description'];
    $requirements = $_POST['requirements'];
    $responsibilities = $_POST['responsibilities'];
    $benefits = $_POST['benefits'];
    $is_active = isset($_POST['is_active']) ? 1 : 0;
    $deadline = $_POST['deadline'];

    $stmt = $conn->prepare("UPDATE jobs SET title = ?, department = ?, location = ?, job_type = ?, salary_range = ?, description = ?, requirements = ?, responsibilities = ?, benefits = ?, is_active = ?, deadline = ? WHERE id = ?");
    $stmt->bind_param("sssssssssssi", $title, $department, $location, $job_type, $salary_range, $description, $requirements, $responsibilities, $benefits, $is_active, $deadline, $job_id);

    if ($stmt->execute()) {
        $_SESSION['message'] = "Job updated successfully";
        $_SESSION['message_type'] = "success";
        echo "<script>window.location.href = 'admin_job_details.php?id=$job_id';</script>";
        exit;
    } else {
        $_SESSION['message'] = "Error updating job: " . $stmt->error;
        $_SESSION['message_type'] = "error";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Job - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.tiny.cloud/1/pc6c1uu3e1pyegmde5wvoxf7ys8ah79unstdolhyvm2lp79u/tinymce/5/tinymce.min.js" referrerpolicy="origin"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin-left: 280px;
        }
    </style>
</head>
<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Edit Job Posting</h1>
            <a href="admin_job_details.php?id=<?php echo $job_id; ?>" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md flex items-center">
                <i class="fas fa-arrow-left mr-2"></i> Back to Job
            </a>
        </div>

        <!-- Display messages -->
        <?php if (isset($_SESSION['message'])): ?>
            <div class="alert alert-<?php echo $_SESSION['message_type']; ?> mb-6 p-4 rounded-md">
                <?php 
                    echo $_SESSION['message']; 
                    unset($_SESSION['message']);
                    unset($_SESSION['message_type']);
                ?>
            </div>
        <?php endif; ?>

        <!-- Job Edit Form -->
        <div class="bg-white rounded-lg shadow overflow-hidden p-6">
            <form action="admin_edit_job.php?id=<?php echo $job_id; ?>" method="POST">
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <!-- Basic Information -->
                    <div class="col-span-2">
                        <h2 class="text-lg font-medium text-gray-900 mb-4">Basic Information</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label for="title" class="block text-sm font-medium text-gray-700">Job Title*</label>
                                <input type="text" name="title" id="title" required value="<?php echo htmlspecialchars($job['title']); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <label for="department" class="block text-sm font-medium text-gray-700">Department*</label>
                                <input type="text" name="department" id="department" required value="<?php echo htmlspecialchars($job['department']); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <label for="location" class="block text-sm font-medium text-gray-700">Location*</label>
                                <input type="text" name="location" id="location" required value="<?php echo htmlspecialchars($job['location']); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div>
                                <label for="job_type" class="block text-sm font-medium text-gray-700">Job Type*</label>
                                <select name="job_type" id="job_type" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500">
                                    <option value="Full-time" <?php echo $job['job_type'] === 'Full-time' ? 'selected' : ''; ?>>Full-time</option>
                                    <option value="Part-time" <?php echo $job['job_type'] === 'Part-time' ? 'selected' : ''; ?>>Part-time</option>
                                    <option value="Contract" <?php echo $job['job_type'] === 'Contract' ? 'selected' : ''; ?>>Contract</option>
                                    <option value="Internship" <?php echo $job['job_type'] === 'Internship' ? 'selected' : ''; ?>>Internship</option>
                                    <option value="Temporary" <?php echo $job['job_type'] === 'Temporary' ? 'selected' : ''; ?>>Temporary</option>
                                </select>
                            </div>
                            <div>
                                <label for="salary_range" class="block text-sm font-medium text-gray-700">Salary Range</label>
                                <input type="text" name="salary_range" id="salary_range" value="<?php echo htmlspecialchars($job['salary_range']); ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500" placeholder="e.g. $50,000 - $70,000">
                            </div>
                            <div>
                                <label for="deadline" class="block text-sm font-medium text-gray-700">Application Deadline</label>
                                <input type="date" name="deadline" id="deadline" value="<?php echo $job['deadline'] ? htmlspecialchars($job['deadline']) : ''; ?>" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500">
                            </div>
                            <div class="flex items-center">
                                <input type="checkbox" name="is_active" id="is_active" <?php echo $job['is_active'] ? 'checked' : ''; ?> class="h-4 w-4 text-green-600 focus:ring-green-500 border-gray-300 rounded">
                                <label for="is_active" class="ml-2 block text-sm text-gray-700">Active Job Posting</label>
                            </div>
                        </div>
                    </div>

                    <!-- Job Description -->
                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700">Job Description*</label>
                        <textarea name="description" id="description" rows="6" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500"><?php echo htmlspecialchars($job['description']); ?></textarea>
                    </div>

                    <!-- Requirements -->
                    <div>
                        <label for="requirements" class="block text-sm font-medium text-gray-700">Requirements*</label>
                        <textarea name="requirements" id="requirements" rows="6" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500"><?php echo htmlspecialchars($job['requirements']); ?></textarea>
                    </div>

                    <!-- Responsibilities -->
                    <div>
                        <label for="responsibilities" class="block text-sm font-medium text-gray-700">Responsibilities*</label>
                        <textarea name="responsibilities" id="responsibilities" rows="6" required class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500"><?php echo htmlspecialchars($job['responsibilities']); ?></textarea>
                    </div>

                    <!-- Benefits -->
                    <div>
                        <label for="benefits" class="block text-sm font-medium text-gray-700">Benefits</label>
                        <textarea name="benefits" id="benefits" rows="6" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500"><?php echo htmlspecialchars($job['benefits']); ?></textarea>
                    </div>
                </div>

                <div class="mt-8 flex justify-end">
                    <button type="submit" class="bg-green-600 hover:bg-green-700 text-white px-6 py-2 rounded-md flex items-center">
                        <i class="fas fa-save mr-2"></i> Update Job Posting
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // Initialize TinyMCE for rich text editors
        tinymce.init({
            selector: '#description, #requirements, #responsibilities, #benefits',
            plugins: 'lists link image paste help wordcount',
            toolbar: 'undo redo | formatselect | bold italic | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | link',
            menubar: false,
            statusbar: false,
            height: 200,
            content_style: 'body { font-family: "Poppins", sans-serif; font-size:14px }'
        });
    </script>
</body>
</html>