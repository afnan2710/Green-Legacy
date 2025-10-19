<!-- Chat Widget -->
    <div id="chatWidget" class="fixed bottom-6 right-6 z-50">
        <!-- Chat Button -->
        <button id="chatButton" class="bg-green-600 hover:bg-green-700 text-white rounded-full p-4 shadow-lg transition-all duration-300 transform hover:scale-110">
            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z" />
            </svg>
        </button>

        <!-- Chat Modal -->
        <div id="chatModal" class="hidden fixed bottom-24 right-6 w-96 bg-white rounded-lg shadow-xl overflow-hidden flex flex-col" style="height: 470px;">
            <!-- Chat Header -->
            <div class="bg-green-600 text-white p-4 flex justify-between items-center">
                <h3 class="font-semibold text-lg">Live Chat</h3>
                <div class="flex space-x-2">
                    <button id="minimizeChat" class="text-white hover:text-green-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M5 10a1 1 0 011-1h8a1 1 0 110 2H6a1 1 0 01-1-1z" clip-rule="evenodd" />
                        </svg>
                    </button>
                    <button id="closeChat" class="text-white hover:text-green-200">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>
            </div>

            <!-- Chat Content -->
            <div class="flex-1 flex flex-col overflow-hidden">
                <!-- Initial Form -->
                <div id="chatInitForm" class="p-4 flex-1 flex flex-col">
                    <h4 class="text-lg font-medium mb-4">Start a conversation</h4>
                    <div class="space-y-4 flex-1">
                        <div>
                            <label for="chatName" class="block text-sm font-medium text-gray-700">Your Name</label>
                            <input type="text" id="chatName" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500">
                        </div>
                        <div>
                            <label for="chatEmail" class="block text-sm font-medium text-gray-700">Email</label>
                            <input type="email" id="chatEmail" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500">
                        </div>
                        <div class="flex-1">
                            <label for="initialMessage" class="block text-sm font-medium text-gray-700">Type your Message/Complaints</label>
                            <textarea id="initialMessage" rows="3" class="mt-1 block w-full border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500"></textarea>
                        </div>
                    </div>
                    <button id="startChatBtn" class="mt-4 w-full bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-md transition duration-200">
                        Start Chat
                    </button>
                </div>

                <!-- Chat Interface (hidden initially) -->
                <div id="chatInterface" class="hidden flex-1 flex flex-col">
                    <!-- Messages Container -->
                    <div id="chatMessages" class="flex-1 overflow-y-auto p-4 space-y-3" style="max-height: 300px;"></div>

                    <!-- Message Input -->
                    <div class="border-t border-gray-200 p-4">
                        <div class="flex space-x-2">
                            <input type="text" id="chatMessageInput" placeholder="Type your message..." class="flex-1 border border-gray-300 rounded-md shadow-sm py-2 px-3 focus:outline-none focus:ring-green-500 focus:border-green-500">
                            <button id="sendMessageBtn" class="bg-green-600 hover:bg-green-700 text-white py-2 px-4 rounded-md transition duration-200">
                                Send
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Minimized Chat (hidden initially) -->
    <div id="minimizedChat" class="hidden fixed bottom-6 right-6 bg-green-600 text-white rounded-t-lg shadow-lg overflow-hidden z-50">
        <div class="px-4 py-2 flex justify-between items-center cursor-pointer" id="restoreChat">
            <span class="font-medium">Live Chat</span>
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd" />
            </svg>
        </div>
    </div>

    <script>
        // Chat Widget Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const chatButton = document.getElementById('chatButton');
            const chatModal = document.getElementById('chatModal');
            const minimizedChat = document.getElementById('minimizedChat');
            const closeChat = document.getElementById('closeChat');
            const minimizeChat = document.getElementById('minimizeChat');
            const restoreChat = document.getElementById('restoreChat');
            const chatInitForm = document.getElementById('chatInitForm');
            const chatInterface = document.getElementById('chatInterface');
            const startChatBtn = document.getElementById('startChatBtn');
            const sendMessageBtn = document.getElementById('sendMessageBtn');
            const chatMessageInput = document.getElementById('chatMessageInput');
            const chatMessages = document.getElementById('chatMessages');

            let chatSessionId = null;
            let lastMessageId = 0;
            let messagePolling = null;

            // Toggle chat modal
            chatButton.addEventListener('click', function() {
                chatModal.classList.remove('hidden');
                minimizedChat.classList.add('hidden');
            });

            // Close chat
            closeChat.addEventListener('click', function() {
                chatModal.classList.add('hidden');
                stopPolling();
            });

            // Minimize chat
            minimizeChat.addEventListener('click', function() {
                chatModal.classList.add('hidden');
                minimizedChat.classList.remove('hidden');
                stopPolling();
            });

            // Restore chat from minimized state
            restoreChat.addEventListener('click', function() {
                minimizedChat.classList.add('hidden');
                chatModal.classList.remove('hidden');
                if (chatSessionId) {
                    startPolling();
                }
            });

            // Start new chat session
            startChatBtn.addEventListener('click', function() {
                const name = document.getElementById('chatName').value.trim();
                const email = document.getElementById('chatEmail').value.trim();
                const message = document.getElementById('initialMessage').value.trim();

                if (!name || !email) {
                    alert('Please enter your name and email');
                    return;
                }

                if (!validateEmail(email)) {
                    alert('Please enter a valid email address');
                    return;
                }

                startChatBtn.disabled = true;
                startChatBtn.textContent = 'Starting...';

                const formData = new FormData();
                formData.append('action', 'start_chat');
                formData.append('name', name);
                formData.append('email', email);
                if (message) formData.append('message', message);

                fetch('chat_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            chatSessionId = data.sessionId;
                            chatInitForm.classList.add('hidden');
                            chatInterface.classList.remove('hidden');

                            // Add initial message if provided
                            if (message) {
                                addMessageToChat('user', message);
                            }

                            // Start polling for new messages
                            startPolling();
                        } else {
                            alert('Failed to start chat: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to start chat');
                    })
                    .finally(() => {
                        startChatBtn.disabled = false;
                        startChatBtn.textContent = 'Start Chat';
                    });
            });

            // Send message
            function sendMessage() {
                const message = chatMessageInput.value.trim();
                if (!message || !chatSessionId) return;

                chatMessageInput.disabled = true;
                sendMessageBtn.disabled = true;

                const formData = new FormData();
                formData.append('action', 'send_message');
                formData.append('sessionId', chatSessionId);
                formData.append('message', message);

                fetch('chat_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            addMessageToChat('user', message);
                            chatMessageInput.value = '';
                        } else {
                            alert('Failed to send message: ' + (data.error || 'Unknown error'));
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('Failed to send message');
                    })
                    .finally(() => {
                        chatMessageInput.disabled = false;
                        sendMessageBtn.disabled = false;
                        chatMessageInput.focus();
                    });
            }

            // Send message on button click or Enter key
            sendMessageBtn.addEventListener('click', sendMessage);
            chatMessageInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    sendMessage();
                }
            });

            // Add message to chat UI
            function addMessageToChat(sender, message) {
                const messageDiv = document.createElement('div');
                messageDiv.className = `flex ${sender === 'user' ? 'justify-end' : 'justify-start'}`;

                const messageBubble = document.createElement('div');
                messageBubble.className = `max-w-xs lg:max-w-md px-4 py-2 rounded-lg ${sender === 'user' ? 'bg-green-100 text-gray-800' : 'bg-gray-200 text-gray-800'}`;
                messageBubble.textContent = message;

                messageDiv.appendChild(messageBubble);
                chatMessages.appendChild(messageDiv);
                chatMessages.scrollTop = chatMessages.scrollHeight;
            }

            // Poll for new messages
            function startPolling() {
                stopPolling();
                pollMessages(); // Initial poll
                messagePolling = setInterval(pollMessages, 2000); // Poll every 2 seconds
            }

            function stopPolling() {
                if (messagePolling) {
                    clearInterval(messagePolling);
                    messagePolling = null;
                }
            }

            function pollMessages() {
                if (!chatSessionId) return;

                const formData = new FormData();
                formData.append('action', 'get_messages');
                formData.append('sessionId', chatSessionId);
                formData.append('lastMessageId', lastMessageId);

                fetch('chat_handler.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            // Add new messages to chat
                            data.messages.forEach(msg => {
                                addMessageToChat(msg.sender, msg.message);
                                lastMessageId = Math.max(lastMessageId, msg.id);
                            });

                            // Check if session was closed by admin
                            if (!data.sessionActive) {
                                chatMessageInput.disabled = true;
                                sendMessageBtn.disabled = true;
                                chatMessageInput.placeholder = 'This chat session has ended';
                                stopPolling();
                            }
                        }
                    })
                    .catch(error => {
                        console.error('Error polling messages:', error);
                    });
            }

            // Simple email validation
            function validateEmail(email) {
                const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                return re.test(email);
            }
        });
    </script>