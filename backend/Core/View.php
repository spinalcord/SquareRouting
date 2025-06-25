<?php

declare(strict_types=1);
namespace SquareRouting\Core;
use InvalidArgumentException;
/**
 * Modern Template Engine for PHP 8.3+
 * A lightweight, secure template engine with modern PHP features
 */
class View
{
    private array $variables = [];
    private string $templateDirectory = 'templates/';
    private string $cacheDirectory = 'cache/';
    private bool $cacheEnabled = true;
    private bool $autoEscape = true;

    public function __construct(
        string $templateDir = 'templates/',
        string $cacheDir = 'cache/'
    ) {
        $this->templateDirectory = rtrim($templateDir, '/') . '/';
        $this->cacheDirectory = rtrim($cacheDir, '/') . '/';
    }

    /**
     * Assign a variable to the template
     */
    public function assign(string $key, mixed $value): self
    {
        $this->variables[$key] = $value;
        return $this;
    }

    /**
     * Assign multiple variables at once
     */
    public function assignMultiple(array $variables): self
    {
        $this->variables = array_merge($this->variables, $variables);
        return $this;
    }

    /**
     * Get a variable value
     */
    public function get(string $key): mixed
    {
        return $this->variables[$key] ?? null;
    }


    /**
     * Render template and return content
     */
    public function render(string $template, array $variables = []): string
    {
        $this->assignMultiple($variables);
        
        $templateFile = $this->templateDirectory . $template;
        
        if (!file_exists($templateFile)) {
            throw new InvalidArgumentException("Template file not found: {$templateFile}");
        }

        $cacheFile = $this->cacheDirectory . md5($template) . '.php';
        $useCache = $this->cacheEnabled && 
                   file_exists($cacheFile) && 
                   filemtime($cacheFile) >= filemtime($templateFile);

        if ($useCache) {
            return $this->executeCompiledTemplate($cacheFile);
        }

        $content = file_get_contents($templateFile);
        $compiled = $this->compile($content);
        
        if ($this->cacheEnabled) {
            $this->ensureCacheDirectory();
            file_put_contents($cacheFile, $compiled, LOCK_EX);
        }
        
        return $this->executeCompiledCode($compiled);
    }

    /**
     * Render template directly to output
     */
    public function display(string $template, array $variables = []): void
    {
        echo $this->render($template, $variables);
    }

    /**
     * Compile template content to PHP code
     */
    private function compile(string $content): string
    {
        // Remove HTML comments
        $content = preg_replace('/<!--.*?-->/s', '', $content);
        
        // Process template inheritance
        $content = $this->processInheritance($content);
        
        // Process loops
        $content = $this->processLoops($content);
        
        // Process conditions
        $content = $this->processConditions($content);
        
        // Process includes
        $content = $this->processIncludes($content);
        
        // Process variables and translations
        $content = $this->processVariables($content);
        
        return $content;
    }

    /**
     * Process template inheritance
     */
    private function processInheritance(string $content): string
    {
        // Handle {extends "parent"}
        $content = preg_replace_callback(
            '/\{extends\s+"([^"]+)"\}/',
            fn($matches) => "<?php \$this->extendTemplate('{$matches[1]}'); ?>",
            $content
        );
        
        // Handle {block "name"}...{/block}
        $content = preg_replace_callback(
            '/\{block\s+"([^"]+)"\}(.*?)\{\/block\}/s',
            fn($matches) => "<?php \$this->startBlock('{$matches[1]}'); ?>{$matches[2]}<?php \$this->endBlock(); ?>",
            $content
        );
        
        return $content;
    }

    /**
     * Process loops
     */
    private function processLoops(string $content): string
    {
        // Handle {foreach $items as $item}...{/foreach}
        $content = preg_replace_callback(
            '/\{foreach\s+\$([a-zA-Z_][a-zA-Z0-9_]*)\s+as\s+\$([a-zA-Z_][a-zA-Z0-9_]*)\}(.*?)\{\/foreach\}/s',
            function($matches) {
                $array = $matches[1];
                $item = $matches[2];
                $loopContent = $matches[3];
                
                // Replace variables in loop content
                $loopContent = preg_replace(
                    '/\{\$' . $item . '\.([a-zA-Z0-9_]+)\}/',
                    '<?php echo $this->escape($' . $item . '[\'$1\']); ?>',
                    $loopContent
                );
                
                $loopContent = preg_replace(
                    '/\{\$' . $item . '\}/',
                    '<?php echo $this->escape($' . $item . '); ?>',
                    $loopContent
                );
                
                return "<?php foreach (\$this->variables['{$array}'] ?? [] as \${$item}): ?>{$loopContent}<?php endforeach; ?>";
            },
            $content
        );
        
        return $content;
    }

