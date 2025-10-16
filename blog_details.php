<?php
require_once 'db_connect.php';
session_start();


// Check if blog ID is provided
if (!isset($_GET['id'])) {
    header("Location: blogs.php");
    exit();
}

$blog_id = (int)$_GET['id'];

// Get blog details
$stmt = $conn->prepare("
    SELECT b.*, u.firstname, u.lastname, u.profile_pic 
    FROM blogs b
    JOIN users u ON b.author_id = u.id
    WHERE b.id = ? AND b.status = 'published'
");
$stmt->bind_param("i", $blog_id);
$stmt->execute();
$result = $stmt->get_result();
$blog = $result->fetch_assoc();

if (!$blog) {
    header("Location: blogs.php");
    exit();
}

// Handle comment submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['comment'])) {
    if (!isset($_SESSION['user_id'])) {
        $_SESSION['error_msg'] = "Please login to leave a comment";
        header("Location: login.php");
        exit();
    }

    $comment = trim($_POST['comment']);
    $user_id = $_SESSION['user_id'];

    if (!empty($comment)) {
        $stmt = $conn->prepare("INSERT INTO blog_comments (blog_id, user_id, comment) VALUES (?, ?, ?)");
        $stmt->bind_param("iis", $blog_id, $user_id, $comment);

        if ($stmt->execute()) {
            $_SESSION['success_msg'] = "Comment added successfully!";
            header("Location: blog_details.php?id=$blog_id");
            exit();
        } else {
            $_SESSION['error_msg'] = "Error adding comment";
        }
    } else {
        $_SESSION['error_msg'] = "Comment cannot be empty";
    }
}

