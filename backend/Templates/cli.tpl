<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Terminal</title>
    <style>
        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
        
        body {
            margin: 0;
            padding: 0;
            background-color: #000;
            color: #ffffff;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            height: 100vh;
            overflow: hidden;
        }
        
        .terminal {
            width: calc(100% - 300px);
            height: 100%;
            background-color: #000;
            padding: 15px;
            box-sizing: border-box;
            overflow-y: auto;
            float: left;
        }
        
        .debug-panel {
            width: 300px;
            height: 100%;
            background-color: #1a1a1a;
            color: #ccc;
            font-family: 'Courier New', monospace;
            font-size: 12px;
            padding: 10px;
            box-sizing: border-box;
            overflow-y: auto;
            float: right;
            border-left: 1px solid #333;
        }
        
        .debug-title {
            color: #fff;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .debug-entry {
            margin-bottom: 10px;
            padding: 5px;
            background-color: #2a2a2a;
            border-radius: 3px;
        }
        
        .debug-sent {
            border-left: 3px solid #4a9eff;
        }
        
        .debug-received {
            border-left: 3px solid #4aff4a;
        }
        
        .debug-label {
            font-weight: bold;
            margin-bottom: 3px;
        }
        
        .output {
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .input-line {
            display: flex;
            align-items: center;
        }
        
        .prompt {
            color: #ffffff;
            margin-right: 5px;
        }
        
        .command-input {
            flex: 1;
            background: transparent;
            border: none;
            color: #ffffff;
            font-family: 'Courier New', monospace;
            font-size: 16px;
            outline: none;
        }
        
        .command-input:focus {
            outline: none;
        }
        
        .output-line {
            margin: 2px 0;
            animation: fadeIn 0.2s ease-out;
        }
        
        .output-success {
            color: #4aff4a;
        }
        
        .output-error {
            color: #ff4444;
        }
        
        .output-warning {
            color: #ffaa44;
        }
        
        .output-info {
            color: #4488ff;
        }
        
        .spinner {
            color: #ffaa44;
            display: inline-block;
        }
    </style>
</head>
<body>
    <div class="terminal">
        <div class="output" id="output"></div>
        <div class="input-line">
            <span class="prompt">$ </span>
            <input type="text" class="command-input" id="commandInput" autocomplete="off">
        </div>
    </div>
    <div class="debug-panel">
        <div class="debug-title">Debug JSON</div>
        <div id="debugOutput"></div>
    </div>

    <script>
        const output = document.getElementById('output');
        const commandInput = document.getElementById('commandInput');
        const debugOutput = document.getElementById('debugOutput');
        const API_URL = '{{ $routePath }}';
        let awaitingInput = false;
        let currentCommandId = null;
        let currentCommand = null;
        let spinnerInterval = null;
        let spinnerElement = null;
        let isQueued = false;
        let expectingResponse = false;
        let currentSessionId = null;

        const spinnerChars = ['⠋', '⠙', '⠹', '⠸', '⠼', '⠴', '⠦', '⠧', '⠇', '⠏'];
        let spinnerIndex = 0;

        function generateSessionId() {
            return Math.random().toString(36).substr(2, 9);
        }

        function addDebug(data, type) {
            const entry = document.createElement('div');
            entry.className = `debug-entry debug-${type}`;
            
            const label = document.createElement('div');
            label.className = 'debug-label';
            label.textContent = type === 'sent' ? 'SENT:' : 'RECEIVED:';
            
            const content = document.createElement('pre');
            content.textContent = JSON.stringify(data, null, 2);
            
            entry.appendChild(label);
            entry.appendChild(content);
            debugOutput.appendChild(entry);
            
            debugOutput.scrollTop = debugOutput.scrollHeight;
        }

        function startSpinner() {
            if (spinnerInterval) return; // Spinner bereits aktiv
            
            spinnerElement = document.createElement('span');
            spinnerElement.className = 'spinner';
            spinnerElement.textContent = spinnerChars[0];
            
            const line = document.createElement('div');
            line.className = 'output-line';
            line.appendChild(spinnerElement);
            output.appendChild(line);
            
            spinnerIndex = 0;
            spinnerInterval = setInterval(() => {
                spinnerIndex = (spinnerIndex + 1) % spinnerChars.length;
                spinnerElement.textContent = spinnerChars[spinnerIndex];
            }, 100);
            
            // Scroll das gesamte Terminal nach unten
            document.querySelector('.terminal').scrollTop = document.querySelector('.terminal').scrollHeight;
        }

        function stopSpinner() {
            if (spinnerInterval) {
                clearInterval(spinnerInterval);
                spinnerInterval = null;
            }
            if (spinnerElement && spinnerElement.parentNode) {
                spinnerElement.parentNode.remove();
                spinnerElement = null;
            }
        }

        function updatePrompt() {
            const prompt = document.querySelector('.prompt');
            if (isQueued) {
                // Bei queue ist Eingabe komplett deaktiviert
                prompt.textContent = '';
                commandInput.disabled = true;
                commandInput.style.opacity = '0.5';
            } else if (awaitingInput) {
                prompt.textContent = '';
                commandInput.disabled = false;
                commandInput.style.opacity = '1';
                // Fokus wiederherstellen
                setTimeout(() => commandInput.focus(), 10);
            } else {
                prompt.textContent = '$ ';
                commandInput.disabled = false;
                commandInput.style.opacity = '1';
                // Fokus wiederherstellen
                setTimeout(() => commandInput.focus(), 10);
                // CommandId und Command löschen wenn neuer Befehl eingegeben werden kann
                currentCommandId = null;
                currentCommand = null;
            }
        }

        function addToOutput(text, outputType = null) {
            const line = document.createElement('div');
            line.className = 'output-line';
            
            // Output-Type als CSS-Klasse hinzufügen falls vorhanden
            if (outputType) {
                line.classList.add(`output-${outputType}`);
            }
            
            line.textContent = text;
            output.appendChild(line);
            
            // Scroll das gesamte Terminal nach unten
            document.querySelector('.terminal').scrollTop = document.querySelector('.terminal').scrollHeight;
        }

        function handleResponse(result, sessionId) {
            // Response nur verarbeiten wenn noch erwartet UND Session ID noch aktuell ist
            if (!expectingResponse || currentSessionId !== sessionId) return;
            
            // Spinner stoppen
            stopSpinner();
            
            // Rate Limit Check
            if (result.rateLimitExceeded) {
                // Rate Limit Nachricht anzeigen
                if (result.output) {
                    addToOutput(result.output, result.outputType || 'warning');
                }
                
                // Warten und dann fortfahren
                const waitTime = (result.remainingTime || 1) * 1000;
                setTimeout(() => {
                    // Prüfen ob Session noch aktiv ist
                    if (currentSessionId === sessionId && expectingResponse) {
                        if (isQueued) {
                            // Im Queue-Modus: Spinner wieder starten und weiter machen
                            startSpinner();
                            sendQueuedRequest();
                        } else if (awaitingInput) {
                            // Im Input-Modus: Benutzer kann selbst erneut versuchen
                            // Nichts weiter zu tun
                        }
                    }
                }, waitTime);
                return;
            }
            
            // CommandId aktualisieren falls neue gesendet wird
            if (result.commandId) {
                currentCommandId = result.commandId;
            }
            
            // Ausgabe anzeigen
            if (result.output) {
                addToOutput(result.output, result.outputType);
            }
            
            // Prüfen ob weitere Eingabe erwartet wird oder queue aktiv ist
            if (result.queue) {
                // Queue hat Priorität - Eingabe wird deaktiviert
                isQueued = true;
                awaitingInput = false;
                updatePrompt();
                startSpinner();
                // Sofort nächste Anfrage senden
                setTimeout(() => sendQueuedRequest(), 100);
            } else if (result.expectInput) {
                awaitingInput = true;
                isQueued = false;
                updatePrompt();
            } else if (result.commandComplete) {
                awaitingInput = false;
                isQueued = false;
                expectingResponse = false;
                currentSessionId = null;
                updatePrompt();
            }
        }

        function handleError(error, sessionId) {
            // Fehler nur verarbeiten wenn Session ID noch aktuell ist
            if (!expectingResponse || currentSessionId !== sessionId) return;
            
            stopSpinner();
            addToOutput('Error: ' + error.message);
            awaitingInput = false;
            isQueued = false;
            expectingResponse = false;
            currentSessionId = null;
            updatePrompt();
        }

        async function sendQueuedRequest() {
            if (!expectingResponse) return; // Keine Anfrage wenn keine Response erwartet wird
            
            const sessionId = currentSessionId; // Session ID für diese Anfrage merken
            
            try {
                const requestData = {};
                
                // Command und CommandId hinzufügen falls vorhanden
                if (currentCommand) {
                    requestData.command = currentCommand;
                }
                if (currentCommandId) {
                    requestData.commandId = currentCommandId;
                }
                
                addDebug(requestData, 'sent');
                
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });
                
                const result = await response.json();
                addDebug(result, 'received');
                
                handleResponse(result, sessionId);
                
            } catch (error) {
                handleError(error, sessionId);
            }
        }

        async function sendCommand(command) {
            try {
                expectingResponse = true; // Response wird erwartet
                currentSessionId = generateSessionId(); // Neue Session ID generieren
                const sessionId = currentSessionId; // Session ID für diese Anfrage merken
                
                // Command und Argumente aufteilen
                const parts = command.split(' ');
                const cmd = parts[0];
                const args = parts.slice(1);
                
                // Command für spätere Verwendung speichern
                currentCommand = cmd;
                
                const requestData = { 
                    command: cmd,
                    arguments: args
                };
                
                addDebug(requestData, 'sent');
                
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });
                
                const result = await response.json();
                addDebug(result, 'received');
                
                handleResponse(result, sessionId);
                
            } catch (error) {
                handleError(error, currentSessionId);
            }
        }

        async function sendInput(input) {
            if (!expectingResponse) return; // Keine Eingabe wenn keine Response erwartet wird
            
            const sessionId = currentSessionId; // Session ID für diese Anfrage merken
            
            try {
                const requestData = { 
                    input: input
                };
                
                // Command und CommandId hinzufügen falls vorhanden
                if (currentCommand) {
                    requestData.command = currentCommand;
                }
                if (currentCommandId) {
                    requestData.commandId = currentCommandId;
                }
                
                addDebug(requestData, 'sent');
                
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });
                
                const result = await response.json();
                addDebug(result, 'received');
                
                handleResponse(result, sessionId);
                
            } catch (error) {
                handleError(error, sessionId);
            }
        }

        commandInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                const input = commandInput.value.trim();
                if (input) {
                    // Eingabe nur verarbeiten wenn nicht in queue
                    if (!isQueued) {
                        if (awaitingInput) {
                            // Eingabe ohne Prompt anzeigen
                            addToOutput(input);
                            sendInput(input);
                        } else {
                            // Prüfen ob es ein lokaler clear command ist
                            if (input === 'clear') {
                                // Clear command - Terminal leeren
                                addToOutput('$ ' + input);
                                output.innerHTML = '';
                                commandInput.value = '';
                                return;
                            }
                            // Befehl mit Prompt anzeigen
                            addToOutput('$ ' + input);
                            sendCommand(input);
                        }
                        commandInput.value = '';
                    }
                    // Eingabe nicht löschen wenn queue aktiv ist
                }
            }
        });

        // Ctrl+C Behandlung
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'c') {
                e.preventDefault();
                if (awaitingInput || isQueued) {
                    addToOutput('^C');
                    sendInterrupt();
                    awaitingInput = false;
                    isQueued = false;
                    expectingResponse = false; // Keine weitere Response erwarten
                    currentSessionId = null; // Session invalidieren
                    stopSpinner();
                    currentCommandId = null; // CommandId bei Ctrl+C löschen
                    currentCommand = null;   // Command bei Ctrl+C löschen
                    updatePrompt();
                    commandInput.value = '';
                }
            }
        });

        async function sendInterrupt() {
            try {
                const requestData = {
                    interrupt: true
                };
                
                // CommandId hinzufügen falls vorhanden
                if (currentCommandId) {
                    requestData.commandId = currentCommandId;
                }
                
                addDebug(requestData, 'sent');
                
                const response = await fetch(API_URL, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify(requestData)
                });
                
                const result = await response.json();
                addDebug(result, 'received');
                
            } catch (error) {
                addToOutput('Error sending interrupt: ' + error.message);
            }
        }

        // Focus auf Input beim Laden
        commandInput.focus();

        // Interrupt beim Verlassen der Seite senden
        window.addEventListener('beforeunload', function(e) {
            if (awaitingInput || isQueued || expectingResponse) {
                // Synchroner Request beim Verlassen der Seite
                const requestData = {
                    interrupt: true
                };
                
                // CommandId hinzufügen falls vorhanden
                if (currentCommandId) {
                    requestData.commandId = currentCommandId;
                }
                
                // sendBeacon für zuverlässiges Senden beim Verlassen
                const blob = new Blob([JSON.stringify(requestData)], {
                    type: 'application/json'
                });
                navigator.sendBeacon(API_URL, blob);
            }
        });
    </script>
</body>
</html>
