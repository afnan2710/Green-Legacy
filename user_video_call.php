<?php
require_once 'db_connect.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: login.php");
    exit();
}

$user_id = $_SESSION['user_id'];
$appointment_id = isset($_GET['appointment_id']) ? intval($_GET['appointment_id']) : 0;

// Verify appointment belongs to user and is confirmed
$stmt = $conn->prepare("SELECT a.*, ad.username as consultant_name 
                       FROM appointments a 
                       JOIN admins ad ON a.consultant_id = ad.admin_id 
                       WHERE a.id = ? AND a.user_id = ? AND a.status = 'confirmed'");
$stmt->bind_param("ii", $appointment_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();
$appointment = $result->fetch_assoc();

if (!$appointment) {
    header("Location: appointments.php");
    exit();
}

?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Call - Consultation with <?php echo htmlspecialchars($appointment['consultant_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.1.2/dist/tailwind.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <script src="https://cdn.socket.io/4.7.2/socket.io.min.js"></script>
    <style>
        body {
            font-family: 'Poppins', sans-serif;
        }
    </style>
</head>

<body class="bg-gray-900">
    <div class="min-h-screen flex flex-col">
        <!-- Header -->
        <div class="bg-green-600 text-white p-4">
            <div class="container mx-auto">
                <div class="flex justify-between items-center mb-2">
                    <h1 class="text-xl font-semibold">Consultation with <?php echo htmlspecialchars($appointment['consultant_name']); ?></h1>
                    <a href="appointment_details.php?id=<?php echo $appointment_id; ?>"
                        class="text-white hover:text-gray-200 text-sm bg-green-700 px-3 py-1 rounded">
                        <i class="fas fa-arrow-left mr-1"></i> Back
                    </a>
                </div>
                <div class="flex justify-between items-center">
                    <div id="call-status" class="flex items-center">
                        <span class="w-3 h-3 bg-yellow-400 rounded-full mr-2 animate-pulse"></span>
                        <span>Connecting to consultant...</span>
                    </div>
                    <div class="text-sm bg-green-700 px-3 py-1 rounded">
                        Appointment #<?php echo $appointment_id; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Video Area -->
        <div class="flex-1 flex flex-col md:flex-row p-4 gap-4">
            <!-- Remote Video (Consultant) - Main View -->
            <div class="flex-1 bg-black rounded-lg overflow-hidden relative">
                <video id="remote-video" autoplay playsinline class="w-full h-full object-cover"></video>
                <div id="remote-video-placeholder" class="absolute inset-0 flex items-center justify-center text-white">
                    <div class="text-center">
                        <i class="fas fa-user-tie fa-5x opacity-50 mb-4"></i>
                        <p>Waiting for consultant to join...</p>
                        <p class="text-sm mt-2 text-gray-300" id="connection-details">Establishing connection...</p>
                    </div>
                </div>
                <div class="absolute top-4 left-4 bg-black bg-opacity-50 text-white px-3 py-1 rounded">
                    <i class="fas fa-user-tie mr-2"></i><?php echo htmlspecialchars($appointment['consultant_name']); ?>
                </div>
            </div>

            <!-- Local Video (User) - Small PIP -->
            <div class="absolute bottom-4 right-4 md:relative md:bottom-auto md:right-auto md:w-1/4">
                <div class="bg-black rounded-lg overflow-hidden border-2 border-white shadow-lg w-40 h-30 md:w-full md:h-full">
                    <video id="local-video" autoplay playsinline muted class="w-full h-full object-cover"></video>
                    <div class="absolute bottom-2 left-2 bg-black bg-opacity-50 text-white px-2 py-1 rounded text-sm">
                        You
                    </div>
                </div>
            </div>
        </div>

        <!-- Controls -->
        <div class="bg-gray-800 p-4 flex justify-center space-x-6">
            <button id="muteBtn" onclick="toggleMute()"
                class="p-4 bg-white text-gray-700 rounded-full hover:bg-gray-200 flex items-center justify-center shadow-lg transition-colors">
                <i class="fas fa-microphone text-xl"></i>
            </button>
            <button id="videoBtn" onclick="toggleVideo()"
                class="p-4 bg-white text-gray-700 rounded-full hover:bg-gray-200 flex items-center justify-center shadow-lg transition-colors">
                <i class="fas fa-video text-xl"></i>
            </button>
            <button onclick="endCall()"
                class="p-4 bg-red-600 text-white rounded-full hover:bg-red-700 flex items-center justify-center shadow-lg transition-colors">
                <i class="fas fa-phone text-xl"></i>
            </button>
        </div>
    </div>

    <script>
        // Declare variables at the top
        let localStream;
        let remoteStream;
        let peerConnection;
        let isCallActive = false;
        let socket;
        let currentSessionId;

        const configuration = {
            iceServers: [{
                    urls: 'stun:stun.l.google.com:19302'
                },
                {
                    urls: 'stun:stun1.l.google.com:19302'
                }
            ]
        };

        // Initialize when page loads
        window.addEventListener('load', async () => {
            try {
                // Initialize socket first
                socket = io('http://localhost:3001');

                // Set up socket listeners
                setupSocketListeners();

                await initializeMedia();
                createPeerConnection();

                // Use consistent session ID based on appointment ID only
                currentSessionId = 'session_<?php echo $appointment_id; ?>';

                console.log('Joining session:', currentSessionId);

                socket.emit('join-session', {
                    sessionId: currentSessionId,
                    userType: 'user',
                    userId: <?php echo $user_id; ?>,
                    appointmentId: <?php echo $appointment_id; ?>
                });

                isCallActive = true;
                updateCallStatus('Connected - Waiting for consultant');

            } catch (error) {
                console.error('Error joining call:', error);
                alert('Error joining video call: ' + error.message);
            }
        });

        function setupSocketListeners() {
            socket.on('offer', async (data) => {
                console.log('Received offer from consultant');
                updateCallStatus('Consultant joined - establishing connection');
                await handleOffer(data.offer);
            });

            socket.on('answer', async (data) => {
                console.log('Received answer from consultant');
                await handleAnswer(data.answer);
            });

            socket.on('ice-candidate', async (data) => {
                console.log('Received ICE candidate from consultant');
                await handleNewICECandidate(data.candidate);
            });

            socket.on('user-joined', (data) => {
                console.log('User joined:', data);
                if (data.userType === 'consultant') {
                    updateCallStatus('Consultant joined - establishing connection');
                }
            });

            socket.on('call-ended', (data) => {
                console.log('Call ended by consultant');
                endCall();
                alert('Consultant ended the call');
                setTimeout(() => {
                    window.location.href = 'appointment_details.php?id=<?php echo $appointment_id; ?>';
                }, 2000);
            });

            socket.on('connect_error', (error) => {
                console.error('Socket connection error:', error);
                alert('Failed to connect to video server. Please make sure the signaling server is running.');
            });
        }

        function updateCallStatus(status) {
            const statusElement = document.getElementById('call-status');
            const connectionDetails = document.getElementById('connection-details');

            if (statusElement) {
                let statusColor = 'bg-yellow-400';
                if (status.includes('Connected')) statusColor = 'bg-green-400';
                if (status.includes('Failed')) statusColor = 'bg-red-400';

                statusElement.innerHTML = `
                <span class="w-3 h-3 ${statusColor} rounded-full mr-2 ${status.includes('establishing') ? 'animate-pulse' : ''}"></span>
                <span>${status}</span>
            `;
            }

            if (connectionDetails) {
                connectionDetails.textContent = status;
            }
        }

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

                    updateCallStatus('Connected with consultant');

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
                    }
                };

                peerConnection.onconnectionstatechange = () => {
                    console.log('Connection state:', peerConnection.connectionState);
                    if (peerConnection.connectionState === 'connected') {
                        updateCallStatus('Connected with consultant');
                    } else if (peerConnection.connectionState === 'connecting') {
                        updateCallStatus('Establishing connection...');
                    }
                };

                console.log('Peer connection created');

            } catch (error) {
                console.error('Error creating peer connection:', error);
                throw error;
            }
        }

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

        async function handleAnswer(answer) {
            try {
                console.log('Setting remote description (answer)');
                await peerConnection.setRemoteDescription(answer);
                console.log('Remote description set successfully');
            } catch (error) {
                console.error('Error handling answer:', error);
            }
        }

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
                    muteBtn.innerHTML = '<i class="fas fa-microphone text-xl"></i>';
                    muteBtn.classList.remove('bg-red-600');
                    muteBtn.classList.add('bg-white');
                } else {
                    muteBtn.innerHTML = '<i class="fas fa-microphone-slash text-xl"></i>';
                    muteBtn.classList.remove('bg-white');
                    muteBtn.classList.add('bg-red-600');
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
                    videoBtn.innerHTML = '<i class="fas fa-video text-xl"></i>';
                    videoBtn.classList.remove('bg-red-600');
                    videoBtn.classList.add('bg-white');
                } else {
                    videoBtn.innerHTML = '<i class="fas fa-video-slash text-xl"></i>';
                    videoBtn.classList.remove('bg-white');
                    videoBtn.classList.add('bg-red-600');
                }
            }
        }

        function endCall() {
            console.log('Ending call...');

            if (isCallActive && socket) {
                socket.emit('end-call', {
                    sessionId: currentSessionId
                });
            }

            if (peerConnection) {
                peerConnection.close();
                peerConnection = null;
            }

            if (localStream) {
                localStream.getTracks().forEach(track => track.stop());
                localStream = null;
            }

            window.location.href = 'appointment_details.php?id=<?php echo $appointment_id; ?>';
        }

        // Handle page refresh/close
        window.addEventListener('beforeunload', endCall);
    </script>
</body>

</html>