// Get comments for this blog
$stmt = $conn->prepare("
    SELECT c.*, u.firstname, u.lastname, u.profile_pic 
    FROM blog_comments c
    JOIN users u ON c.user_id = u.id
    WHERE c.blog_id = ?
    ORDER BY c.created_at DESC
");
$stmt->bind_param("i", $blog_id);
$stmt->execute();
$comments_result = $stmt->get_result();
$comments = $comments_result->fetch_all(MYSQLI_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($blog['title']) ?> - Green Legacy</title>
    <style>
        .content img {
            max-width: 100%;
            height: auto;
            margin: 1rem 0;
        }

        .content p {
            margin-bottom: 1rem;
            line-height: 1.6;
        }

        .comment-box {
            transition: all 0.3s ease;
        }

        .comment-box:focus {
            box-shadow: 0 0 0 3px rgba(46, 204, 113, 0.2);
        }

        .breadcrumb {
            display: flex;
            align-items: center;
            font-size: 0.9rem;
            color: #6b7280;
            margin-bottom: 1.5rem;
        }

        .breadcrumb a {
            color: #10b981;
            text-decoration: none;
            transition: color 0.2s;
        }

        .breadcrumb a:hover {
            color: #059669;
            text-decoration: underline;
        }

        .breadcrumb-separator {
            margin: 0 0.5rem;
            color: #9ca3af;
        }

        .breadcrumb-current {
            color: #374151;
            font-weight: 500;
        }

        .speech-controls {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 20px;
            background-color: #f0fdf4;
            padding: 12px;
            border-radius: 8px;
            border: 1px solid #bbf7d0;
        }

        .speech-btn {
            background-color: #2E8B57;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 6px;
            cursor: pointer;
            display: flex;
            align-items: center;
            gap: 6px;
            transition: all 0.2s;
        }

        .speech-btn:hover {
            background-color: #256f47;
        }

        .speech-btn:disabled {
            background-color: #a1a1aa;
            cursor: not-allowed;
        }

        .speech-rate-control {
            display: flex;
            align-items: center;
            gap: 6px;
        }

        .speech-rate-control label {
            font-size: 14px;
            color: #374151;
        }

        .speech-rate-control select {
            padding: 6px;
            border-radius: 4px;
            border: 1px solid #d1d5db;
        }

        .currently-reading {
            background-color: #fef08a;
            transition: background-color 0.3s;
        }

        .speech-btn.bg-yellow-500 {
            background-color: #eab308;
        }

        .speech-btn.bg-yellow-500:hover {
            background-color: #ca8a04;
        }

        .speech-btn:disabled {
            opacity: 0.7;
            cursor: not-allowed;
        }
    </style>
</head>

<body>
    <?php include 'navbar.php'; ?>
    <main class="container mx-auto px-4 py-8 mt-10 max-w-4xl">
        <!-- Breadcrumb Navigation -->
        <div class="breadcrumb">
            <a href="index.php">Home</a>
            <span class="breadcrumb-separator">></span>
            <a href="blogs.php">Blogs</a>
            <span class="breadcrumb-separator">></span>
            <span class="breadcrumb-current"><?= htmlspecialchars($blog['title']) ?></span>
        </div>
        <!-- Success/Error Messages -->
        <?php if (isset($_SESSION['error_msg'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <?= $_SESSION['error_msg'];
                unset($_SESSION['error_msg']); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success_msg'])): ?>
            <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded mb-6">
                <?= $_SESSION['success_msg'];
                unset($_SESSION['success_msg']); ?>
            </div>
        <?php endif; ?>

        <!-- Blog Content -->
        <div class="speech-controls">
            <button id="speechBtn" class="speech-btn">
                <i class="fas fa-play" id="speechIcon"></i>
                <span id="speechText">Listen to Article</span>
            </button>

            <div class="speech-rate-control">
                <label for="rateSelect">Speed:</label>
                <select id="rateSelect">
                    <option value="0.7">Slow</option>
                    <option value="1" selected>Normal</option>
                    <option value="1.3">Fast</option>
                </select>
            </div>

            <div id="speechStatus" class="text-sm text-gray-600"></div>
        </div>
        <article>
            <h1 class="text-3xl font-bold text-gray-800 mb-4"><?= htmlspecialchars($blog['title']) ?></h1>

            <div class="flex items-center mb-8">
                <img src="<?= htmlspecialchars($blog['profile_pic'] ?? 'default-user.png') ?>"
                    alt="<?= htmlspecialchars($blog['firstname'] . ' ' . $blog['lastname']) ?>"
                    class="w-12 h-12 rounded-full mr-4">
                <div>
                    <p class="font-medium"><?= htmlspecialchars($blog['firstname'] . ' ' . $blog['lastname']) ?></p>
                    <p class="text-sm text-gray-500">
                        Published on <?= date('F j, Y', strtotime($blog['published_at'])) ?>
                    </p>
                </div>
            </div>

            <?php if ($blog['featured_image']): ?>
                <img src="<?= htmlspecialchars($blog['featured_image']) ?>" alt="<?= htmlspecialchars($blog['title']) ?>" class="w-full rounded-lg mb-8">
            <?php endif; ?>

            <div class="content mb-8">
                <?= nl2br(htmlspecialchars($blog['content'])) ?>
            </div>

            <?php if ($blog['tags']): ?>
                <div class="flex flex-wrap gap-2 mb-8">
                    <?php foreach (explode(',', $blog['tags']) as $tag): ?>
                        <span class="tag"><?= htmlspecialchars(trim($tag)) ?></span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Social Sharing -->
            <div class="flex items-center gap-4 mb-12">
                <span class="text-gray-600">Share:</span>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?= urlencode("https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]") ?>"
                    target="_blank" class="text-blue-600 hover:text-blue-800">
                    <i class="fab fa-facebook text-2xl"></i>
                </a>
                <a href="https://twitter.com/intent/tweet?url=<?= urlencode("https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]") ?>&text=<?= urlencode($blog['title']) ?>"
                    target="_blank" class="text-blue-400 hover:text-blue-600">
                    <i class="fab fa-twitter text-2xl"></i>
                </a>
                <a href="https://www.linkedin.com/shareArticle?mini=true&url=<?= urlencode("https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]") ?>&title=<?= urlencode($blog['title']) ?>"
                    target="_blank" class="text-blue-700 hover:text-blue-900">
                    <i class="fab fa-linkedin text-2xl"></i>
                </a>
                <a href="mailto:?subject=<?= rawurlencode($blog['title']) ?>&body=Check%20out%20this%20blog:%20<?= urlencode("https://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]") ?>"
                    class="text-gray-600 hover:text-gray-800">
                    <i class="fas fa-envelope text-2xl"></i>
                </a>
            </div>
        </article>

        <!-- Comments Section -->
        <section class="mt-12 border-t pt-8">
            <h2 class="text-2xl font-bold mb-6">Comments (<?= count($comments) ?>)</h2>

            <!-- Comment Form -->
            <?php if (isset($_SESSION['user_id'])): ?>
                <form method="POST" class="mb-8">
                    <div class="flex items-start gap-4">
                        <img src="<?= htmlspecialchars($_SESSION['profile_pic'] ?? 'default-user.png') ?>"
                            alt="Your profile" class="w-10 h-10 rounded-full mt-1">
                        <div class="flex-1">
                            <textarea name="comment" rows="3"
                                class="comment-box w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:border-green-500"
                                placeholder="Write your comment..."></textarea>
                            <button type="submit"
                                class="mt-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700 transition">
                                Post Comment
                            </button>
                        </div>
                    </div>
                </form>
            <?php else: ?>
                <div class="bg-gray-100 p-4 rounded-lg mb-8 text-center">
                    <p class="mb-2">Please <a href="login.php" class="text-green-600 hover:underline">login</a> to leave a comment</p>
                </div>
            <?php endif; ?>

            <!-- Comments List -->
            <div class="space-y-6">
                <?php foreach ($comments as $comment): ?>
                    <div class="flex gap-4">
                        <img src="<?= htmlspecialchars($comment['profile_pic'] ?? 'default-user.png') ?>"
                            alt="<?= htmlspecialchars($comment['firstname'] . ' ' . $comment['lastname']) ?>"
                            class="w-10 h-10 rounded-full">
                        <div class="flex-1">
                            <div class="bg-gray-50 p-4 rounded-lg">
                                <div class="flex justify-between items-start mb-2">
                                    <h3 class="font-medium"><?= htmlspecialchars($comment['firstname'] . ' ' . $comment['lastname']) ?></h3>
                                    <span class="text-sm text-gray-500">
                                        <?= date('M j, Y', strtotime($comment['created_at'])) ?>
                                    </span>
                                </div>
                                <p><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>

                <?php if (empty($comments)): ?>
                    <p class="text-gray-500 text-center py-4">No comments yet. Be the first to comment!</p>
                <?php endif; ?>
            </div>
        </section>
    </main>

    <?php include 'footer.php'; ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const speechBtn = document.getElementById('speechBtn');
            const speechIcon = document.getElementById('speechIcon');
            const speechText = document.getElementById('speechText');
            const rateSelect = document.getElementById('rateSelect');
            const speechStatus = document.getElementById('speechStatus');
            const blogContent = document.querySelector('.content');

            // Check if speech synthesis is supported
            if (!('speechSynthesis' in window)) {
                speechBtn.disabled = true;
                speechStatus.textContent = "Text-to-speech not supported in your browser";
                return;
            }

            const synth = window.speechSynthesis;
            let utterance = null;
            let isPlaying = false;
            let voices = [];

            // Load available voices
            function loadVoices() {
                voices = synth.getVoices();
                if (voices.length === 0) {
                    setTimeout(loadVoices, 100);
                    return;
                }
            }

            // Load voices when they become available
            loadVoices();
            synth.onvoiceschanged = loadVoices;

            speechBtn.addEventListener('click', function() {
                // Required for Chrome - must be triggered by user interaction
                if (!isPlaying) {
                    startSpeech();
                } else {
                    stopSpeech();
                }
            });

            function startSpeech() {
                // If paused, resume
                if (synth.paused) {
                    synth.resume();
                    isPlaying = true;
                    updateButtonState();
                    speechStatus.textContent = "Reading...";
                    return;
                }

                // Create new utterance
                const text = blogContent.textContent || blogContent.innerText;
                utterance = new SpeechSynthesisUtterance(text);

                // Set utterance properties
                utterance.rate = parseFloat(rateSelect.value);
                utterance.pitch = 1;
                utterance.volume = 1;

                // Select a voice (prefer English female if available)
                const englishVoice = voices.find(voice =>
                    voice.lang.includes('en') && voice.name.includes('Female')
                ) || voices.find(voice => voice.lang.includes('en')) || voices[0];

                if (englishVoice) {
                    utterance.voice = englishVoice;
                }

                // Event handlers
                utterance.onstart = function() {
                    isPlaying = true;
                    updateButtonState();
                    speechStatus.textContent = "Reading...";
                };

                utterance.onend = function() {
                    isPlaying = false;
                    updateButtonState();
                    speechStatus.textContent = "";
                };

                utterance.onerror = function(event) {
                    console.error('SpeechSynthesis error:', event);
                    isPlaying = false;
                    updateButtonState();
                    speechStatus.textContent = "Error occurred";
                };

                utterance.onpause = function() {
                    isPlaying = false;
                    updateButtonState();
                    speechStatus.textContent = "Paused";
                };

                utterance.onresume = function() {
                    isPlaying = true;
                    updateButtonState();
                    speechStatus.textContent = "Reading...";
                };

                // Speak the text
                synth.speak(utterance);
            }

            function stopSpeech() {
                if (synth.speaking) {
                    synth.cancel();
                }
                isPlaying = false;
                updateButtonState();
                speechStatus.textContent = "";
            }

            function updateButtonState() {
                if (isPlaying) {
                    speechIcon.className = "fas fa-pause";
                    speechText.textContent = "Pause";
                    speechBtn.classList.add('bg-yellow-500', 'hover:bg-yellow-600');
                    speechBtn.classList.remove('bg-green-600', 'hover:bg-green-700');
                } else {
                    speechIcon.className = "fas fa-play";
                    speechText.textContent = "Listen to Article";
                    speechBtn.classList.add('bg-green-600', 'hover:bg-green-700');
                    speechBtn.classList.remove('bg-yellow-500', 'hover:bg-yellow-600');
                }
            }

            // Update rate when changed
            rateSelect.addEventListener('change', function() {
                if (utterance && isPlaying) {
                    utterance.rate = parseFloat(this.value);
                    if (synth.paused) {
                        synth.resume();
                    }
                }
            });

            // Clean up when leaving page
            window.addEventListener('beforeunload', function() {
                if (synth.speaking) {
                    synth.cancel();
                }
            });
        });
    </script>
</body>

</html>