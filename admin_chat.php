<?php

require_once 'db_connect.php';
session_start();
// Check admin authentication
if (!isset($_SESSION['admin_logged_in'])) {
    header('Location: admin_login.php');
    exit;
}

// Get admin info
$adminId = $_SESSION['admin_id'];
$stmt = $conn->prepare("SELECT username, role FROM admins WHERE admin_id = ?");
$stmt->bind_param("i", $adminId);
$stmt->execute();
$admin = $stmt->get_result()->fetch_assoc();

if (!$admin) {
    header('Location: admin_logout.php');
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Chat Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .chat-container {
            height: calc(100vh - 120px);
        }

        .session-list {
            height: calc(100vh - 120px);
            overflow-y: auto;
        }

        /* Updated chat messages container */
        .chat-messages-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            overflow: hidden;
        }

        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 1rem;
            min-height: 0;
            /* Fix for Firefox flexbox overflow */
        }

        .message-input-container {
            flex-shrink: 0;
            padding: 1rem;
            border-top: 1px solid #e5e7eb;
            background-color: white;
        }

        .unread-indicator {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background-color: #10B981;
        }

        /* Custom scrollbar styling */
        .chat-messages::-webkit-scrollbar {
            width: 8px;
        }

        .chat-messages::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 4px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: #c1c1c1;
            border-radius: 4px;
        }

        .chat-messages::-webkit-scrollbar-thumb:hover {
            background: #a8a8a8;
        }
    </style>
</head>

