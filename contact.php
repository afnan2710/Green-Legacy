<?php
// Database connection
require_once 'db_connect.php';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'] ?? '';
    $email = $_POST['email'] ?? '';
    $subject = $_POST['subject'] ?? '';
    $message = $_POST['message'] ?? '';
    $contact_method = $_POST['contact_method'] ?? 'email';

    // Basic validation
    if (!empty($name) && !empty($email) && !empty($message)) {
        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_message = "Please enter a valid email address.";
        } else {
            // Insert into database
            $stmt = $conn->prepare("INSERT INTO messages (name, email, subject, message, created_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->bind_param("ssss", $name, $email, $subject, $message);

            if ($stmt->execute()) {
                $success_message = "Thank you for your message! We'll get back to you soon.";
                // Clear form fields
                $name = $email = $subject = $message = '';
            } else {
                $error_message = "There was an error sending your message. Please try again.";
            }
        }
    } else {
        $error_message = "Please fill in all required fields.";
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Us - Green Legacy</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0-beta3/css/all.min.css">
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }

        .contact-section {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
        }

        .map-container {
            height: 600px;
            border-radius: 0.5rem;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        }

        .form-input {
            transition: all 0.3s ease;
        }

        .form-input:focus {
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.2);
        }

        .contact-card {
            transition: all 0.3s ease;
        }

        .contact-card:hover {
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }

        .floating-label {
            position: absolute;
            top: 0.75rem;
            left: 1rem;
            color: #718096;
            transition: all 0.2s ease;
            pointer-events: none;
        }

        .form-group:focus-within .floating-label,
        .form-group.filled .floating-label {
            top: -0.5rem;
            left: 0.8rem;
            font-size: 0.75rem;
            color: #38a169;
            background: white;
            padding: 0 0.25rem;
        }

        .character-count {
            font-size: 0.75rem;
            color: #718096;
            text-align: right;
            margin-top: -0.5rem;
            margin-bottom: 1rem;
        }

        .character-count.warning {
            color: #e53e3e;
        }

        .owner-card {
            transition: all 0.3s ease;
        }

        .owner-card:hover {
            transform: translateY(-5px);
        }

        .owner-image {
            transition: all 0.3s ease;
        }

        .owner-card:hover .owner-image {
            transform: scale(1.05);
            box-shadow: 0 10px 25px -5px rgba(0, 0, 0, 0.1);
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>

    <div class="container mx-auto px-4 py-16">
        <div class="text-center mb-12">
            <h1 class="text-4xl font-bold text-gray-800 mb-4">Get in Touch</h1>
            <p class="text-lg text-gray-600 max-w-2xl mx-auto">
                Have questions or feedback? We'd love to hear from you! Reach out to us through the form below or visit our office.
            </p>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-2 gap-12">
            <!-- Contact Form -->
            <div class="bg-white rounded-xl shadow-lg p-8 contact-card">
                <h2 class="text-2xl font-semibold text-gray-800 mb-6">Send us a message</h2>

                <?php if (isset($success_message)): ?>
                    <div id="success-alert" class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6 flex items-center justify-between">
                        <div>
                            <i class="fas fa-check-circle mr-2"></i>
                            <?php echo $success_message; ?>
                        </div>
                        <button onclick="document.getElementById('success-alert').style.display='none'" class="text-green-700 hover:text-green-900">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if (isset($error_message)): ?>
                    <div id="error-alert" class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6 flex items-center justify-between">
                        <div>
                            <i class="fas fa-exclamation-circle mr-2"></i>
                            <?php echo $error_message; ?>
                        </div>
                        <button onclick="document.getElementById('error-alert').style.display='none'" class="text-red-700 hover:text-red-900">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                <?php endif; ?>

                <form action="contact.php" method="POST" id="contactForm">
                    <div class="mb-6 form-group relative">
                        <input type="text" id="name" name="name" required value="<?php echo htmlspecialchars($name ?? ''); ?>"
                            class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                        <label for="name" class="floating-label">Full Name *</label>
                    </div>

                    <div class="mb-6 form-group relative">
                        <input type="email" id="email" name="email" required value="<?php echo htmlspecialchars($email ?? ''); ?>"
                            class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                        <label for="email" class="floating-label">Email Address *</label>
                    </div>

                    <div class="mb-6 form-group relative">
                        <input type="text" id="subject" name="subject" value="<?php echo htmlspecialchars($subject ?? ''); ?>"
                            class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500">
                        <label for="subject" class="floating-label">Subject</label>
                    </div>

                    <div class="mb-6">
                        <div class="form-group relative">
                            <textarea id="message" name="message" rows="5" required
                                class="form-input w-full px-4 py-3 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"><?php echo htmlspecialchars($message ?? ''); ?></textarea>
                            <label for="message" class="floating-label">Your Message *</label>
                        </div>
                        <div id="charCount" class="character-count">0/500 characters</div>
                    </div>

                    <button type="submit" id="submitBtn"
                        class="w-full bg-green-600 hover:bg-green-700 text-white font-bold py-3 px-4 rounded-lg transition duration-200 flex items-center justify-center">
                        <span id="submitText">Send Message</span>
                        <span id="submitSpinner" class="ml-2 hidden">
                            <i class="fas fa-spinner fa-spin"></i>
                        </span>
                    </button>
                </form>

                <!-- Owners Section -->
                <div class="mt-12">
                    <h3 class="text-xl font-semibold text-gray-800 mb-6 text-center">Meet Our Team</h3>

                    <!-- CEO - Centered and Highlighted -->
                    <div class="flex justify-center mb-8">
                        <div class="owner-card text-center max-w-xs">
                            <div class="mx-auto mb-4 w-32 h-32 rounded-full overflow-hidden border-4 border-green-500 shadow-xl">
                                <img src="ceo.jpg" alt="Afnan Shahriar" class="w-full h-full object-cover">
                            </div>
                            <h4 class="text-xl font-bold text-gray-800">Afnan Shahriar</h4>
                            <p class="text-green-600 font-medium mb-2">Founder & CEO</p>
                            <div class="flex justify-center space-x-4">
                                <a href="#" class="text-gray-600 hover:text-green-600">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                                <a href="#" class="text-gray-600 hover:text-green-600">
                                    <i class="fab fa-facebook"></i>
                                </a>
                                <a href="#" class="text-gray-600 hover:text-green-600">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Department Heads - In a row below -->
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                        <!-- Head of Operations -->
                        <div class="owner-card text-center">
                            <div class="mx-auto mb-4 w-32 h-32 rounded-full overflow-hidden border-4 border-green-300 shadow-lg">
                                <img src="coo.jpg" alt="Mst. Sumaiya Akter" class="w-full h-full object-cover">
                            </div>
                            <h4 class="text-lg font-bold text-gray-800">Mst. Sumaiya Akter</h4>
                            <p class="text-green-600 mb-2">Head of Operations</p>
                            <div class="flex justify-center space-x-3">
                                <a href="#" class="text-gray-600 hover:text-green-600">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                                <a href="#" class="text-gray-600 hover:text-green-600">
                                    <i class="fab fa-facebook"></i>
                                </a>
                                <a href="#" class="text-gray-600 hover:text-green-600">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Head of Finance -->
                        <div class="owner-card text-center">
                            <div class="mx-auto mb-4 w-32 h-32 rounded-full overflow-hidden border-4 border-green-300 shadow-lg">
                                <img src="cfo.jpg" alt="Abir Nag Bulbul" class="w-full h-full object-cover">
                            </div>
                            <h4 class="text-lg font-bold text-gray-800">Abir Nag Bulbul</h4>
                            <p class="text-green-600 mb-2">Head of Finance</p>
                            <div class="flex justify-center space-x-3">
                                <a href="#" class="text-gray-600 hover:text-green-600">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                                <a href="#" class="text-gray-600 hover:text-green-600">
                                    <i class="fab fa-facebook"></i>
                                </a>
                                <a href="#" class="text-gray-600 hover:text-green-600">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            </div>
                        </div>

                        <!-- Head of IT Support -->
                        <div class="owner-card text-center">
                            <div class="mx-auto mb-4 w-32 h-32 rounded-full overflow-hidden border-4 border-green-300 shadow-lg">
                                <img src="cto.jpg" alt="Mst. Sayma Akter" class="w-full h-full object-cover">
                            </div>
                            <h4 class="text-lg font-bold text-gray-800">Mst. Sayma Akter</h4>
                            <p class="text-green-600 mb-2">Head of IT Support</p>
                            <div class="flex justify-center space-x-3">
                                <a href="#" class="text-gray-600 hover:text-green-600">
                                    <i class="fab fa-linkedin-in"></i>
                                </a>
                                <a href="#" class="text-gray-600 hover:text-green-600">
                                    <i class="fab fa-facebook"></i>
                                </a>
                                <a href="#" class="text-gray-600 hover:text-green-600">
                                    <i class="fas fa-envelope"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Contact Info & Map -->
            <div class="space-y-8">
                <div class="bg-white rounded-xl shadow-lg p-8 contact-card">
                    <h2 class="text-2xl font-semibold text-gray-800 mb-6">Our Information</h2>

                    <div class="space-y-6">
                        <div class="flex items-start">
                            <div class="bg-green-100 p-3 rounded-full mr-4 flex-shrink-0">
                                <i class="fas fa-map-marker-alt text-green-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-gray-800">Our Address</h3>
                                <p class="text-gray-600">United City, Madani Avenue, Badda, Dhaka 1212, Bangladesh</p>
                                <button onclick="openDirections()" class="mt-2 text-green-600 hover:text-green-800 text-sm font-medium flex items-center">
                                    <i class="fas fa-directions mr-1"></i> Get Directions
                                </button>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <div class="bg-green-100 p-3 rounded-full mr-4 flex-shrink-0">
                                <i class="fas fa-phone-alt text-green-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-gray-800">Phone Number</h3>
                                <p class="text-gray-600">+880 1601-701444</p>
                                <button onclick="callUs()" class="mt-2 text-green-600 hover:text-green-800 text-sm font-medium flex items-center">
                                    <i class="fas fa-phone mr-1"></i> Call Now
                                </button>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <div class="bg-green-100 p-3 rounded-full mr-4 flex-shrink-0">
                                <i class="fas fa-envelope text-green-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-gray-800">Email Address</h3>
                                <p class="text-gray-600">info@greenlegacy.com</p>
                                <button onclick="emailUs()" class="mt-2 text-green-600 hover:text-green-800 text-sm font-medium flex items-center">
                                    <i class="fas fa-paper-plane mr-1"></i> Email Us
                                </button>
                            </div>
                        </div>

                        <div class="flex items-start">
                            <div class="bg-green-100 p-3 rounded-full mr-4 flex-shrink-0">
                                <i class="fas fa-clock text-green-600"></i>
                            </div>
                            <div>
                                <h3 class="font-medium text-gray-800">Working Hours</h3>
                                <div class="grid grid-cols-2 gap-2 text-gray-600">
                                    <div>Sunday - Thursday:</div>
                                    <div class="font-medium">9:00 AM - 6:00 PM</div>
                                    <div>Saturday:</div>
                                    <div class="font-medium">10:00 AM - 4:00 PM</div>
                                    <div>Friday:</div>
                                    <div class="font-medium">Closed</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Google Map with custom marker -->
                <div class="map-container">
                    <iframe
                        src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d3650.5823360349013!2d90.44713507511669!3d23.797882878637957!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3755c7d8042caf2d%3A0x686fa3e360361ddf!2sUnited%20International%20University!5e0!3m2!1sen!2sbd!4v1752431505520!5m2!1sen!2sbd"
                        width="100%"
                        height="100%"
                        style="border:0;"
                        allowfullscreen=""
                        loading="lazy"
                        referrerpolicy="no-referrer-when-downgrade">
                    </iframe>
                </div>
            </div>
        </div>
    </div>

    <?php include 'footer.php'; ?>

    <script>
        // Floating labels functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Initialize floating labels
            const formGroups = document.querySelectorAll('.form-group');
            formGroups.forEach(group => {
                const input = group.querySelector('input, textarea');
                if (input.value.trim() !== '') {
                    group.classList.add('filled');
                }

                input.addEventListener('input', function() {
                    if (this.value.trim() !== '') {
                        group.classList.add('filled');
                    } else {
                        group.classList.remove('filled');
                    }
                });
            });

            // Character counter for message
            const messageInput = document.getElementById('message');
            const charCount = document.getElementById('charCount');

            messageInput.addEventListener('input', function() {
                const currentLength = this.value.length;
                charCount.textContent = `${currentLength}/500 characters`;

                if (currentLength > 450) {
                    charCount.classList.add('warning');
                } else {
                    charCount.classList.remove('warning');
                }

                if (currentLength > 500) {
                    this.value = this.value.substring(0, 500);
                    charCount.textContent = "500/500 characters (max reached)";
                }
            });

            // Form submission spinner
            const contactForm = document.getElementById('contactForm');
            const submitBtn = document.getElementById('submitBtn');
            const submitText = document.getElementById('submitText');
            const submitSpinner = document.getElementById('submitSpinner');

            contactForm.addEventListener('submit', function() {
                submitText.textContent = "Sending...";
                submitSpinner.classList.remove('hidden');
                submitBtn.disabled = true;
            });
        });

        // Contact methods functions
        function openDirections() {
            window.open("https://www.google.com/maps/dir/?api=1&destination=United+International+University,+United+City,+Madani+Avenue,+Badda,+Dhaka");
        }

        function callUs() {
            window.location.href = "tel:+8801601701444";
        }

        function emailUs() {
            window.location.href = "mailto:info@greenlegacy.com";
        }

        // Form validation
        function validateForm() {
            const name = document.getElementById('name').value.trim();
            const email = document.getElementById('email').value.trim();
            const message = document.getElementById('message').value.trim();

            if (!name || !email || !message) {
                alert("Please fill in all required fields.");
                return false;
            }

            if (!/^\S+@\S+\.\S+$/.test(email)) {
                alert("Please enter a valid email address.");
                return false;
            }

            return true;
        }
    </script>
</body>

</html>