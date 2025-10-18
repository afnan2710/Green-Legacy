const express = require('express');
const http = require('http');
const socketIo = require('socket.io');
const cors = require('cors');

const app = express();
const server = http.createServer(app);
const io = socketIo(server, {
  cors: {
    origin: "*",
    methods: ["GET", "POST"]
  }
});

app.use(cors());
app.use(express.json());

// Store active sessions
const activeSessions = new Map();

io.on('connection', (socket) => {
  console.log('User connected:', socket.id);

  // Join a video session
  socket.on('join-session', (data) => {
    const { sessionId, userType, userId, appointmentId } = data;
    
    socket.join(sessionId);
    socket.sessionId = sessionId;
    socket.userType = userType;
    socket.userId = userId;
    
    // Initialize session if not exists
    if (!activeSessions.has(sessionId)) {
      activeSessions.set(sessionId, {
        users: [],
        offers: {},
        answers: {},
        iceCandidates: {}
      });
      console.log(`Created new session: ${sessionId}`);
    }
    
    const session = activeSessions.get(sessionId);
    session.users.push({ socketId: socket.id, userType, userId });
    
    console.log(`User ${userId} (${userType}) joined session ${sessionId}`);
    console.log(`Session ${sessionId} now has ${session.users.length} users:`, 
                session.users.map(u => `${u.userType}(${u.userId})`));
    
    // Notify other users in the room
    socket.to(sessionId).emit('user-joined', {
      userType,
      userId,
      socketId: socket.id
    });

    // Send existing ICE candidates to new user
    if (session.iceCandidates[socket.id]) {
      session.iceCandidates[socket.id].forEach(candidate => {
        socket.emit('ice-candidate', { candidate, from: userType });
      });
    }

    // If there are other users in the session, notify them about the new user
    if (session.users.length > 1) {
      console.log(`Notifying ${session.users.length - 1} other users about new ${userType}`);
    }
  });

  // WebRTC signaling: Offer
  socket.on('offer', (data) => {
    const { sessionId, offer } = data;
    const session = activeSessions.get(sessionId);
    if (session) {
      session.offers[socket.id] = offer;
      console.log(`Offer from ${socket.userType} in session ${sessionId} to ${session.users.length - 1} other users`);
      
      // Send offer to all other users in the session
      session.users.forEach(user => {
        if (user.socketId !== socket.id) {
          io.to(user.socketId).emit('offer', { 
            offer, 
            from: socket.userType,
            fromSocketId: socket.id 
          });
          console.log(`Sent offer to ${user.userType} (${user.socketId})`);
        }
      });
    }
  });

  // WebRTC signaling: Answer
  socket.on('answer', (data) => {
    const { sessionId, answer } = data;
    const session = activeSessions.get(sessionId);
    if (session) {
      session.answers[socket.id] = answer;
      console.log(`Answer from ${socket.userType} in session ${sessionId} to ${session.users.length - 1} other users`);
      
      // Send answer to all other users in the session
      session.users.forEach(user => {
        if (user.socketId !== socket.id) {
          io.to(user.socketId).emit('answer', { 
            answer, 
            from: socket.userType,
            fromSocketId: socket.id 
          });
          console.log(`Sent answer to ${user.userType} (${user.socketId})`);
        }
      });
    }
  });

  // WebRTC signaling: ICE Candidate
  socket.on('ice-candidate', (data) => {
    const { sessionId, candidate } = data;
    const session = activeSessions.get(sessionId);
    if (session) {
      // Store candidate for late joiners
      if (!session.iceCandidates[socket.id]) {
        session.iceCandidates[socket.id] = [];
      }
      session.iceCandidates[socket.id].push(candidate);
      
      console.log(`ICE candidate from ${socket.userType} to ${session.users.length - 1} other users`);
      
      // Send to all other users in the session
      session.users.forEach(user => {
        if (user.socketId !== socket.id) {
          io.to(user.socketId).emit('ice-candidate', { 
            candidate, 
            from: socket.userType,
            fromSocketId: socket.id 
          });
        }
      });
    }
  });

  // End call
  socket.on('end-call', (data) => {
    const { sessionId } = data;
    console.log(`Call ended by ${socket.userType} in session ${sessionId}`);
    socket.to(sessionId).emit('call-ended', { endedBy: socket.userType });
  });

  // Handle disconnect
  socket.on('disconnect', () => {
    console.log('User disconnected:', socket.id);
    
    if (socket.sessionId) {
      const session = activeSessions.get(socket.sessionId);
      if (session) {
        session.users = session.users.filter(user => user.socketId !== socket.id);
        
        // Notify other users
        socket.to(socket.sessionId).emit('user-left', {
          userType: socket.userType,
          userId: socket.userId
        });

        console.log(`User ${socket.userId} (${socket.userType}) left session ${socket.sessionId}`);
        console.log(`Session ${socket.sessionId} now has ${session.users.length} users`);

        // Clean up empty sessions
        if (session.users.length === 0) {
          console.log(`Cleaning up empty session: ${socket.sessionId}`);
          activeSessions.delete(socket.sessionId);
        }
      }
    }
  });
});

// Health check endpoint
app.get('/health', (req, res) => {
  res.json({ 
    status: 'ok', 
    activeSessions: activeSessions.size,
    port: process.env.PORT || 3001
  });
});

const PORT = process.env.PORT || 3001;
server.listen(PORT, () => {
  console.log(`✅ Signaling server running on port ${PORT}`);
  console.log(`✅ Health check available at http://localhost:${PORT}/health`);
});