    /**
     * Process conditional statements
     */
    private function processConditions(string $content): string
    {
        // Handle {if condition}...{/if}
        $content = preg_replace_callback(
            '/\{if\s+(.+?)\}(.*?)(\{else\}(.*?))?\{\/if\}/s',
            function($matches) {
                $condition = $this->parseCondition($matches[1]);
                $ifContent = $matches[2];
                $elseContent = $matches[4] ?? '';
                
                $result = "<?php if ({$condition}): ?>{$ifContent}";
                if ($elseContent) {
                    $result .= "<?php else: ?>{$elseContent}";
                }
                $result .= "<?php endif; ?>";
                
                return $result;
            },
            $content
        );
        
        return $content;
    }

    /**
     * Process template includes
     */
    private function processIncludes(string $content): string
    {
        return preg_replace(
            '/\{include\s+"([^"]+)"\}/',
            '<?php echo $this->render(\'$1\'); ?>',
            $content
        );
    }


    /**
     * Process variables and translations
     */
    private function processVariables(string $content): string
    {
        // Handle raw variables {$var|raw}
        $content = preg_replace(
            '/\{\$([a-zA-Z_][a-zA-Z0-9_]*)\|raw\}/',
            '<?php echo $this->variables[\'$1\'] ?? \'\'; ?>',
            $content
        );
        
        // Handle array variables {$var.key}
        $content = preg_replace(
            '/\{\$([a-zA-Z_][a-zA-Z0-9_]*)\.([a-zA-Z0-9_]+)\}/',
            '<?php echo $this->escape($this->variables[\'$1\'][\'$2\'] ?? \'\'); ?>',
            $content
        );
        
        // Handle simple variables {$var}
        $content = preg_replace(
            '/\{\$([a-zA-Z_][a-zA-Z0-9_]*)\}/',
            '<?php echo $this->escape($this->variables[\'$1\'] ?? \'\'); ?>',
            $content
        );
        
        return $content;
    }

    /**
     * Parse condition for if statements
     */
    private function parseCondition(string $condition): string
    {
        // Replace variables with proper array access
        $condition = preg_replace(
            '/\$([a-zA-Z_][a-zA-Z0-9_]*)/',
            '($this->variables[\'$1\'] ?? null)',
            $condition
        );
        
        return $condition;
    }

    /**
     * Execute compiled template from file
     */
    private function executeCompiledTemplate(string $cacheFile): string
    {
        ob_start();
        
        try {
            include $cacheFile;
        } catch (Throwable $e) {
            ob_end_clean();
            throw new RuntimeException("Template execution error: " . $e->getMessage(), 0, $e);
        }
        
        return ob_get_clean();
    }

    /**
     * Execute compiled PHP code
     */
    private function executeCompiledCode(string $code): string
    {
        ob_start();
        
        try {
            eval('?>' . $code);
        } catch (Throwable $e) {
            ob_end_clean();
            throw new RuntimeException("Template compilation error: " . $e->getMessage(), 0, $e);
        }
        
        return ob_get_clean();
    }

    /**
     * Escape output for security
     */
    private function escape(mixed $value): string
    {
        if (!$this->autoEscape) {
            return (string) $value;
        }
        
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Ensure cache directory exists
     */
    private function ensureCacheDirectory(): void
    {
        if (!is_dir($this->cacheDirectory)) {
            mkdir($this->cacheDirectory, 0755, true);
        }
    }

    /**
     * Clear all cached templates
     */
    public function clearCache(): self
    {
        if (is_dir($this->cacheDirectory)) {
            $files = glob($this->cacheDirectory . '*.php');
            foreach ($files as $file) {
                unlink($file);
            }
        }
        
        return $this;
    }

    /**
     * Enable or disable caching
     */
    public function setCaching(bool $enabled): self
    {
        $this->cacheEnabled = $enabled;
        return $this;
    }

    /**
     * Enable or disable auto-escaping
     */
    public function setAutoEscape(bool $enabled): self
    {
        $this->autoEscape = $enabled;
        return $this;
    }
}