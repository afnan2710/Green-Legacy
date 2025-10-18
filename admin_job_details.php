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
$job = [];
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

// Fetch applications for this job
$applications = [];
$stmt = $conn->prepare("SELECT * FROM job_applications WHERE job_id = ? ORDER BY applied_at DESC");
$stmt->bind_param("i", $job_id);
$stmt->execute();
$result = $stmt->get_result();
$applications = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Job Details - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            margin-left: 280px;
        }

        .job-status-active {
            background-color: #d1fae5;
            color: #065f46;
        }

        .job-status-inactive {
            background-color: #fee2e2;
            color: #b91c1c;
        }

        .job-type {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .full-time {
            background-color: #d1fae5;
            color: #065f46;
        }

        .part-time {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .contract {
            background-color: #ede9fe;
            color: #5b21b6;
        }

        .internship {
            background-color: #fef3c7;
            color: #92400e;
        }

        .temporary {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .application-status {
            display: inline-block;
            padding: 0.25rem 0.5rem;
            border-radius: 0.25rem;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .Submitted {
            background-color: #e0f2fe;
            color: #0369a1;
        }

        .Under-Review {
            background-color: #fef3c7;
            color: #92400e;
        }

        .Interview {
            background-color: #ede9fe;
            color: #5b21b6;
        }

        .Rejected {
            background-color: #fee2e2;
            color: #991b1b;
        }

        .Hired {
            background-color: #d1fae5;
            color: #065f46;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="container mx-auto px-4 py-8">
        <div class="flex justify-between items-center mb-6">
            <h1 class="text-2xl font-bold text-gray-800">Job Details</h1>
            <div class="flex space-x-2">
                <a href="admin_edit_job.php?id=<?php echo $job['id']; ?>" class="bg-green-600 hover:bg-green-700 text-white px-4 py-2 rounded-md flex items-center">
                    <i class="fas fa-edit mr-2"></i> Edit Job
                </a>
                <a href="admin_jobs.php" class="bg-gray-600 hover:bg-gray-700 text-white px-4 py-2 rounded-md flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Jobs
                </a>
            </div>
        </div>

        <!-- Job Details Card -->
        <div class="bg-white rounded-lg shadow overflow-hidden mb-8">
            <div class="p-6">
                <div class="flex justify-between items-start">
                    <div>
                        <h2 class="text-xl font-bold text-gray-900"><?php echo htmlspecialchars($job['title']); ?></h2>
                        <div class="flex items-center mt-2">
                            <span class="job-type <?php echo strtolower(str_replace('-', '', $job['job_type'])); ?> mr-2">
                                <?php echo htmlspecialchars($job['job_type']); ?>
                            </span>
                            <span class="px-2 py-1 text-xs font-semibold rounded-full <?php echo $job['is_active'] ? 'job-status-active' : 'job-status-inactive'; ?>">
                                <?php echo $job['is_active'] ? 'Active' : 'Inactive'; ?>
                            </span>
                        </div>
                        <div class="mt-2 text-sm text-gray-500">
                            <span><i class="fas fa-building mr-1"></i> <?php echo htmlspecialchars($job['department']); ?></span>
                            <span class="mx-2">•</span>
                            <span><i class="fas fa-map-marker-alt mr-1"></i> <?php echo htmlspecialchars($job['location']); ?></span>
                        </div>
                    </div>
                    <div class="text-right">
                        <div class="text-sm text-gray-500">Posted: <?php echo date('M d, Y', strtotime($job['posted_at'])); ?></div>
                        <?php if ($job['deadline']): ?>
                            <div class="text-sm text-gray-500 mt-1">Deadline: <?php echo date('M d, Y', strtotime($job['deadline'])); ?></div>
                        <?php endif; ?>
                        <div class="text-sm text-gray-500 mt-1">Views: <?php echo $job['views']; ?></div>
                    </div>
                </div>

                <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="md:col-span-2">
                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Job Description</h3>
                            <div class="prose max-w-none text-gray-700">
                                <?php echo $job['description']; ?>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Requirements</h3>
                            <div class="prose max-w-none text-gray-700">
                                <?php echo $job['requirements']; ?>
                            </div>
                        </div>

                        <div class="mb-6">
                            <h3 class="text-lg font-medium text-gray-900 mb-2">Responsibilities</h3>
                            <div class="prose max-w-none text-gray-700">
                                <?php echo $job['responsibilities']; ?>
                            </div>
                        </div>

                        <?php if ($job['benefits']): ?>
                            <div class="mb-6">
                                <h3 class="text-lg font-medium text-gray-900 mb-2">Benefits</h3>
                                <div class="prose max-w-none text-gray-700">
                                    <?php echo $job['benefits']; ?>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>

                    <div>
                        <div class="bg-gray-50 p-4 rounded-lg">
                            <h3 class="text-lg font-medium text-gray-900 mb-4">Job Summary</h3>
                            <div class="space-y-3">
                                <div>
                                    <span class="block text-sm font-medium text-gray-500">Job Type</span>
                                    <span class="text-sm text-gray-900"><?php echo htmlspecialchars($job['job_type']); ?></span>
                                </div>
                                <?php if ($job['salary_range']): ?>
                                    <div>
                                        <span class="block text-sm font-medium text-gray-500">Salary Range</span>
                                        <span class="text-sm text-gray-900"><?php echo htmlspecialchars($job['salary_range']); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span class="block text-sm font-medium text-gray-500">Location</span>
                                    <span class="text-sm text-gray-900"><?php echo htmlspecialchars($job['location']); ?></span>
                                </div>
                                <div>
                                    <span class="block text-sm font-medium text-gray-500">Department</span>
                                    <span class="text-sm text-gray-900"><?php echo htmlspecialchars($job['department']); ?></span>
                                </div>
                                <div>
                                    <span class="block text-sm font-medium text-gray-500">Posted Date</span>
                                    <span class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($job['posted_at'])); ?></span>
                                </div>
                                <?php if ($job['deadline']): ?>
                                    <div>
                                        <span class="block text-sm font-medium text-gray-500">Application Deadline</span>
                                        <span class="text-sm text-gray-900"><?php echo date('M d, Y', strtotime($job['deadline'])); ?></span>
                                    </div>
                                <?php endif; ?>
                                <div>
                                    <span class="block text-sm font-medium text-gray-500">Status</span>
                                    <span class="text-sm text-gray-900"><?php echo $job['is_active'] ? 'Active' : 'Inactive'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Applications Section -->
        <div class="bg-white rounded-lg shadow overflow-hidden">
            <div class="p-6 border-b border-gray-200">
                <h2 class="text-lg font-medium text-gray-900">Applications (<?php echo count($applications); ?>)</h2>
            </div>

            <?php if (empty($applications)): ?>
                <div class="p-6 text-center text-gray-500">
                    No applications received yet for this job.
                </div>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Email</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Phone</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Applied On</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                            <?php foreach ($applications as $application): ?>
                                <tr>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <div class="font-medium text-gray-900"><?php echo htmlspecialchars($application['applicant_name']); ?></div>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($application['applicant_email']); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo htmlspecialchars($application['applicant_phone'] ?? 'N/A'); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">
                                        <?php echo date('M d, Y', strtotime($application['applied_at'])); ?>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap">
                                        <span class="application-status <?php echo str_replace(' ', '-', $application['status']); ?>">
                                            <?php echo htmlspecialchars($application['status']); ?>
                                        </span>
                                    </td>
                                    <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                        <a href="admin_application_details.php?id=<?php echo $application['id']; ?>" class="text-blue-600 hover:text-blue-900 mr-3">
                                            <i class="fas fa-eye"></i> View
                                        </a>
                                        <?php if ($application['resume_path']): ?>
                                            <a href="<?php echo htmlspecialchars($application['resume_path']); ?>" target="_blank" class="text-green-600 hover:text-green-900">
                                                <i class="fas fa-download"></i> Resume
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>
    </div>
</body>

</html>