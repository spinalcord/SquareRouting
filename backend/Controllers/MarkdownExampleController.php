<?php
namespace SquareRouting\Controllers;

use SquareRouting\Core\DependencyContainer;
use SquareRouting\Core\MarkdownRenderer;
use SquareRouting\Core\Response;
use SquareRouting\Core\Cache;

class MarkdownExampleController
{
    public MarkdownRenderer $mdr;
    public Cache $cache;

    public function __construct(DependencyContainer $container)
    {
        $this->mdr = $container->get(MarkdownRenderer::class);
        $this->cache = $container->get(Cache::class);
    }

    public function showMarkdownExample(): Response
    {
        $inhalt = "# Welcome to SquareRouting Framework

## Getting Started

This is a **powerful** and *flexible* PHP routing framework that makes building web applications simple and enjoyable.

### Key Features

- **Fast Routing**: Lightning-fast route matching and dispatching
- **Flexible Controllers**: Clean, organized controller structure
- **Markdown Rendering**: Built-in support for rendering Markdown content

### Quick Example

Here's how easy it is to define a route:

```php
echo \"hello world\";
```

### Why Choose SquareRouting?

1. **Simple Setup**: Get started in minutes
2. **Modern PHP**: Built with PHP 8+ features
3. **Extensible**: Easy to customize and extend
4. **Well Documented**: Comprehensive documentation and examples

> \"Simplicity is the ultimate sophistication.\" - Leonardo da Vinci

### Code Example

```php
class UserController
{
    public function index(): Response
    {
        return (new Response)->json([
            'users' => User::all()
        ]);
    }
}
```

---

*Built with ❤️*";

        // Clear cache first to show difference
        $this->mdr->clearCache();
        
        // Test WITHOUT cache (first run after clearing)
        $start = microtime(true);
        $htmlContent = $this->mdr->render($inhalt);
        $end = microtime(true);
        $firstRunTime = ($end - $start) * 1000;
        
        // Test WITH cache (multiple runs)
        $runs = 9; // 9 more runs to make total 10
        $cachedTimes = [];
        
        for ($i = 0; $i < $runs; $i++) {
            $start = microtime(true);
            $this->mdr->render($inhalt);
            $end = microtime(true);
            $cachedTimes[] = ($end - $start) * 1000;
        }
        
        // Calculate statistics
        $avgCachedTime = array_sum($cachedTimes) / count($cachedTimes);
        $minCachedTime = min($cachedTimes);
        $maxCachedTime = max($cachedTimes);
        $speedup = round($firstRunTime / $avgCachedTime, 1);
        
        // Cache status info
        $cacheInfo = "
        <div style='background: #e8f5e8; padding: 20px; margin: 20px 0; border-left: 4px solid #4caf50;'>
            <h3>💾 Cache Performance Test</h3>
            <p><strong>Cache cleared before test:</strong> Yes</p>
            <p><strong>First run (no cache):</strong> " . number_format($firstRunTime, 2) . " ms</p>
            <p><strong>Cached runs (avg of {$runs}):</strong> " . number_format($avgCachedTime, 2) . " ms</p>
            <p><strong>Speed improvement:</strong> {$speedup}x faster</p>
            <p><strong>Time saved:</strong> " . number_format($firstRunTime - $avgCachedTime, 2) . " ms per request</p>
        </div>";
        
        // Detailed benchmark
        $benchmark = "
        <div style='background: #f0f0f0; padding: 20px; margin: 20px 0; border-left: 4px solid #007cba;'>
            <h3>🚀 Detailed Performance Benchmark</h3>
            <p><strong>Total runs:</strong> 10 (1 without cache + 9 with cache)</p>
            <p><strong>Without cache:</strong> " . number_format($firstRunTime, 2) . " ms</p>
            <p><strong>With cache (fastest):</strong> " . number_format($minCachedTime, 2) . " ms</p>
            <p><strong>With cache (slowest):</strong> " . number_format($maxCachedTime, 2) . " ms</p>
            <p><strong>With cache (average):</strong> " . number_format($avgCachedTime, 2) . " ms</p>
            <p><strong>Cached run times:</strong> " . implode(' ms, ', array_map(fn($t) => number_format($t, 2), $cachedTimes)) . " ms</p>
        </div>";
        
        // Navigation links
        $navigation = "
        <div style='background: #fff3cd; padding: 15px; margin: 20px 0; border-left: 4px solid #ffc107;'>
            <h3>🔄 Test Cache Again</h3>
            <p>Refresh this page to see the cache in action, or <a href='?clear=1' style='color: #d32f2f; font-weight: bold;'>click here to clear cache and test again</a></p>
        </div>";
        
        return (new Response)->html($navigation . $cacheInfo . $benchmark . $htmlContent);
    }
    
    public function clearCacheAndTest(): Response
    {
        // Clear all markdown cache
        $this->mdr->clearCache();
        
        $message = "
        <div style='background: #ffebee; padding: 20px; margin: 20px 0; border-left: 4px solid #f44336;'>
            <h3>🗑️ Cache Cleared</h3>
            <p>All markdown cache has been cleared. <a href='/markdown-example'>Go back to test performance</a></p>
        </div>";
        
        return (new Response)->html($message);
    }
}
