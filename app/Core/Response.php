<?php
declare(strict_types=1);

namespace SquareRouting\Core;

class Response {
    private int $status = 200;
    private array $headers = [];
    private string $body = '';

    /**
     * Configures the response for a JSON reply.
     *
     * @param mixed $data The data to encode.
     * @param int $status The HTTP status code.
     * @return self
     */
    public function json(mixed $data, int $status = 200): self
    {
        $this->status = $status;
        $this->setHeader('Content-Type', 'application/json');
        $this->body = json_encode($data);
        return $this; // Important: Return $this for method chaining.
    }

    /**
     * Configures the response for an error reply in JSON format.
     *
     * @param string $message The error message.
     * @param int $status The HTTP status code.
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
     * Configures the response for a simple text reply.
     *
     * @param string $message The text content.
     * @param int $status The HTTP status code.
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
     * Configures the response for an HTML reply.
     *
     * @param string $html The HTML content.
     * @param int $status The HTTP status code.
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
     * Configures the response for a redirect.
     *
     * @param string $url The target URL.
     * @param int $status The HTTP status code (usually 302 or 301).
     * @return self
     */
    public function reroute(string $url, int $status = 302): self
    {
        $this->status = $status;
        $this->setHeader('Location', $url);
        return $this;
    }

    /**
     * Sets a custom header.
     *
     * @param string $name The name of the header.
     * @param string $value The value of the header.
     * @return self
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }
    
    /**
     * Sends the collected headers and body to the client.
     * This method should only be called once at the end of the request lifecycle.
     */
    public function send(): void
    {
        // Prevents headers from being sent if they have already been sent.
        if (headers_sent()) {
            return;
        }

        // Send the status code
        http_response_code($this->status);

        // Send all headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        // Send the body
        echo $this->body;
    }
}
