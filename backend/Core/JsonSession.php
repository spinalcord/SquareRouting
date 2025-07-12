<?php
declare(strict_types=1);
namespace SquareRouting\Core;

/**
 * JSON-basierte Session-Klasse für PHP 8.3+
 * Ersetzt die native PHP Session-Funktionalität durch JSON-Dateispeicherung
 */
class JsonSession
{
    private string $sessionId;
    private array $sessionData = [];
    private string $sessionPath;
    private int $lifetime;
    private bool $isStarted = false;
    private readonly string $cookieName;

    public function __construct(
        string $sessionPath = './sessions',
        int $lifetime = 3600,
        string $cookieName = 'JSON_SESSID'
    ) {
        $this->sessionPath = rtrim($sessionPath, '/');
        $this->lifetime = $lifetime;
        $this->cookieName = $cookieName;
        
        // Session-Verzeichnis erstellen falls nicht vorhanden
        if (!is_dir($this->sessionPath)) {
            mkdir($this->sessionPath, 0755, true);
        }
    }

    /**
     * Startet eine neue Session oder lädt eine bestehende
     */
    public function start(): bool
    {
        if ($this->isStarted) {
            return true;
        }

        $this->sessionId = $this->getSessionId();
        $this->loadSessionData();
        $this->isStarted = true;
        
        // Cookie setzen
        $this->setCookie();
        
        return true;
    }

    /**
     * Setzt einen Session-Wert
     */
    public function set(string $key, mixed $value): void
    {
        $this->ensureStarted();
        $this->sessionData[$key] = $value;
        $this->saveSessionData();
    }

    /**
     * Holt einen Session-Wert
     */
    public function get(string $key, mixed $default = null): mixed
    {
        $this->ensureStarted();
        return $this->sessionData[$key] ?? $default;
    }

    /**
     * Prüft ob ein Session-Key existiert
     */
    public function has(string $key): bool
    {
        $this->ensureStarted();
        return array_key_exists($key, $this->sessionData);
    }

    /**
     * Entfernt einen Session-Wert
     */
    public function remove(string $key): void
    {
        $this->ensureStarted();
        unset($this->sessionData[$key]);
        $this->saveSessionData();
    }

    /**
     * Holt alle Session-Daten
     */
    public function all(): array
    {
        $this->ensureStarted();
        return $this->sessionData;
    }

    /**
     * Leert alle Session-Daten
     */
    public function clear(): void
    {
        $this->ensureStarted();
        $this->sessionData = [];
        $this->saveSessionData();
    }

    /**
     * Zerstört die Session komplett
     */
    public function destroy(): bool
    {
        if (!$this->isStarted) {
            return true;
        }

        $sessionFile = $this->getSessionFilePath();
        
        if (file_exists($sessionFile)) {
            unlink($sessionFile);
        }
        
        $this->sessionData = [];
        $this->isStarted = false;
        
        // Cookie löschen
        setcookie(
            $this->cookieName,
            '',
            time() - 3600,
            '/',
            '',
            true,
            true
        );
        
        return true;
    }

    /**
     * Regeneriert die Session-ID (Sicherheitsfeature)
     */
    public function regenerate(): bool
    {
        $this->ensureStarted();
        
        // Alte Session-Datei löschen
        $oldSessionFile = $this->getSessionFilePath();
        if (file_exists($oldSessionFile)) {
            unlink($oldSessionFile);
        }
        
        // Neue Session-ID generieren
        $this->sessionId = $this->generateSessionId();
        $this->setCookie();
        $this->saveSessionData();
        
        return true;
    }

    /**
     * Holt die aktuelle Session-ID
     */
    public function getId(): string
    {
        return $this->sessionId ?? '';
    }

    /**
     * Garbage Collection - entfernt abgelaufene Sessions
     */
    public function gc(): int
    {
        $deletedCount = 0;
        $files = glob($this->sessionPath . '/sess_*.json');
        
        foreach ($files as $file) {
            if (filemtime($file) < (time() - $this->lifetime)) {
                unlink($file);
                $deletedCount++;
            }
        }
        
        return $deletedCount;
    }

    /**
     * Prüft ob die Session gestartet wurde
     */
    public function isStarted(): bool
    {
        return $this->isStarted;
    }

