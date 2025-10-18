const { spawn } = require('child_process');

console.log('Starting video call signaling server...');
console.log('Make sure you have run: npm install');
console.log('Server will start on port 3001');

const server = spawn('node', ['signaling-server.js'], {
    stdio: 'inherit',
    shell: true
});

server.on('error', (err) => {
    console.error('Failed to start server:', err);
    console.log('Please make sure you have installed the dependencies: npm install');
});

process.on('SIGINT', () => {
    console.log('\nStopping server...');
    server.kill();
    process.exit();
});

server.on('exit', (code) => {
    if (code !== 0) {
        console.log(`Server process exited with code ${code}`);
        console.log('Please check if port 3001 is available');
    }
});