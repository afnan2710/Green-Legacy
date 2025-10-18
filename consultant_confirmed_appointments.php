<?php
require_once 'db_connect.php';

// Check consultant authentication
session_start();
if (!isset($_SESSION['consultant_logged_in']) || $_SESSION['consultant_logged_in'] !== true) {
    header('Location: consultant_login.php');
    exit;
}

$consultant_id = $_SESSION['consultant_id'];

// Get confirmed appointments
$appointments = [];
try {
    $stmt = $conn->prepare("SELECT 
        a.id, a.user_id, a.date, a.time_slot, a.status, 
        a.notes, a.created_at,
        u.firstname, u.lastname, u.email, u.phone
        FROM appointments a
        JOIN users u ON a.user_id = u.id
        WHERE a.consultant_id = ? 
        AND a.status = 'confirmed'
        ORDER BY a.date ASC, a.time_slot ASC");

    $stmt->bind_param("i", $consultant_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $appointments = $result->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
} catch (Exception $e) {
    error_log("Error fetching appointments: " . $e->getMessage());
    $_SESSION['error_message'] = "Error loading appointments. Please try again later.";
}

// Time slots configuration
$time_slots = [
    '09:00 - 10:00',
    '10:00 - 11:00',
    '11:00 - 12:00',
    '13:00 - 14:00',
    '14:00 - 15:00',
    '15:00 - 16:00',
    '16:00 - 17:00'
];
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirmed Appointments - Consultant Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        .main-content {
            margin-left: 280px;
            padding: 2rem;
            transition: margin-left 0.3s ease;
        }

        .status-confirmed {
            background-color: #d1fae5;
            color: #065f46;
        }

        .badge {
            display: inline-block;
            padding: 0.25em 0.4em;
            font-size: 75%;
            font-weight: 700;
            line-height: 1;
            text-align: center;
            white-space: nowrap;
            vertical-align: baseline;
            border-radius: 0.25rem;
        }

        .schedule-table {
            width: 100%;
            border-collapse: collapse;
        }

        .schedule-table th,
        .schedule-table td {
            padding: 12px 15px;
            text-align: left;
            border-bottom: 1px solid #e2e8f0;
        }

        .schedule-table th {
            background-color: #f7fafc;
            font-weight: 600;
            color: #4a5568;
        }
    </style>
</head>

<body class="bg-gray-100">
    <?php include 'consultant_sidebar.php'; ?>
    <div class="main-content">
        <div class="flex justify-between items-center mb-8">
            <h1 class="text-3xl font-bold text-gray-800">Confirmed Appointments</h1>
            <div class="text-sm text-gray-500">
                <i class="far fa-calendar-alt mr-1"></i>
                <?php echo date('l, F j, Y'); ?>
            </div>
        </div>

        <?php if (isset($_SESSION['error_message'])): ?>
            <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded mb-6">
                <div class="flex justify-between items-center">
                    <span><?php echo $_SESSION['error_message'];
                            unset($_SESSION['error_message']); ?></span>
                    <button onclick="this.parentElement.parentElement.remove()" class="text-red-700 hover:text-red-900">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <?php if (empty($appointments)): ?>
            <div class="text-center py-12 text-gray-500">
                <i class="fas fa-calendar-check text-4xl mb-3 text-gray-300"></i>
                <p class="text-lg">No confirmed appointments</p>
                <p class="text-sm mt-2">When you have confirmed appointments, they will appear here.</p>
            </div>
        <?php else: ?>
            <div class="overflow-x-auto rounded-lg border border-gray-200">
                <table class="schedule-table min-w-full divide-y divide-gray-200">
                    <thead class="bg-gray-50">
                        <tr>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Time</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Client</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Contact</th>
                            <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="bg-white divide-y divide-gray-200">
                        <?php foreach ($appointments as $appointment): ?>
                            <tr class="hover:bg-gray-50">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm font-medium text-gray-900"><?php echo date('M j, Y', strtotime($appointment['date'])); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo date('D', strtotime($appointment['date'])); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo $time_slots[$appointment['time_slot']]; ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="flex-shrink-0 h-10 w-10 bg-blue-100 rounded-full flex items-center justify-center">
                                            <span class="text-blue-600 font-medium"><?php echo strtoupper(substr($appointment['firstname'], 0, 1) . substr($appointment['lastname'], 0, 1)); ?></span>
                                        </div>
                                        <div class="ml-4">
                                            <div class="text-sm font-medium text-gray-900"><?php echo htmlspecialchars($appointment['firstname'] . ' ' . $appointment['lastname']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="text-sm text-gray-900"><?php echo htmlspecialchars($appointment['email']); ?></div>
                                    <div class="text-sm text-gray-500"><?php echo htmlspecialchars($appointment['phone']); ?></div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-sm font-medium">
                                    <button onclick="startCall(<?php echo $appointment['id']; ?>)"
                                        class="px-4 py-2 bg-green-600 text-white rounded-md hover:bg-green-700 flex items-center">
                                        <i class="fas fa-video mr-2"></i> Start Video Call
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>

    <!-- Video Call Modal -->
    <div id="videoCallModal" class="fixed inset-0 z-50 hidden bg-black bg-opacity-50 flex items-center justify-center p-4">
        <div class="bg-white rounded-lg shadow-xl w-full max-w-4xl h-[80vh] flex flex-col">
            <!-- Header -->
            <div class="bg-green-600 text-white p-4 rounded-t-lg">
                <div class="flex justify-between items-center mb-2">
                    <h3 class="text-xl font-semibold">Video Consultation</h3>
                    <div class="flex items-center" id="connectionStatus">
                        <span class="w-3 h-3 bg-yellow-400 rounded-full mr-2 animate-pulse"></span>
                        <span class="text-sm">Connecting...</span>
                    </div>
                    <button onclick="endCall()" class="text-white hover:text-gray-200">
                        <i class="fas fa-times text-xl"></i>
                    </button>
                </div>
                <div class="text-sm bg-blue-100 p-2 rounded text-gray-800 hidden" id="sessionInfo">
                    <strong>Session ID:</strong> <span id="sessionIdDisplay"></span><br>
                    <strong>Appointment:</strong> #<span id="sessionCode"></span>
                </div>
            </div>

            <!-- Video Area -->
            <div class="flex-1 flex flex-col md:flex-row p-4 gap-4 bg-gray-800">
                <!-- Remote Video (User) - Main View -->
                <div class="flex-1 bg-black rounded-lg overflow-hidden relative">
                    <video id="remote-video" autoplay playsinline class="w-full h-full object-cover"></video>
                    <div id="remote-video-placeholder" class="absolute inset-0 flex items-center justify-center text-white">
                        <div class="text-center">
                            <i class="fas fa-user fa-5x opacity-50 mb-4"></i>
                            <p>Waiting for user to join...</p>
                        </div>
                    </div>
                </div>

                <!-- Local Video (Consultant) - Small PIP -->
                <div class="absolute bottom-4 right-4 md:relative md:bottom-auto md:right-auto md:w-1/4">
                    <div class="bg-black rounded-lg overflow-hidden border-2 border-white shadow-lg w-40 h-30 md:w-full md:h-full">
                        <video id="local-video" autoplay playsinline muted class="w-full h-full object-cover"></video>
                    </div>
                </div>
            </div>

            <!-- Controls -->
            <div class="bg-gray-100 p-3 flex justify-center space-x-4 rounded-b-lg">
                <button id="muteBtn" onclick="toggleMute()"
                    class="p-3 bg-white text-gray-700 rounded-full hover:bg-gray-200 flex items-center justify-center shadow">
                    <i class="fas fa-microphone"></i>
                </button>
                <button id="videoBtn" onclick="toggleVideo()"
                    class="p-3 bg-white text-gray-700 rounded-full hover:bg-gray-200 flex items-center justify-center shadow">
                    <i class="fas fa-video"></i>
                </button>
                <button onclick="endCall()"
                    class="p-3 bg-red-600 text-white rounded-full hover:bg-red-700 flex items-center justify-center shadow">
                    <i class="fas fa-phone"></i>
                </button>
            </div>
        </div>
    </div>
</body>
<script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
<script>
    // Declare variables at the top
    let localStream;
    let remoteStream;
    let peerConnection;
    let isCallActive = false;
    let currentSessionId = null;
    let socket;

    // WebRTC configuration
    const configuration = {
        iceServers: [{
                urls: 'stun:stun.l.google.com:19302'
            },
            {
                urls: 'stun:stun1.l.google.com:19302'
            }
        ]
    };

    // Initialize socket connection when page loads
    document.addEventListener('DOMContentLoaded', function() {
        socket = io('http://localhost:3001');

        // Socket event handlers
        socket.on('connect', () => {
            console.log('Connected to signaling server');
        });

        socket.on('offer', async (data) => {
            console.log('Received offer from user');
            await handleOffer(data.offer);
        });

        socket.on('answer', async (data) => {
            console.log('Received answer from user');
            await handleAnswer(data.answer);
        });

        socket.on('ice-candidate', async (data) => {
            console.log('Received ICE candidate from user');
            await handleNewICECandidate(data.candidate);
        });

        socket.on('user-joined', (data) => {
            console.log('User joined the call:', data);
            // When user joins, create a new offer if we're the consultant
            if (isCallActive && data.userType === 'user') {
                console.log('User joined, creating new offer...');
                setTimeout(async () => {
                    await createOffer();
                }, 1000);
            }
        });

        socket.on('call-ended', (data) => {
            console.log('Call ended by:', data.endedBy);
            endCall();
            alert(`Call ended by ${data.endedBy}`);
        });

        socket.on('user-left', (data) => {
            console.log('User left:', data);
            alert(`User left the call`);
            endCall();
        });

        socket.on('connect_error', (error) => {
            console.error('Socket connection error:', error);
            alert('Failed to connect to video server. Please make sure the signaling server is running on port 3001.');
        });
    });

    // Start video call
    async function startCall(appointmentId) {
        try {
            // Check if socket is connected
            if (!socket || !socket.connected) {
                throw new Error('Not connected to video server. Please refresh the page and try again.');
            }

            // Use consistent session ID based on appointment ID only
            currentSessionId = `session_${appointmentId}`;

            console.log('Starting call with session:', currentSessionId);

            // Show session info
            document.getElementById('sessionIdDisplay').textContent = currentSessionId;
            document.getElementById('sessionCode').textContent = currentSessionId;
            document.getElementById('sessionInfo').classList.remove('hidden');

            // Initialize media
            await initializeMedia();

            // Create peer connection
            createPeerConnection();

            // Show modal
            document.getElementById('videoCallModal').classList.remove('hidden');

            // Join session as consultant
            socket.emit('join-session', {
                sessionId: currentSessionId,
                userType: 'consultant',
                userId: <?php echo $consultant_id; ?>,
                appointmentId: appointmentId
            });

            isCallActive = true;

            // Create offer immediately
            setTimeout(async () => {
                await createOffer();
            }, 1000);

        } catch (error) {
            console.error('Error starting call:', error);
            alert('Error starting video call: ' + error.message);
        }
    }

    // Initialize media devices
    async function initializeMedia() {
        try {
            localStream = await navigator.mediaDevices.getUserMedia({
                video: {
                    width: {
                        ideal: 1280
                    },
                    height: {
                        ideal: 720
                    }
                },
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true
                }
            });

            const localVideo = document.getElementById('local-video');
            localVideo.srcObject = localStream;

            console.log('Local media initialized');

        } catch (error) {
            console.error('Error accessing media devices:', error);
            throw new Error('Could not access camera or microphone. Please check your permissions.');
        }
    }

    // Create peer connection
    function createPeerConnection() {
        try {
            peerConnection = new RTCPeerConnection(configuration);

            // Add local stream to connection
            localStream.getTracks().forEach(track => {
                console.log('Adding local track:', track.kind);
                peerConnection.addTrack(track, localStream);
            });

            // Handle remote stream
            peerConnection.ontrack = (event) => {
                console.log('Received remote track:', event.track.kind);
                remoteStream = event.streams[0];
                const remoteVideo = document.getElementById('remote-video');
                remoteVideo.srcObject = remoteStream;
                document.getElementById('remote-video-placeholder').classList.add('hidden');

                // Play the remote video
                remoteVideo.play().catch(e => console.error('Error playing remote video:', e));
            };

            // Handle ICE candidates
            peerConnection.onicecandidate = (event) => {
                if (event.candidate) {
                    console.log('Sending ICE candidate');
                    socket.emit('ice-candidate', {
                        sessionId: currentSessionId,
                        candidate: event.candidate
                    });
                } else {
                    console.log('All ICE candidates sent');
                }
            };

            peerConnection.onconnectionstatechange = () => {
                console.log('Connection state:', peerConnection.connectionState);
                updateConnectionStatus();
            };

            peerConnection.oniceconnectionstatechange = () => {
                console.log('ICE connection state:', peerConnection.iceConnectionState);
            };

            console.log('Peer connection created');

        } catch (error) {
            console.error('Error creating peer connection:', error);
            throw error;
        }
    }

    // Update connection status display
    function updateConnectionStatus() {
        const statusElement = document.getElementById('connectionStatus');
        if (statusElement && peerConnection) {
            let statusText = '';
            let statusColor = '';

            switch (peerConnection.connectionState) {
                case 'connected':
                    statusText = 'Connected to User';
                    statusColor = 'bg-green-400';
                    break;
                case 'connecting':
                    statusText = 'Connecting...';
                    statusColor = 'bg-yellow-400';
                    break;
                case 'disconnected':
                    statusText = 'Disconnected';
                    statusColor = 'bg-red-400';
                    break;
                case 'failed':
                    statusText = 'Connection Failed';
                    statusColor = 'bg-red-400';
                    break;
                default:
                    statusText = peerConnection.connectionState;
                    statusColor = 'bg-gray-400';
            }

            statusElement.innerHTML = `
            <span class="w-3 h-3 ${statusColor} rounded-full mr-2 ${peerConnection.connectionState === 'connecting' ? 'animate-pulse' : ''}"></span>
            <span class="text-sm">${statusText}</span>
        `;
        }
    }

    // Create and send offer
    async function createOffer() {
        try {
            console.log('Creating offer...');
            const offer = await peerConnection.createOffer({
                offerToReceiveAudio: true,
                offerToReceiveVideo: true
            });

            await peerConnection.setLocalDescription(offer);
            console.log('Offer created, sending to signaling server');

            socket.emit('offer', {
                sessionId: currentSessionId,
                offer: offer
            });

        } catch (error) {
            console.error('Error creating offer:', error);
        }
    }

    // Handle incoming offer
    async function handleOffer(offer) {
        try {
            console.log('Setting remote description (offer)');
            await peerConnection.setRemoteDescription(offer);

            console.log('Creating answer...');
            const answer = await peerConnection.createAnswer();
            await peerConnection.setLocalDescription(answer);

            console.log('Answer created, sending to signaling server');
            socket.emit('answer', {
                sessionId: currentSessionId,
                answer: answer
            });

        } catch (error) {
            console.error('Error handling offer:', error);
        }
    }

    // Handle incoming answer
    async function handleAnswer(answer) {
        try {
            console.log('Setting remote description (answer)');
            await peerConnection.setRemoteDescription(answer);
            console.log('Remote description set successfully');
        } catch (error) {
            console.error('Error handling answer:', error);
        }
    }

    // Handle new ICE candidate
    async function handleNewICECandidate(candidate) {
        try {
            console.log('Adding ICE candidate');
            await peerConnection.addIceCandidate(candidate);
        } catch (error) {
            console.error('Error adding ICE candidate:', error);
        }
    }

    // Toggle audio mute
    function toggleMute() {
        if (localStream) {
            const audioTracks = localStream.getAudioTracks();
            audioTracks.forEach(track => {
                track.enabled = !track.enabled;
            });

            const muteBtn = document.getElementById('muteBtn');
            if (audioTracks[0].enabled) {
                muteBtn.innerHTML = '<i class="fas fa-microphone"></i>';
                muteBtn.classList.remove('bg-red-600', 'text-white');
                muteBtn.classList.add('bg-white', 'text-gray-700');
            } else {
                muteBtn.innerHTML = '<i class="fas fa-microphone-slash"></i>';
                muteBtn.classList.remove('bg-white', 'text-gray-700');
                muteBtn.classList.add('bg-red-600', 'text-white');
            }
        }
    }

    // Toggle video
    function toggleVideo() {
        if (localStream) {
            const videoTracks = localStream.getVideoTracks();
            videoTracks.forEach(track => {
                track.enabled = !track.enabled;
            });

            const videoBtn = document.getElementById('videoBtn');
            if (videoTracks[0].enabled) {
                videoBtn.innerHTML = '<i class="fas fa-video"></i>';
                videoBtn.classList.remove('bg-red-600', 'text-white');
                videoBtn.classList.add('bg-white', 'text-gray-700');
            } else {
                videoBtn.innerHTML = '<i class="fas fa-video-slash"></i>';
                videoBtn.classList.remove('bg-white', 'text-gray-700');
                videoBtn.classList.add('bg-red-600', 'text-white');
            }
        }
    }

    // End call
    function endCall() {
        console.log('Ending call...');

        if (isCallActive && socket) {
            // Send end call signal
            socket.emit('end-call', {
                sessionId: currentSessionId
            });
        }

        // Close peer connection
        if (peerConnection) {
            peerConnection.close();
            peerConnection = null;
        }

        // Stop media streams
        if (localStream) {
            localStream.getTracks().forEach(track => track.stop());
            localStream = null;
        }

        if (remoteStream) {
            remoteStream.getTracks().forEach(track => track.stop());
            remoteStream = null;
        }

        // Reset UI
        document.getElementById('videoCallModal').classList.add('hidden');
        document.getElementById('remote-video-placeholder').classList.remove('hidden');
        document.getElementById('sessionInfo').classList.add('hidden');

        const localVideo = document.getElementById('local-video');
        const remoteVideo = document.getElementById('remote-video');
        localVideo.srcObject = null;
        remoteVideo.srcObject = null;

        // Reset buttons
        const muteBtn = document.getElementById('muteBtn');
        const videoBtn = document.getElementById('videoBtn');

        muteBtn.innerHTML = '<i class="fas fa-microphone"></i>';
        muteBtn.classList.remove('bg-red-600', 'text-white');
        muteBtn.classList.add('bg-white', 'text-gray-700');

        videoBtn.innerHTML = '<i class="fas fa-video"></i>';
        videoBtn.classList.remove('bg-red-600', 'text-white');
        videoBtn.classList.add('bg-white', 'text-gray-700');

        isCallActive = false;
        currentSessionId = null;

        console.log('Call ended');
    }

    // Close modal when clicking outside
    document.getElementById('videoCallModal').addEventListener('click', function(e) {
        if (e.target === this) {
            endCall();
        }
    });
</script>

</html>