<body class="bg-gray-100">
    <div class="flex">
        <?php include 'admin_sidebar.php'; ?>

        <div class="flex-1 ml-80 p-6">
            <h1 class="text-2xl font-bold text-gray-800 mb-6">Live Chat Sessions</h1>

            <div class="bg-white rounded-lg shadow overflow-hidden">
                <div class="flex border-b border-gray-200">
                    <button id="activeSessionsTab" class="px-4 py-2 font-medium text-green-600 border-b-2 border-green-600">Active Sessions</button>
                    <button id="closedSessionsTab" class="px-4 py-2 font-medium text-gray-500 hover:text-gray-700">Closed Sessions</button>
                </div>

                <div class="flex chat-container">
                    <!-- Session List -->
                    <div class="w-1/3 border-r border-gray-200 session-list">
                        <div class="p-4">
                            <div class="relative">
                                <input type="text" id="sessionSearch" placeholder="Search sessions..." class="w-full px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                                <i class="fas fa-search absolute right-3 top-3 text-gray-400"></i>
                            </div>
                        </div>
                        <div id="sessionsList" class="divide-y divide-gray-200">
                            <!-- Sessions will be loaded here via JavaScript -->
                        </div>
                    </div>

                    <!-- Chat Area -->
                    <div class="flex-1 flex flex-col">
                        <div id="noSessionSelected" class="flex-1 flex items-center justify-center bg-gray-50">
                            <div class="text-center p-6">
                                <i class="fas fa-comments text-4xl text-gray-300 mb-4"></i>
                                <h3 class="text-lg font-medium text-gray-700">Select a chat session</h3>
                                <p class="text-gray-500 mt-1">Choose a session from the list to view messages</p>
                            </div>
                        </div>

                        <div id="chatArea" class="hidden flex flex-col h-full">
                            <!-- Chat Header -->
                            <div class="p-4 border-b border-gray-200 flex justify-between items-center bg-gray-50">
                                <div>
                                    <h3 id="chatUserName" class="font-medium text-gray-800"></h3>
                                    <p id="chatUserEmail" class="text-sm text-gray-500"></p>
                                </div>
                                <div class="flex space-x-2">
                                    <button id="closeSessionBtn" class="px-3 py-1 bg-red-100 text-red-700 rounded-md text-sm hover:bg-red-200 transition">
                                        End Session
                                    </button>
                                </div>
                            </div>

                            <!-- Messages -->
                            <div class="chat-messages-container">
                                <div id="chatMessages" class="chat-messages space-y-3">
                                    <!-- Messages will be loaded here -->
                                </div>

                                <!-- Message Input -->
                                <div class="message-input-container">
                                    <div class="flex space-x-2">
                                        <input type="text" id="adminMessageInput" placeholder="Type your message..." class="flex-1 px-4 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-green-500">
                                        <button id="adminSendBtn" class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 transition">
                                            Send
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const activeSessionsTab = document.getElementById('activeSessionsTab');
            const closedSessionsTab = document.getElementById('closedSessionsTab');
            const sessionsList = document.getElementById('sessionsList');
            const noSessionSelected = document.getElementById('noSessionSelected');
            const chatArea = document.getElementById('chatArea');
            const chatMessages = document.getElementById('chatMessages');
            const adminMessageInput = document.getElementById('adminMessageInput');
            const adminSendBtn = document.getElementById('adminSendBtn');
            const closeSessionBtn = document.getElementById('closeSessionBtn');
            const chatUserName = document.getElementById('chatUserName');
            const chatUserEmail = document.getElementById('chatUserEmail');
            const sessionSearch = document.getElementById('sessionSearch');

            let currentSessionId = null;
            let currentSessionStatus = 'active';
            let lastMessageId = 0;
            let messagePolling = null;

            // Load sessions based on tab
            function loadSessions(status) {
                currentSessionStatus = status;
                sessionsList.innerHTML = '<div class="p-4 text-center text-gray-500">Loading...</div>';

                const formData = new FormData();
                formData.append('action', 'admin_get_sessions');
                formData.append('status', status);

                fetch('chat_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            if (data.sessions.length === 0) {
                                sessionsList.innerHTML = '<div class="p-4 text-center text-gray-500">No ' + status + ' sessions found</div>';
                            } else {
                                sessionsList.innerHTML = '';
                                data.sessions.forEach(session => {
                                    const sessionElement = document.createElement('div');
                                    sessionElement.className = `p-4 cursor-pointer hover:bg-gray-50 ${currentSessionId === session.id ? 'bg-green-50' : ''}`;
                                    sessionElement.dataset.sessionId = session.id;

                                    sessionElement.innerHTML = `
                                <div class="flex justify-between items-start">
                                    <div>
                                        <h4 class="font-medium text-gray-800">${session.userName}</h4>
                                        <p class="text-sm text-gray-500">${session.userEmail}</p>
                                    </div>
                                    ${session.unreadCount > 0 ? '<div class="unread-indicator"></div>' : ''}
                                </div>
                                <div class="flex justify-between items-center mt-2">
                                    <span class="text-xs text-gray-500">${new Date(session.lastActivity).toLocaleString()}</span>
                                    <span class="text-xs ${session.status === 'active' ? 'text-green-600' : 'text-gray-500'}">${session.status}</span>
                                </div>
                            `;

                                    sessionElement.addEventListener('click', () => selectSession(session.id, session.userName, session.userEmail));
                                    sessionsList.appendChild(sessionElement);
                                });
                            }
                        } else {
                            sessionsList.innerHTML = '<div class="p-4 text-center text-gray-500">Failed to load sessions</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        sessionsList.innerHTML = '<div class="p-4 text-center text-gray-500">Error loading sessions</div>';
                    });
            }

            // Select a session
            function selectSession(sessionId, userName, userEmail) {
                currentSessionId = sessionId;
                chatUserName.textContent = userName;
                chatUserEmail.textContent = userEmail;

                // Update UI
                noSessionSelected.classList.add('hidden');
                chatArea.classList.remove('hidden');

                // Highlight selected session
                document.querySelectorAll('#sessionsList > div').forEach(el => {
                    if (el.dataset.sessionId === sessionId) {
                        el.classList.add('bg-green-50');
                    } else {
                        el.classList.remove('bg-green-50');
                    }
                });

                // Load messages for this session
                loadMessages();

                // Start polling for new messages
                startPolling();
            }

            function addMessageToChat(sender, message) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `flex ${sender === 'admin' ? 'justify-end' : 'justify-start'}`;

                const messageBubble = document.createElement('div');
                messageBubble.className = `max-w-xs lg:max-w-md px-4 py-2 rounded-lg ${sender === 'admin' ? 'bg-green-100 text-gray-800' : 'bg-gray-200 text-gray-800'}`;
                messageBubble.textContent = message;

                messageDiv.appendChild(messageBubble);
                chatMessages.appendChild(messageDiv);

                // Scroll to bottom smoothly
                chatMessages.scrollTo({
                    top: chatMessages.scrollHeight,
                    behavior: 'smooth'
                });
            }

            // Load messages for selected session
            function loadMessages() {
                if (!currentSessionId) return;

                chatMessages.innerHTML = '<div class="text-center py-4 text-gray-500">Loading messages...</div>';

                const formData = new FormData();
                formData.append('action', 'admin_get_messages');
                formData.append('sessionId', currentSessionId);

                fetch('chat_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            chatMessages.innerHTML = '';
                            lastMessageId = 0;

                            if (data.messages.length === 0) {
                                chatMessages.innerHTML = '<div class="text-center py-4 text-gray-500">No messages yet</div>';
                            } else {
                                data.messages.forEach(msg => {
                                    addMessageToChat(msg.sender, msg.message);
                                    lastMessageId = Math.max(lastMessageId, msg.id);
                                });
                            }
                        } else {
                            chatMessages.innerHTML = '<div class="text-center py-4 text-gray-500">Failed to load messages</div>';
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        chatMessages.innerHTML = '<div class="text-center py-4 text-gray-500">Error loading messages</div>';
                    });
            }

            // Add message to chat UI
            function addMessageToChat(sender, message) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `flex ${sender === 'admin' ? 'justify-end' : 'justify-start'}`;

                const messageBubble = document.createElement('div');
                messageBubble.className = `max-w-xs lg:max-w-md px-4 py-2 rounded-lg ${sender === 'admin' ? 'bg-green-100 text-gray-800' : 'bg-gray-200 text-gray-800'}`;
                messageBubble.textContent = message;

                messageDiv.appendChild(messageBubble);
                chatMessages.appendChild(messageDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            // Send message
            function sendMessage() {
                const message = adminMessageInput.value.trim();
                if (!message || !currentSessionId) return;

                adminMessageInput.disabled = true;
                adminSendBtn.disabled = true;

                const formData = new FormData();
                formData.append('action', 'admin_send_message');
                formData.append('sessionId', currentSessionId);
                formData.append('message', message);

                fetch('chat_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            addMessageToChat('admin', message);
                            adminMessageInput.value = '';

                            // Reload sessions to update unread counts
                            loadSessions(currentSessionStatus);
                        } else {
                            alert('Failed to send message: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to send message');
                    })
                    .finally(() => {
                        adminMessageInput.disabled = false;
                        adminSendBtn.disabled = false;
                        adminMessageInput.focus();
                    });
            }

            // Close session
            // Replace the existing closeSession function with this improved version
            function closeSession() {
                if (!currentSessionId) return;

                if (!confirm('Are you sure you want to end this chat session? The user will no longer be able to send messages.')) {
                    return;
                }

                // Disable button immediately to prevent multiple clicks
                closeSessionBtn.disabled = true;
                closeSessionBtn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i> Ending...';

                // Stop polling before closing to prevent interference
                stopPolling();

                const formData = new FormData();
                formData.append('action', 'admin_close_session');
                formData.append('sessionId', currentSessionId);

                fetch('chat_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (data.success) {
                            // Update UI immediately
                            noSessionSelected.classList.remove('hidden');
                            chatArea.classList.add('hidden');

                            // Force reload sessions to reflect the closed status
                            loadSessions(currentSessionStatus);

                            // Reset current session
                            currentSessionId = null;

                            // Show success feedback
                            showToast('Session ended successfully', 'success');
                        } else {
                            throw new Error(data.error || 'Failed to close session');
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        showToast(error.message, 'error');
                        // Restart polling if close failed
                        if (currentSessionId) {
                            startPolling();
                        }
                    })
                    .finally(() => {
                        // Reset button state
                        closeSessionBtn.disabled = false;
                        closeSessionBtn.textContent = 'End Session';
                    });
            }

            // Add this helper function for toast notifications
            function showToast(message, type = 'info') {
                const toast = document.createElement('div');
                toast.className = `fixed bottom-4 right-4 px-4 py-2 rounded-md shadow-lg text-white ${
                    type === 'error' ? 'bg-red-500' : 
                    type === 'success' ? 'bg-green-500' : 'bg-blue-500'
                }`;
                toast.textContent = message;
                document.body.appendChild(toast);

                setTimeout(() => {
                    toast.remove();
                }, 3000);
            }

            // Poll for new messages
            function startPolling() {
                // Clear any existing polling first
                stopPolling();

                // Initial poll
                pollMessages();

                // Set up new interval
                messagePolling = setInterval(pollMessages, 2000);
            }

            function stopPolling() {
                if (messagePolling) {
                    clearInterval(messagePolling);
                    messagePolling = null;
                }
            }

            // Update the closeSession function to call stopPolling() first (already shown above)

            function pollMessages() {
                if (!currentSessionId) return;

                const formData = new FormData();
                formData.append('action', 'admin_get_messages');
                formData.append('sessionId', currentSessionId);

                fetch('chat_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.messages.length > 0) {
                            // Check if we have new messages
                            const newMessages = data.messages.filter(msg => msg.id > lastMessageId);

                            if (newMessages.length > 0) {
                                newMessages.forEach(msg => {
                                    addMessageToChat(msg.sender, msg.message);
                                    lastMessageId = Math.max(lastMessageId, msg.id);
                                });

                                // Reload sessions to update unread counts
                                loadSessions(currentSessionStatus);
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error polling messages:', error);
                    });
            }

            // Event listeners
            activeSessionsTab.addEventListener('click', () => {
                activeSessionsTab.classList.add('text-green-600', 'border-green-600');
                closedSessionsTab.classList.remove('text-green-600', 'border-green-600');
                loadSessions('active');
            });

            closedSessionsTab.addEventListener('click', () => {
                closedSessionsTab.classList.add('text-green-600', 'border-green-600');
                activeSessionsTab.classList.remove('text-green-600', 'border-green-600');
                loadSessions('closed');
            });

            adminSendBtn.addEventListener('click', sendMessage);
            adminMessageInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendMessage();
                }
            });

            closeSessionBtn.addEventListener('click', closeSession);

            // Search sessions
            sessionSearch.addEventListener('input', function() {
                const searchTerm = this.value.toLowerCase();
                document.querySelectorAll('#sessionsList > div').forEach(session => {
                    const userName = session.querySelector('h4').textContent.toLowerCase();
                    const userEmail = session.querySelector('p').textContent.toLowerCase();
                    if (userName.includes(searchTerm) || userEmail.includes(searchTerm)) {
                        session.style.display = 'block';
                    } else {
                        session.style.display = 'none';
                    }
                });
            });

            // Initial load
            loadSessions('active');
        });
    </script>
</body>

</html>