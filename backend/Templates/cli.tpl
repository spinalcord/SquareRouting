<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminal</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(2px); }
            to { opacity: 1; transform: translateY(0); }
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        body {
            background: #000;
            color: #fff;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            height: 100vh;
            overflow: hidden;
        }
        
        .terminal {
            width: 100%;
            height: 100%;
            padding: 20px;
            overflow-y: auto;
            scroll-behavior: smooth;
        }
        
        .output {
            white-space: pre-wrap;
            word-wrap: break-word;
            margin-bottom: 10px;
        }
        
        .output-line {
            margin: 2px 0;
            animation: fadeIn 0.15s ease-out;
        }
        
        .output-success { color: #4aff4a; }
        .output-error { color: #ff4444; }
        .output-warning { color: #ffaa44; }
        .output-info { color: #4488ff; }
        
        .input-line {
            display: flex;
            align-items: center;
            position: sticky;
            bottom: 0;
            background: #000;
            padding-top: 5px;
        }
        
        .prompt {
            color: #fff;
            margin-right: 8px;
            user-select: none;
        }
        
        .command-input {
            flex: 1;
            background: transparent;
            border: none;
            color: #fff;
            font-family: inherit;
            font-size: inherit;
            outline: none;
            transition: opacity 0.2s;
        }
        
        .command-input:disabled {
            opacity: 0.5;
        }
        
        .spinner {
            color: #ffaa44;
            display: inline-block;
            font-size: 14px;
        }
        
        /* Scrollbar styling */
        .terminal::-webkit-scrollbar {
            width: 8px;
        }
        
        .terminal::-webkit-scrollbar-track {
            background: #111;
        }
        
        .terminal::-webkit-scrollbar-thumb {
            background: #333;
            border-radius: 4px;
        }
        
        .terminal::-webkit-scrollbar-thumb:hover {
            background: #555;
        }
    </style>
</head>
<body>
    <div class="terminal">
        <div class="output" id="output"></div>
        <div class="input-line">
            <span class="prompt">{{ $username }}$ </span>
            <input type="text" class="command-input" id="commandInput" autocomplete="off">
        </div>
    </div>

    <script>
        class Terminal {
            constructor() {
                this.output = document.getElementById('output');
                this.commandInput = document.getElementById('commandInput');
                this.prompt = document.querySelector('.prompt');
                this.apiUrl = '{{ $routePath }}';
                
                // State management
                this.state = {
                    awaitingInput: false,
                    isQueued: false,
                    expectingResponse: false,
                    currentCommandId: null,
                    currentCommand: null,
                    currentSessionId: null,
                    currentUser: "{{ $username }}"
                };
                
                // Spinner management
                this.spinner = {
                    element: null,
                    interval: null,
                    timeout: null,
                    chars: ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'],
                    index: 0
                };
                
                this.init();
            }
            
            init() {
                this.setupEventListeners();
                this.commandInput.focus();
            }
            
            setupEventListeners() {
                this.commandInput.addEventListener('keypress', (e) => {
                    if (e.key === 'Enter') {
                        this.handleEnter();
                    }
                });
                
                document.addEventListener('keydown', (e) => {
                    if (e.ctrlKey && e.key === 'c') {
                        e.preventDefault();
                        this.handleInterrupt();
                    }
                });
                
                window.addEventListener('beforeunload', () => {
                    this.sendInterruptOnExit();
                });
            }
            
            handleEnter() {
                const input = this.commandInput.value.trim();
                if (!input || this.state.isQueued) return;
                
                if (input === 'clear') {
                    this.clearTerminal();
                    return;
                }
                
                if (this.state.awaitingInput) {
                    this.addOutput(input);
                    this.sendInput(input);
                } else {
                    this.addOutput(`${this.state.currentUser}$ ${input}`);
                    this.sendCommand(input);
                }
                
                this.commandInput.value = '';
            }
            
            handleInterrupt() {
                if (this.state.awaitingInput || this.state.isQueued || this.state.expectingResponse) {
                    this.addOutput('^C');
                    this.sendInterrupt();
                    this.resetState();
                    this.commandInput.value = '';
                }
            }
            
            clearTerminal() {
                this.addOutput(`${this.state.currentUser}$ clear`);
                this.output.innerHTML = '';
                this.commandInput.value = '';
            }
            
            resetState() {
                Object.assign(this.state, {
                    awaitingInput: false,
                    isQueued: false,
                    expectingResponse: false,
                    currentCommandId: null,
                    currentCommand: null,
                    currentSessionId: null
                });
                this.stopSpinner();
                this.updatePrompt();
            }
            
            generateSessionId() {
                return Math.random().toString(36).substr(2, 9);
            }
            
            updatePrompt() {
                const { awaitingInput, isQueued, expectingResponse, currentUser } = this.state;
                const promptText = currentUser ? `${currentUser}$ ` : '$ ';
                
                if (isQueued) {
                    this.prompt.textContent = '';
                    this.commandInput.disabled = true;
                } else if (awaitingInput) {
                    this.prompt.textContent = '';
                    this.commandInput.disabled = false;
                    setTimeout(() => this.commandInput.focus(), 10);
                } else if (expectingResponse) {
                    this.prompt.textContent = '';
                    this.commandInput.disabled = true;
                } else {
                    this.prompt.textContent = promptText;
                    this.commandInput.disabled = false;
                    setTimeout(() => this.commandInput.focus(), 10);
                    this.state.currentCommandId = null;
                    this.state.currentCommand = null;
                }
            }
            
            addOutput(text, outputType = null) {
                const line = document.createElement('div');
                line.className = 'output-line';
                
                if (outputType) {
                    line.classList.add(`output-${outputType}`);
                }
                
                line.textContent = text;
                this.output.appendChild(line);
                this.scrollToBottom();
            }
            
            scrollToBottom() {
                const terminal = document.querySelector('.terminal');
                terminal.scrollTop = terminal.scrollHeight;
            }
            
            startSpinner() {
                if (this.spinner.interval) return;
                
                this.spinner.element = document.createElement('span');
                this.spinner.element.className = 'spinner';
                this.spinner.element.textContent = this.spinner.chars[0];
                
                const line = document.createElement('div');
                line.className = 'output-line';
                line.appendChild(this.spinner.element);
                this.output.appendChild(line);
                
                this.spinner.index = 0;
                this.spinner.interval = setInterval(() => {
                    this.spinner.index = (this.spinner.index + 1) % this.spinner.chars.length;
                    if (this.spinner.element) {
                        this.spinner.element.textContent = this.spinner.chars[this.spinner.index];
                    }
                }, 100);
                
                this.scrollToBottom();
            }
            
            stopSpinner() {
                if (this.spinner.interval) {
                    clearInterval(this.spinner.interval);
                    this.spinner.interval = null;
                }
                if (this.spinner.element?.parentNode) {
                    this.spinner.element.parentNode.remove();
                    this.spinner.element = null;
                }
                this.clearSpinnerTimeout();
            }
            
            startSpinnerTimeout() {
                this.clearSpinnerTimeout();
                this.spinner.timeout = setTimeout(() => {
                    if (this.state.expectingResponse) {
                        this.startSpinner();
                    }
                }, 1000);
            }
            
            clearSpinnerTimeout() {
                if (this.spinner.timeout) {
                    clearTimeout(this.spinner.timeout);
                    this.spinner.timeout = null;
                }
            }
            
            async sendRequest(requestData, sessionId) {
                try {
                    const response = await fetch(this.apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(requestData)
                    });
                    
                    const result = await response.json();
                    this.handleResponse(result, sessionId);
                } catch (error) {
                    this.handleError(error, sessionId);
                }
            }
            
            async sendCommand(command) {
                this.state.expectingResponse = true;
                this.state.currentSessionId = this.generateSessionId();
                const sessionId = this.state.currentSessionId;
                
                this.updatePrompt();
                this.startSpinnerTimeout();
                
                const parts = command.split(' ');
                this.state.currentCommand = parts[0];
                
                const requestData = {
                    command: parts[0],
                    arguments: parts.slice(1)
                };
                
                await this.sendRequest(requestData, sessionId);
            }
            
            async sendInput(input) {
                if (!this.state.expectingResponse) return;
                
                const sessionId = this.state.currentSessionId;
                this.updatePrompt();
                this.startSpinnerTimeout();
                
                const requestData = { input };
                
                if (this.state.currentCommand) requestData.command = this.state.currentCommand;
                if (this.state.currentCommandId) requestData.commandId = this.state.currentCommandId;
                
                await this.sendRequest(requestData, sessionId);
            }
            
            async sendQueuedRequest() {
                if (!this.state.expectingResponse) return;
                
                const sessionId = this.state.currentSessionId;
                const requestData = {};
                
                if (this.state.currentCommand) requestData.command = this.state.currentCommand;
                if (this.state.currentCommandId) requestData.commandId = this.state.currentCommandId;
                
                await this.sendRequest(requestData, sessionId);
            }
            
            async sendInterrupt() {
                const requestData = { interrupt: true };
                if (this.state.currentCommandId) requestData.commandId = this.state.currentCommandId;
                
                try {
                    await fetch(this.apiUrl, {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify(requestData)
                    });
                } catch (error) {
                    this.addOutput('Error sending interrupt: ' + error.message);
                }
            }
            
            sendInterruptOnExit() {
                if (this.state.awaitingInput || this.state.isQueued || this.state.expectingResponse) {
                    const requestData = { interrupt: true };
                    if (this.state.currentCommandId) requestData.commandId = this.state.currentCommandId;
                    
                    const blob = new Blob([JSON.stringify(requestData)], {
                        type: 'application/json'
                    });
                    navigator.sendBeacon(this.apiUrl, blob);
                }
            }
            
            handleResponse(result, sessionId) {
                if (!this.state.expectingResponse || this.state.currentSessionId !== sessionId) return;
                
                this.stopSpinner();
                
                if (result.label) {
                    this.state.currentUser = result.label;
                    this.updatePrompt();
                }
                
                if (result.rateLimitExceeded) {
                    if (result.output) {
                        this.addOutput(result.output, result.outputType || 'warning');
                    }
                    
                    const waitTime = (result.remainingTime || 1) * 1000;
                    setTimeout(() => {
                        if (this.state.currentSessionId === sessionId && this.state.expectingResponse) {
                            if (this.state.isQueued) {
                                this.startSpinner();
                                this.sendQueuedRequest();
                            }
                        }
                    }, waitTime);
                    return;
                }
                
                if (result.commandId) {
                    this.state.currentCommandId = result.commandId;
                }
                
                if (result.output) {
                    this.addOutput(result.output, result.outputType);
                }
                
                if (result.queue) {
                    this.state.isQueued = true;
                    this.state.awaitingInput = false;
                    this.updatePrompt();
                    this.startSpinner();
                    setTimeout(() => this.sendQueuedRequest(), 100);
                } else if (result.expectInput) {
                    this.state.awaitingInput = true;
                    this.state.isQueued = false;
                    this.updatePrompt();
                } else if (result.commandComplete) {
                    this.state.awaitingInput = false;
                    this.state.isQueued = false;
                    this.state.expectingResponse = false;
                    this.state.currentSessionId = null;
                    this.updatePrompt();
                }
            }
            
            handleError(error, sessionId) {
                if (!this.state.expectingResponse || this.state.currentSessionId !== sessionId) return;
                
                this.stopSpinner();
                this.addOutput('Error: ' + error.message, 'error');
                this.resetState();
            }
        }
        
        // Initialize terminal when page loads
        new Terminal();
    </script>
</body>
</html>
