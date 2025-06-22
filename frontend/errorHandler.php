<?php

// Enable error reporting (for development environments)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL & ~E_DEPRECATED); // E_ALL except E_DEPRECATED

// Constants
define('CODE_SNIPPET_LINES', 5);
define('STACK_TRACE_SNIPPETS', 4); // Number of stack trace entries with snippets

// Custom error handling function
function customErrorHandler($errno, $errstr, $errfile, $errline) {
    logError("Error", $errno, $errstr, $errfile, $errline);
}

// Error handling for uncaught exceptions
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

// Common function for logging and outputting errors
function logError($type, $code, $message, $file, $line, $trace = null) {
    // Format for HTML output (browser) - full error output
    $htmlOutput = "<pre>\n";
    $htmlOutput .= "<strong>$type:</strong>\n";
    $htmlOutput .= "Code:". $code."\n";
    $htmlOutput .= "Message: $message\n";
    $htmlOutput .= "File: $file\n";
    $htmlOutput .= "Line: $line\n";

    // Format for console (PhpStorm) - stack trace only
    $consoleOutput = "\n$type: $message in $file on line $line\n";
    $consoleOutput .= "Stack Trace:\n";

    // Show code snippet for the original error location (browser only)
    $htmlOutput .= "\nInitial Error Location:\n";
    $htmlOutput .= showCodeSnippet($file, $line);

    // Stack Trace verarbeiten
    if ($trace === null) {
        // If no trace was passed, get it via debug_backtrace
        $trace = debug_backtrace();
    } elseif (is_string($trace)) {
        // If the trace was passed as a string (from getTraceAsString)
        $htmlOutput .= "\nStack Trace:\n" . $trace;
        $consoleOutput .= $trace;

        // Write error to console (stack trace only)
        error_log($consoleOutput, 4); // 4 bedeutet STDERR

        // Output error in browser (full output)
        echo $htmlOutput . "</pre>\n";
        return;
    }

    $htmlOutput .= "\nDetailed Stack Trace (last " . STACK_TRACE_SNIPPETS . " entries):\n";

    // Process the last X entries of the stack trace
    $relevantTrace = array_slice($trace, 0, STACK_TRACE_SNIPPETS);
    foreach ($relevantTrace as $index => $traceEntry) {
        if (isset($traceEntry['file']) && isset($traceEntry['line'])) {
            // For browser: Detailed stack trace with code snippets
            $htmlOutput .= "\n<h3>Stack Level " . ($index + 1) . "</h3>:\n";
            $htmlOutput .= "File: " . $traceEntry['file'] . "\n";
            $htmlOutput .= "Line: " . $traceEntry['line'] . "\n";
            $htmlOutput .= "Function: " . (isset($traceEntry['function']) ? $traceEntry['function'] : 'unknown') . "\n";
            $htmlOutput .= "Code Context:\n";
            $htmlOutput .= showCodeSnippet($traceEntry['file'], $traceEntry['line']);

            // For PhpStorm: Only the stack trace information
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

    // Write error to console (for PhpStorm) - stack trace only
    error_log($consoleOutput, 4); // 4 bedeutet STDERR

    // Output error in browser - full output
    echo $htmlOutput;
}
// Function to display code snippets
function showCodeSnippet($file, $line) {
    if (!file_exists($file)) return "File not found.\n";

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
