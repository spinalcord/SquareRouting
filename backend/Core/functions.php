<?php

if (! function_exists('nameof')) {
    function nameof($var): string
    {
        static $exprCache = [];
        static $fileCache = [];

        $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 1)[0];
        $key = $trace['file'] . ':' . $trace['line'];

        if (isset($exprCache[$key])) {
            return $exprCache[$key];
        }

        $file = $trace['file'];
        $lineNum = $trace['line'];

        if (! isset($fileCache[$file])) {
            $fileCache[$file] = @file($file) ?: [];
        }

        $line = $fileCache[$file][$lineNum - 1] ?? '';
        $start = strpos($line, 'nameof(');

        if ($start === false) {
            $exprCache[$key] = '';

            return '';
        }

        $start += 7; // Länge von "nameof("
        $depth = 1;
        $expr = '';

        // Extrahiert den Inhalt innerhalb der Klammern
        for ($i = $start; $i < strlen($line); $i++) {
            $char = $line[$i];
            if ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            }

            if ($depth === 0) {
                break;
            }
            $expr .= $char;
        }

        // Entfernt Fehleroperator und Variablenzeichen
        $expr = ltrim(trim($expr), '$');

        // Findet letzten relevanten Teil des Ausdrucks
        $lastArrow = strrpos($expr, '->');
        $lastColon = strrpos($expr, '::');
        $cutPos = max($lastArrow, $lastColon);

        if ($cutPos !== false) {
            $expr = substr($expr, $cutPos + 2);
        }

        // Entfernt nachfolgende Klammern/Indizes
        $parenPos = strpos($expr, '(');
        $bracketPos = strpos($expr, '[');
        $cutPos = min(
            $parenPos !== false ? $parenPos : PHP_INT_MAX,
            $bracketPos !== false ? $bracketPos : PHP_INT_MAX
        );

        if ($cutPos !== PHP_INT_MAX) {
            $expr = substr($expr, 0, $cutPos);
        }

        $expr = trim($expr);
        $exprCache[$key] = $expr;

        return $expr;
    }
}
