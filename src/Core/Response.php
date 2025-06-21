<?php
declare(strict_types=1);

namespace SquareRouting\Core;

class Response {
    private int $status = 200;
    private array $headers = [];
    private string $body = '';

    /**
     * Konfiguriert die Response für eine JSON-Antwort.
     *
     * @param mixed $data Die zu kodierenden Daten.
     * @param int $status Der HTTP-Statuscode.
     * @return self
     */
    public function json(mixed $data, int $status = 200): self
    {
        $this->status = $status;
        $this->setHeader('Content-Type', 'application/json');
        $this->body = json_encode($data);
        return $this; // Wichtig: Gib $this zurück für Method-Chaining.
    }

    /**
     * Konfiguriert die Response für eine Fehlerantwort im JSON-Format.
     *
     * @param string $message Die Fehlermeldung.
     * @param int $status Der HTTP-Statuscode.
     * @return self
     */
    public function error(string $message, int $status = 400): self
    {
        return $this->json([
            'error' => true,
            'message' => $message
        ], $status);
    }

    /**
     * Konfiguriert die Response für eine einfache Textantwort.
     *
     * @param string $message Der Textinhalt.
     * @param int $status Der HTTP-Statuscode.
     * @return self
     */
    public function text(string $message, int $status = 200): self
    {
        $this->status = $status;
        $this->setHeader('Content-Type', 'text/plain');
        $this->body = $message;
        return $this;
    }

    /**
     * Konfiguriert die Response für eine HTML-Antwort.
     *
     * @param string $html Der HTML-Inhalt.
     * @param int $status Der HTTP-Statuscode.
     * @return self
     */
    public function html(string $html, int $status = 200): self
    {
        $this->status = $status;
        $this->setHeader('Content-Type', 'text/html');
        $this->body = $html;
        return $this;
    }

    /**
     * Konfiguriert die Response für eine Weiterleitung.
     *
     * @param string $url Die Ziel-URL.
     * @param int $status Der HTTP-Statuscode (meist 302 oder 301).
     * @return self
     */
    public function reroute(string $url, int $status = 302): self
    {
        $this->status = $status;
        $this->setHeader('Location', $url);
        return $this;
    }

    /**
     * Setzt einen benutzerdefinierten Header.
     *
     * @param string $name Der Name des Headers.
     * @param string $value Der Wert des Headers.
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }
    
    /**
     * Sendet die gesammelten Header und den Body an den Client.
     * Diese Methode sollte nur einmal am Ende des Request-Lifecycles aufgerufen werden.
     */
    public function send(): void
    {
        // Verhindert, dass Header gesendet werden, wenn sie schon gesendet wurden.
        if (headers_sent()) {
            return;
        }

        // Sende den Statuscode
        http_response_code($this->status);

        // Sende alle Header
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Sende den Body
        echo $this->body;
    }
}
