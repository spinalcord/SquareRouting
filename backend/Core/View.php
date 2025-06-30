<?php

declare(strict_types=1);

namespace SquareRouting\Core;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Modern Template Engine for PHP 8.3+
 * A lightweight, secure template engine with modern PHP features
 *
 * Refactored to use a simpler, more consistent syntax:
 * - {% control structure %} for logic (if, foreach, etc.)
 * - {{ expression }} for escaped output
 * - {{ expression|raw }} for raw (unescaped) output
 * - {# comment #} for template comments
 */
class View
{
    private array $variables = [];
    private string $templateDirectory = 'templates/';
    private string $cacheDirectory = 'cache/';
    private bool $cacheEnabled = false;
    private bool $autoEscape = true;

    // --- Template Inheritance Properties ---
    private ?string $extendedTemplate = null;
    private array $blocks = [];
    private ?string $activeBlock = null;

    public function __construct(
        string $templateDir = 'templates/',
        string $cacheDir = 'cache/'
    ) {
        $this->templateDirectory = rtrim($templateDir, '/') . '/';
        $this->cacheDirectory = rtrim($cacheDir, '/') . '/';
    }

    public function set(string $key, mixed $value): self
    {
        $this->variables[$key] = $value;

        return $this;
    }

    public function setMultiple(array $variables): self
    {
        $this->variables = array_merge($this->variables, $variables);

        return $this;
    }

    public function get(string $key): mixed
    {
        return $this->variables[$key] ?? null;
    }

    public function render(string $template, array $variables = []): string
    {
        $this->setMultiple($variables);
        $templateFile = $this->templateDirectory . $template;

        if (! file_exists($templateFile)) {
            throw new InvalidArgumentException("Template file not found: {$templateFile}");
        }

        // Reset inheritance properties for each main render call
        $this->extendedTemplate = null;
        $this->blocks = [];

        $cacheFile = $this->cacheDirectory . md5($template) . '.php';
        $useCache = $this->cacheEnabled &&
            file_exists($cacheFile) &&
            filemtime($cacheFile) >= filemtime($templateFile);

        if ($useCache) {
            $compiledContent = file_get_contents($cacheFile);
        } else {
            $content = file_get_contents($templateFile);
            $compiledContent = $this->compile($content);

            if ($this->cacheEnabled) {
                $this->ensureCacheDirectory();
                file_put_contents($cacheFile, $compiledContent, LOCK_EX);
            }
        }

        // Pass the compiled code (as a string) to the execution context
        return $this->executeCompiledCode($compiledContent);
    }

    public function display(string $template, array $variables = []): void
    {
        echo $this->render($template, $variables);
    }

    public function clearCache(): self
    {
        if (! is_dir($this->cacheDirectory)) {
            return $this;
        }
        $files = glob($this->cacheDirectory . '*.php');
        if ($files) {
            foreach ($files as $file) {
                unlink($file);
            }
        }

        return $this;
    }

    public function setCaching(bool $enabled): self
    {
        $this->cacheEnabled = $enabled;

        return $this;
    }

    public function setAutoEscape(bool $enabled): self
    {
        $this->autoEscape = $enabled;

        return $this;
    }

    /**
     * This method is called from the parent template to display a block.
     */
    protected function displayBlock(string $name): void
    {
        echo $this->blocks[$name] ?? '';
    }

    /**
     * Compile template content to PHP code using a unified approach.
     */
    private function compile(string $content): string
    {
        // 1. Remove template comments {# ... #}
        $content = preg_replace('/{#.*?#}/s', '', $content);

        // 2. Process control structures {% ... %}
        $content = preg_replace_callback('/{%\s*(.*?)\s*%}/s', function ($matches) {
            $body = trim($matches[1]);
            $parts = explode(' ', $body, 2);
            $keyword = $parts[0];
            $expression = $parts[1] ?? '';

            switch ($keyword) {
                // Statements with expressions and a colon
                case 'if':
                case 'elseif':
                case 'for':
                case 'foreach':
                case 'while':
                    return "<?php {$keyword}({$expression}): ?>";

                    // Statements without expressions
                case 'else':
                    return '<?php else: ?>';
                case 'endif':
                case 'endfor':
                case 'endforeach':
                case 'endwhile':
                    return "<?php {$keyword}; ?>";

                    // Custom template functions
                case 'include':
                    return "<?= \$this->render({$expression}, \$this->variables) ?>";
                case 'extends':
                    return "<?php \$this->extendTemplate({$expression}); ?>";
                case 'block':
                    return "<?php \$this->startBlock({$expression}); ?>";
                case 'endblock':
                    return '<?php $this->endBlock(); ?>';

                    // Fallback for simple PHP code: {% $var = 'value' %}
                default:
                    return "<?php {$body}; ?>";
            }
        }, $content);

        // 3. Process output expressions {{ ... }}
        $content = preg_replace_callback('/{{\s*(.*?)\s*}}/s', function ($matches) {
            $expression = trim($matches[1]);

            // Handle raw output filter: {{ my_var|raw }}
            if (str_ends_with($expression, '|raw')) {
                $expression = trim(substr($expression, 0, -4));

                return "<?= {$expression} ?>";
            }

            // Your original request for raw PHP: {{ ... }}
            if (! str_starts_with($expression, '$')) {
                return "<?php {$expression} ?>";
            }

            // Default: Escaped output for {{ $... }}
            return "<?= \$this->escape({$expression}) ?>";
        }, $content);

        return $content;
    }

    private function executeCompiledCode(string $code): string
    {
        // Extract variables for use in the template
        extract($this->variables);

        ob_start();
        try {
            eval('?>' . $code);
        } catch (Throwable $e) {
            ob_end_clean();
            throw new RuntimeException('Template execution error: ' . $e->getMessage(), 0, $e);
        }
        $content = ob_get_clean();

        // If a template is extended, render the parent template now
        if ($this->extendedTemplate) {
            // The variables are already set, just render the parent
            return $this->render($this->extendedTemplate);
        }

        return $content;
    }

    private function escape(mixed $value): string
    {
        if (! $this->autoEscape || $value === null) {
            return (string) $value;
        }

        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    // --- Helper methods for Caching ---

    private function ensureCacheDirectory(): void
    {
        if (! is_dir($this->cacheDirectory)) {
            if (! mkdir($this->cacheDirectory, 0755, true) && ! is_dir($this->cacheDirectory)) {
                throw new RuntimeException(sprintf('Directory "%s" was not created', $this->cacheDirectory));
            }
        }
    }

    // --- Helper methods for Template Inheritance ---

    private function extendTemplate(string $template): void
    {
        $this->extendedTemplate = $template;
    }

    private function startBlock(string $name): void
    {
        $this->activeBlock = $name;
        ob_start();
    }

    private function endBlock(): void
    {
        if ($this->activeBlock === null) {
            throw new RuntimeException('Cannot call endBlock() without an active block.');
        }
        $this->blocks[$this->activeBlock] = ob_get_clean();
        $this->activeBlock = null;
    }
}