    /**
     * Flash-Message Funktionalität
     */
    public function flash(string $key, mixed $value = null): mixed
    {
        if ($value === null) {
            // Flash-Message lesen und löschen
            $flashValue = $this->get("_flash_{$key}");
            $this->remove("_flash_{$key}");
            return $flashValue;
        }
        
        // Flash-Message setzen
        $this->set("_flash_{$key}", $value);
        return $value;
    }

    /**
     * Holt oder generiert eine Session-ID
     */
    private function getSessionId(): string
    {
        // Versuche Session-ID aus Cookie zu holen
        $sessionId = $_COOKIE[$this->cookieName] ?? null;
        
        if ($sessionId && $this->isValidSessionId($sessionId)) {
            return $sessionId;
        }
        
        return $this->generateSessionId();
    }

    /**
     * Generiert eine sichere Session-ID
     */
    private function generateSessionId(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * Validiert eine Session-ID
     */
    private function isValidSessionId(string $sessionId): bool
    {
        return preg_match('/^[a-f0-9]{64}$/', $sessionId) === 1;
    }

    /**
     * Lädt Session-Daten aus JSON-Datei
     */
    private function loadSessionData(): void
    {
        $sessionFile = $this->getSessionFilePath();
        
        if (!file_exists($sessionFile)) {
            $this->sessionData = [];
            return;
        }
        
        // Prüfe ob Session abgelaufen ist
        if (filemtime($sessionFile) < (time() - $this->lifetime)) {
            unlink($sessionFile);
            $this->sessionData = [];
            return;
        }
        
        $content = file_get_contents($sessionFile);
        $data = json_decode($content, true);
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($data)) {
            $this->sessionData = $data;
        } else {
            $this->sessionData = [];
        }
    }

    /**
     * Speichert Session-Daten in JSON-Datei
     */
    private function saveSessionData(): void
    {
        $sessionFile = $this->getSessionFilePath();
        $jsonData = json_encode($this->sessionData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if ($jsonData === false) {
            throw new RuntimeException('Fehler beim JSON-Encoding der Session-Daten');
        }
        
        // Atomisches Schreiben mit temporärer Datei
        $tempFile = $sessionFile . '.tmp';
        
        if (file_put_contents($tempFile, $jsonData, LOCK_EX) === false) {
            throw new RuntimeException('Fehler beim Schreiben der Session-Datei');
        }
        
        if (!rename($tempFile, $sessionFile)) {
            unlink($tempFile);
            throw new RuntimeException('Fehler beim Finalisieren der Session-Datei');
        }
    }

    /**
     * Holt den Pfad zur Session-Datei
     */
    private function getSessionFilePath(): string
    {
        return $this->sessionPath . '/sess_' . $this->sessionId . '.json';
    }

    /**
     * Setzt das Session-Cookie
     */
    private function setCookie(): void
    {
        setcookie(
            $this->cookieName,
            $this->sessionId,
            time() + $this->lifetime,
            '/',
            '',
            true, // secure (nur HTTPS)
            true  // httponly
        );
    }

    /**
     * Stellt sicher, dass die Session gestartet wurde
     */
    private function ensureStarted(): void
    {
        if (!$this->isStarted) {
            throw new RuntimeException('Session wurde noch nicht gestartet');
        }
    }
}

// Beispiel für die Verwendung:
/*
try {
    $session = new JsonSession('./sessions', 3600, 'MY_SESS');
    
    // Session starten
    $session->start();
    
    // Werte setzen
    $session->set('user_id', 123);
    $session->set('username', 'max.mustermann');
    $session->set('preferences', ['theme' => 'dark', 'lang' => 'de']);
    
    // Werte lesen
    $userId = $session->get('user_id');
    $username = $session->get('username', 'Gast');
    
    // Flash-Messages
    $session->flash('success', 'Login erfolgreich!');
    $message = $session->flash('success'); // Liest und löscht die Message
    
    // Session-Sicherheit
    $session->regenerate(); // ID regenerieren nach Login
    
    // Aufräumen
    $deletedSessions = $session->gc(); // Garbage Collection
    
} catch (Exception $e) {
    echo "Session-Fehler: " . $e->getMessage();
}
*/
?>
