<?php

declare(strict_types=1);

namespace SquareRouting\Tests\Core;

use PHPUnit\Framework\TestCase;
use SquareRouting\Core\Language;
use InvalidArgumentException;
use RuntimeException;

class LanguageTest extends TestCase
{
    private Language $language;
    private string $tempDirectory;

    protected function setUp(): void
    {
        // Temporäres Verzeichnis erstellen
        $this->tempDirectory = sys_get_temp_dir() . '/language_test_' . uniqid();
        mkdir($this->tempDirectory, 0755, true);

        // Test-Sprachdateien erstellen
        $this->createTestLanguageFiles();

        // Session zurücksetzen
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }

    protected function tearDown(): void
    {
        // Temporäre Dateien und Verzeichnis löschen
        $this->removeDirectory($this->tempDirectory);

        // Session nach jedem Test zurücksetzen
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION = [];
        }
    }

    /**
     * Rekursiv ein Verzeichnis und alle Inhalte löschen
     */
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . DIRECTORY_SEPARATOR . $file;
            if (is_dir($path)) {
                $this->removeDirectory($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }

    /**
     * Erstellt Test-Sprachdateien im temporären Verzeichnis
     */
    private function createTestLanguageFiles(): void
    {
        // Englische Übersetzungen
        $enTranslations = [
            'hello' => 'Hello',
            'goodbye' => 'Goodbye',
            'welcome' => 'Welcome %s',
            'user' => [
                'name' => 'Name',
                'email' => 'Email',
                'profile' => [
                    'title' => 'User Profile'
                ]
            ],
            'messages' => [
                'success' => 'Operation successful',
                'error' => 'An error occurred'
            ]
        ];

        // Deutsche Übersetzungen
        $deTranslations = [
            'hello' => 'Hallo',
            'goodbye' => 'Auf Wiedersehen',
            'welcome' => 'Willkommen %s',
            'user' => [
                'name' => 'Name',
                'email' => 'E-Mail',
                'profile' => [
                    'title' => 'Benutzerprofil'
                ]
            ],
            'messages' => [
                'success' => 'Vorgang erfolgreich'
                // 'error' fehlt absichtlich für Fallback-Tests
            ]
        ];

        // Französische Übersetzungen (unvollständig für Tests)
        $frTranslations = [
            'hello' => 'Bonjour',
            'goodbye' => 'Au revoir'
        ];

        // JSON-Dateien erstellen
        file_put_contents($this->tempDirectory . '/en.json', json_encode($enTranslations));
        file_put_contents($this->tempDirectory . '/de.json', json_encode($deTranslations));
        file_put_contents($this->tempDirectory . '/fr.json', json_encode($frTranslations));
        
        // Ungültige JSON-Datei für Error-Tests
        file_put_contents($this->tempDirectory . '/invalid.json', '{"hello": "Hello",}'); // Trailing comma
        
        // Leere Datei
        file_put_contents($this->tempDirectory . '/empty.json', '');
        
        // Nicht-Array JSON
        file_put_contents($this->tempDirectory . '/string.json', '"This is a string"');
    }

    // =================================================================
    // CONSTRUCTOR TESTS
    // =================================================================

    public function testConstructorWithValidDirectory(): void
    {
        $language = new Language($this->tempDirectory);

        $this->assertEquals('en', $language->getCurrentLanguage());
        $this->assertEquals('en', $language->getFallbackLanguage());
    }

    public function testConstructorWithCustomDefaults(): void
    {
        $language = new Language($this->tempDirectory, 'de', 'fr');

        $this->assertEquals('de', $language->getCurrentLanguage());
        $this->assertEquals('fr', $language->getFallbackLanguage());
    }

    public function testConstructorThrowsExceptionForNonExistentDirectory(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Language directory '/nonexistent' does not exist");

        new Language('/nonexistent');
    }

    public function testConstructorThrowsExceptionForNonExistentDefaultLanguage(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Cannot load language file");

        new Language($this->tempDirectory, 'nonexistent');
    }

    // =================================================================
    // LANGUAGE SETTING TESTS
    // =================================================================

    public function testSetLanguageSuccess(): void
    {
        $this->language = new Language($this->tempDirectory);
        
        $result = $this->language->setLanguage('de');

        $this->assertInstanceOf(Language::class, $result);
        $this->assertEquals('de', $this->language->getCurrentLanguage());
    }

    public function testSetLanguageWithSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $this->language = new Language($this->tempDirectory);
        $this->language->setLanguage('de', true);

        $this->assertEquals('de', $_SESSION['language']);
    }

    public function testSetLanguageThrowsExceptionForNonExistentLanguage(): void
    {
        $this->language = new Language($this->tempDirectory);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage("Language file for 'nonexistent' not found");

        $this->language->setLanguage('nonexistent');
    }

    // =================================================================
    // TRANSLATION TESTS
    // =================================================================

    public function testTranslateSimpleKey(): void
    {
        $this->language = new Language($this->tempDirectory);

        $this->assertEquals('Hello', $this->language->translate('hello'));
        $this->assertEquals('Goodbye', $this->language->translate('goodbye'));
    }

    public function testTranslateNestedKey(): void
    {
        $this->language = new Language($this->tempDirectory);

        $this->assertEquals('Name', $this->language->translate('user.name'));
        $this->assertEquals('User Profile', $this->language->translate('user.profile.title'));
    }

    public function testTranslateWithStringFormatting(): void
    {
        $this->language = new Language($this->tempDirectory);

        $result = $this->language->translate('welcome', 'John');
        $this->assertEquals('Welcome John', $result);
    }

    public function testTranslateWithMultipleArguments(): void
    {
        // Füge eine Übersetzung mit mehreren Platzhaltern hinzu
        file_put_contents($this->tempDirectory . '/en.json', json_encode([
            'greeting' => 'Hello %s, you have %d messages'
        ]));

        $this->language = new Language($this->tempDirectory);

        $result = $this->language->translate('greeting', 'John', 5);
        $this->assertEquals('Hello John, you have 5 messages', $result);
    }

    public function testTranslateReturnsKeyWhenNotFound(): void
    {
        $this->language = new Language($this->tempDirectory);

        $this->assertEquals('nonexistent.key', $this->language->translate('nonexistent.key'));
    }

    public function testTranslateAlias(): void
    {
        $this->language = new Language($this->tempDirectory);

        $this->assertEquals('Hello', $this->language->t('hello'));
        $this->assertEquals('Welcome John', $this->language->t('welcome', 'John'));
    }

    // =================================================================
    // FALLBACK TESTS
    // =================================================================

    public function testFallbackLanguageWhenTranslationMissing(): void
    {
        $this->language = new Language($this->tempDirectory, 'de', 'en');

        // 'messages.error' existiert nur in englischer Sprache
        $this->assertEquals('An error occurred', $this->language->translate('messages.error'));
    }

    public function testNoFallbackWhenLanguageIsSame(): void
    {
        $this->language = new Language($this->tempDirectory, 'en', 'en');

        // Sollte den Key zurückgeben, da kein Fallback versucht wird
        $this->assertEquals('nonexistent.key', $this->language->translate('nonexistent.key'));
    }

    public function testSetFallbackLanguage(): void
    {
        $this->language = new Language($this->tempDirectory);
        
        $result = $this->language->setFallbackLanguage('de');

        $this->assertInstanceOf(Language::class, $result);
        $this->assertEquals('de', $this->language->getFallbackLanguage());
    }

    // =================================================================
    // TRANSLATION EXISTENCE TESTS
    // =================================================================

    public function testHasTranslationSimpleKey(): void
    {
        $this->language = new Language($this->tempDirectory);

        $this->assertTrue($this->language->hasTranslation('hello'));
        $this->assertFalse($this->language->hasTranslation('nonexistent'));
    }

    public function testHasTranslationNestedKey(): void
    {
        $this->language = new Language($this->tempDirectory);

        $this->assertTrue($this->language->hasTranslation('user.name'));
        $this->assertTrue($this->language->hasTranslation('user.profile.title'));
        $this->assertFalse($this->language->hasTranslation('user.nonexistent'));
        $this->assertFalse($this->language->hasTranslation('user.profile.nonexistent'));
    }

    // =================================================================
    // AVAILABLE LANGUAGES TESTS
    // =================================================================

    public function testGetAvailableLanguages(): void
    {
        $this->language = new Language($this->tempDirectory);

        $languages = $this->language->getAvailableLanguages();

        $this->assertIsArray($languages);
        $this->assertContains('en', $languages);
        $this->assertContains('de', $languages);
        $this->assertContains('fr', $languages);
        
        // Ungültige Dateien sollten nicht enthalten sein
        $this->assertNotContains('invalid', $languages);
        $this->assertNotContains('empty', $languages);
        $this->assertNotContains('string', $languages);
    }

    public function testGetAvailableLanguagesWithRegionalCodes(): void
    {
        // Erstelle Dateien mit regionalen Codes
        file_put_contents($this->tempDirectory . '/en-US.json', '{"hello": "Hello USA"}');
        file_put_contents($this->tempDirectory . '/de-DE.json', '{"hello": "Hallo Deutschland"}');
        file_put_contents($this->tempDirectory . '/invalid-code.json', '{"hello": "Invalid"}');

        $this->language = new Language($this->tempDirectory);

        $languages = $this->language->getAvailableLanguages();

        $this->assertContains('en-US', $languages);
        $this->assertContains('de-DE', $languages);
        $this->assertNotContains('invalid-code', $languages); // Ungültiges Format
    }

    // =================================================================
    // SESSION TESTS
    // =================================================================

    public function testLoadFromSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['language'] = 'de';

        $this->language = new Language($this->tempDirectory);
        $result = $this->language->loadFromSession();

        $this->assertInstanceOf(Language::class, $result);
        $this->assertEquals('de', $this->language->getCurrentLanguage());
    }

    public function testLoadFromSessionWithInvalidLanguage(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['language'] = 'nonexistent';

        $this->language = new Language($this->tempDirectory);
        $originalLanguage = $this->language->getCurrentLanguage();
        
        $this->language->loadFromSession();

        // Sprache sollte unverändert bleiben
        $this->assertEquals($originalLanguage, $this->language->getCurrentLanguage());
    }

    public function testClearSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $_SESSION['language'] = 'de';

        $this->language = new Language($this->tempDirectory);
        $result = $this->language->clearSession();

        $this->assertInstanceOf(Language::class, $result);
        $this->assertArrayNotHasKey('language', $_SESSION);
    }

    // =================================================================
    // GET ALL TRANSLATIONS TESTS
    // =================================================================

    public function testGetAllTranslations(): void
    {
        $this->language = new Language($this->tempDirectory);

        $translations = $this->language->getAllTranslations();

        $this->assertIsArray($translations);
        $this->assertArrayHasKey('hello', $translations);
        $this->assertArrayHasKey('user', $translations);
        $this->assertEquals('Hello', $translations['hello']);
    }

    // =================================================================
    // ERROR HANDLING TESTS
    // =================================================================

    public function testLoadLanguageWithInvalidJson(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');

        new Language($this->tempDirectory, 'invalid');
    }

    public function testLoadLanguageWithEmptyFile(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid JSON');

        new Language($this->tempDirectory, 'empty');
    }

    public function testLoadLanguageWithNonArrayJson(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must contain a JSON object');

        new Language($this->tempDirectory, 'string');
    }

    // =================================================================
    // EDGE CASES AND SPECIAL SCENARIOS
    // =================================================================

    public function testTranslateWithEmptyKey(): void
    {
        $this->language = new Language($this->tempDirectory);

        $this->assertEquals('', $this->language->translate(''));
    }

    public function testTranslateWithDotOnlyKey(): void
    {
        $this->language = new Language($this->tempDirectory);

        $this->assertEquals('.', $this->language->translate('.'));
    }

    public function testNestedValueWithNonStringValue(): void
    {
        // Erstelle eine Übersetzung mit einem numerischen Wert
        file_put_contents($this->tempDirectory . '/test.json', json_encode([
            'number' => 42,
            'boolean' => true,
            'array' => ['item1', 'item2']
        ]));

        $this->language = new Language($this->tempDirectory, 'test');

        // Nicht-String-Werte sollten null zurückgeben und damit den Key
        $this->assertEquals('number', $this->language->translate('number'));
        $this->assertEquals('boolean', $this->language->translate('boolean'));
        $this->assertEquals('array', $this->language->translate('array'));
    }

    public function testFallbackWithCorruptFallbackFile(): void
    {
        // Überschreibe die englische Datei mit ungültigem JSON
        file_put_contents($this->tempDirectory . '/en.json', '{"invalid": json}');

        $this->language = new Language($this->tempDirectory, 'de', 'en');

        // Sollte den Key zurückgeben, da Fallback fehlschlägt
        $this->assertEquals('nonexistent.key', $this->language->translate('nonexistent.key'));
    }

    public function testDirectoryPathNormalization(): void
    {
        // Teste mit verschiedenen Pfad-Trennzeichen
        $pathWithTrailingSlash = $this->tempDirectory . '/';
        $pathWithBackslash = str_replace('/', DIRECTORY_SEPARATOR, $this->tempDirectory) . DIRECTORY_SEPARATOR;

        $language1 = new Language($pathWithTrailingSlash);
        $language2 = new Language($pathWithBackslash);

        $this->assertEquals('Hello', $language1->translate('hello'));
        $this->assertEquals('Hello', $language2->translate('hello'));
    }

    public function testComplexNestedTranslation(): void
    {
        // Erstelle eine komplexe verschachtelte Struktur
        file_put_contents($this->tempDirectory . '/complex.json', json_encode([
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => 'Deep nested value'
                    ]
                ]
            ]
        ]));

        $this->language = new Language($this->tempDirectory, 'complex');

        $this->assertEquals('Deep nested value', $this->language->translate('level1.level2.level3.level4'));
        $this->assertEquals('level1.level2.level3.nonexistent', $this->language->translate('level1.level2.level3.nonexistent'));
    }

    // =================================================================
    // ADDITIONAL PRACTICAL TESTS
    // =================================================================

    public function testLanguageSwitchingBetweenMultipleLanguages(): void
    {
        $this->language = new Language($this->tempDirectory);

        // Start mit Englisch
        $this->assertEquals('Hello', $this->language->translate('hello'));

        // Wechsel zu Deutsch
        $this->language->setLanguage('de');
        $this->assertEquals('Hallo', $this->language->translate('hello'));

        // Wechsel zu Französisch
        $this->language->setLanguage('fr');
        $this->assertEquals('Bonjour', $this->language->translate('hello'));
    }

    public function testMixedCaseAndSpecialCharactersInKeys(): void
    {
        // Erstelle Übersetzungen mit speziellen Keys
        file_put_contents($this->tempDirectory . '/special.json', json_encode([
            'UPPERCASE' => 'Upper case value',
            'with-dashes' => 'Dashes value',
            'with_underscores' => 'Underscores value',
            'with.dots.in.key' => 'Dots value',
            'numbers123' => 'Numbers value'
        ]));

        $this->language = new Language($this->tempDirectory, 'special');

        $this->assertEquals('Upper case value', $this->language->translate('UPPERCASE'));
        $this->assertEquals('Dashes value', $this->language->translate('with-dashes'));
        $this->assertEquals('Underscores value', $this->language->translate('with_underscores'));
        $this->assertEquals('Numbers value', $this->language->translate('numbers123'));
        
        // Key mit Punkten sollte nicht als verschachtelt interpretiert werden
        $this->assertEquals('with.dots.in.key', $this->language->translate('with.dots.in.key'));
    }

    public function testSprintfFormattingEdgeCases(): void
    {
        file_put_contents($this->tempDirectory . '/format.json', json_encode([
            'no_placeholders' => 'No formatting needed',
            'single_string' => 'Hello %s',
            'multiple_mixed' => 'User %s has %d points and %.2f rating',
            'with_percent' => 'Progress: %d%% complete'
        ]));

        $this->language = new Language($this->tempDirectory, 'format');

        // Keine Argumente bei Text ohne Platzhalter
        $this->assertEquals('No formatting needed', $this->language->translate('no_placeholders', 'extra', 'args'));

        // Verschiedene Formatierungen
        $this->assertEquals('Hello World', $this->language->translate('single_string', 'World'));
        $this->assertEquals('User John has 100 points and 4.50 rating', 
            $this->language->translate('multiple_mixed', 'John', 100, 4.5));
        $this->assertEquals('Progress: 75% complete', $this->language->translate('with_percent', 75));
    }
}