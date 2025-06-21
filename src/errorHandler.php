<?php

// Fehlerberichterstattung aktivieren (für Entwicklungsumgebungen)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED); // E_ALL außer E_DEPRECATED

// Konstanten
define('CODE_SNIPPET_LINES', 5);
define('STACK_TRACE_SNIPPETS', 4); // Anzahl der Stacktrace-Einträge mit Snippets

// Eigene Fehlerbehandlungsfunktion
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    logError("Error", $errno, $errstr, $errfile, $errline);
}

// Fehlerbehandlung für uncatchte Ausnahmen
function customExceptionHandler($exception) {
    logError(
        "Uncaught Exception",
        $exception->getCode(),
        $exception->getMessage(),
        $exception->getFile(),
        $exception->getLine(),
        $exception->getTrace()
    );
}

// Gemeinsame Funktion zum Loggen und Ausgeben von Fehlern
function logError($type, $code, $message, $file, $line, $trace = null) {
    // Format für HTML-Ausgabe (Browser) - vollständige Fehlerausgabe
    $htmlOutput = "<pre>\n";
    $htmlOutput .= "<strong>$type:</strong>\n";
    $htmlOutput .= "Code:". $code."\n";
    $htmlOutput .= "Message: $message\n";
    $htmlOutput .= "File: $file\n";
    $htmlOutput .= "Line: $line\n";

    // Format für Konsole (PhpStorm) - nur Stack Trace
    $consoleOutput = "\n$type: $message in $file on line $line\n";
    $consoleOutput .= "Stack Trace:\n";

    // Zeige Code-Snippet für die ursprüngliche Fehlerposition (nur im Browser)
    $htmlOutput .= "\nInitial Error Location:\n";
    $htmlOutput .= showCodeSnippet($file, $line);

    // Stack Trace verarbeiten
    if ($trace === null) {
        // Wenn kein Trace übergeben wurde, hole ihn via debug_backtrace
        $trace = debug_backtrace();
    } elseif (is_string($trace)) {
        // Wenn der Trace als String übergeben wurde (von getTraceAsString)
        $htmlOutput .= "\nStack Trace:\n" . $trace;
        $consoleOutput .= $trace;

        // Fehler in die Konsole schreiben (nur Stack Trace)
        error_log($consoleOutput, 4); // 4 bedeutet STDERR

        // Fehler im Browser ausgeben (vollständige Ausgabe)
        echo $htmlOutput . "</pre>\n";
        return;
    }

    $htmlOutput .= "\nDetailed Stack Trace (last " . STACK_TRACE_SNIPPETS . " entries):\n";

    // Die letzten X Einträge des Stack Trace verarbeiten
    $relevantTrace = array_slice($trace, 0, STACK_TRACE_SNIPPETS);
    foreach ($relevantTrace as $index => $traceEntry) {
        if (isset($traceEntry['file']) && isset($traceEntry['line'])) {
            // Für Browser: Detaillierter Stack Trace mit Code-Snippets
            $htmlOutput .= "\n<h3>Stack Level " . ($index + 1) . "</h3>:\n";
            $htmlOutput .= "File: " . $traceEntry['file'] . "\n";
            $htmlOutput .= "Line: " . $traceEntry['line'] . "\n";
            $htmlOutput .= "Function: " . (isset($traceEntry['function']) ? $traceEntry['function'] : 'unknown') . "\n";
            $htmlOutput .= "Code Context:\n";
            $htmlOutput .= showCodeSnippet($traceEntry['file'], $traceEntry['line']);

            // Für PhpStorm: Nur die Stack-Trace-Informationen
            $consoleOutput .= "#$index {$traceEntry['file']}({$traceEntry['line']}): ";
            $consoleOutput .= isset($traceEntry['class']) ? "{$traceEntry['class']}{$traceEntry['type']}" : "";
            $consoleOutput .= "{$traceEntry['function']}()\n";
        }
    }

    $htmlOutput .= "\nComplete Stack Trace:\n";
    foreach ($trace as $index => $traceEntry) {
        if (isset($traceEntry['file']) && isset($traceEntry['line'])) {
            $traceInfo = "#$index {$traceEntry['file']}({$traceEntry['line']}): ";
            $traceInfo .= isset($traceEntry['class']) ? "{$traceEntry['class']}{$traceEntry['type']}" : "";
            $traceInfo .= "{$traceEntry['function']}()\n";

            $htmlOutput .= $traceInfo;
        }
    }

    $htmlOutput .= "</pre>\n";

    // Fehler in die Konsole schreiben (für PhpStorm) - nur Stack Trace
    error_log($consoleOutput, 4); // 4 bedeutet STDERR

    // Fehler im Browser ausgeben - vollständige Ausgabe
    echo $htmlOutput;
}
// Funktion zum Anzeigen von Code-Ausschnitten
function showCodeSnippet($file, $line) {
    if (!file_exists($file)) return "Datei nicht gefunden.\n";

    $lines = file($file);
    $start = max(0, $line - CODE_SNIPPET_LINES);
    $end = min(count($lines), $line + CODE_SNIPPET_LINES + 1);

    $snippet = "";
    for ($i = $start; $i < $end; $i++) {
        $num = $i + 1;
        $marker = ($num == $line) ? ">> " : "   ";
        $snippet .= "$marker$num: " . $lines[$i];
    }
    return $snippet;
}

set_error_handler("customErrorHandler");
set_exception_handler("customExceptionHandler");

register_shutdown_function(function() {
    $error = error_get_last();
    if ($error && ($error['type'] === E_ERROR || $error['type'] === E_PARSE ||
            $error['type'] === E_COMPILE_ERROR || $error['type'] === E_CORE_ERROR)) {
        logError("Fatal Error", $error['type'], $error['message'], $error['file'], $error['line']);
    }
});
