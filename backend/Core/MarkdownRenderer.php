<?php
namespace SquareRouting\Core;

use Exception;

class MarkdownRenderer
{
    private array $blockPatterns;
    private array $inlinePatterns;
    private array $usedIds = [];
    private ?Cache $cache = null;

    public function __construct(?Cache $cache = null)
    {
        $this->cache = $cache;
        $this->initializePatterns();
    }

    public function render(string $markdown): string
    {
        if ($this->cache) {
            $cacheKey = $this->generateCacheKey($markdown);
            return $this->cache->get('markdown', $cacheKey, fn() => $this->doRender($markdown), 3600);
        }
        return $this->doRender($markdown);
    }

    public function renderFile(string $filePath): string
    {
        if (!file_exists($filePath)) {
            throw new Exception("Markdown file not found: {$filePath}");
        }
        $markdown = file_get_contents($filePath);

        if ($this->cache) {
            $fileModTime = filemtime($filePath);
            $cacheKey = $this->generateFileCacheKey($filePath, $fileModTime);
            return $this->cache->get('markdown_file', $cacheKey, fn() => $this->doRender($markdown), 3600);
        }

        return $this->doRender($markdown);
    }

    public function renderToFile(string $markdown, string $filename): bool
    {
        $html = $this->render($markdown);
        return file_put_contents($filename, $html) !== false;
    }

    public function clearCache(?string $filePath = null): void
    {
        if (!$this->cache) {
            return;
        }
        if ($filePath) {
            $this->cache->clear('markdown_file');
        } else {
            $this->cache->clear('markdown');
            $this->cache->clear('markdown_file');
        }
    }

    public function validate(string $markdown): array
    {
        $errors = [];
        $lines = explode("\n", $markdown);

        foreach ($lines as $lineNum => $line) {
            if (preg_match('/^#{7,}/', $line)) {
                $errors[] = 'Zeile ' . ($lineNum + 1) . ': Zu viele # für Überschrift (max. 6)';
            }
            if (preg_match('/\[([^\]]+)\]\(([^)]*)\)/', $line, $matches) && empty($matches[2])) {
                $errors[] = 'Zeile ' . ($lineNum + 1) . ': Leere URL in Link';
            }
        }
        return $errors;
    }

    private function initializePatterns(): void
    {
        $this->inlinePatterns = [
            'escape' => '/\\\\([\\`*_{}\[\]()#+\-.!])/',
            'code' => '/`([^`\n]+)`/',
            'strong' => '/\*\*([^*\n]+?)\*\*/',
            'em' => '/(?<!\*)\*([^*\n]+?)\*(?!\*)/',
            'strikethrough' => '/~~([^~\n]+?)~~/',
            'image' => '/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]+)")?\)/',
            'link' => '/\[([^\]]+)\]\(([^)\s]+)(?:\s+"([^"]+)")?\)/',
            'autolink' => '/(?<![\[\(])(https?:\/\/[^\s<>\[\]()]{2,})(?![^\s]*[\]\)])/',
        ];
    }

    private function doRender(string $markdown): string
    {
        $this->usedIds = [];

        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

        // Front Matter und Metadaten in einem Schritt entfernen
        $result = $this->extractFrontMatter($markdown);
        $markdown = $result['content'];

        $blocks = $this->parseBlocks($markdown);
        $html = '';

        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block);
        }

        return trim($html);
    }

    private function extractFrontMatter(string $markdown): array
    {
        if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $markdown, $matches)) {
            return [
                'content' => substr($markdown, strlen($matches[0])),
            ];
        }
        return ['content' => $markdown];
    }

    private function parseBlocks(string $markdown): array
    {
        $blocks = [];
        $lines = explode("\n", $markdown);
        $i = 0;

        while ($i < count($lines)) {
            $line = $lines[$i];

            // 1. Codeblock (hat höchste Priorität)
            if (str_starts_with(trim($line), '```')) {
                [$codeBlock, $newIndex] = $this->parseCodeBlock($lines, $i);
                $blocks[] = $codeBlock;
                $i = $newIndex;
                continue;
            }

            // 2. Tabelle
            if (str_starts_with($line, '|')) {
                [$tableBlock, $newIndex] = $this->parseTableBlock($lines, $i);
                $blocks[] = $tableBlock;
                $i = $newIndex;
                continue;
            }

            // 3. Blockquote
            if (str_starts_with($line, '>')) {
                [$quoteLines, $newIndex] = $this->collectLines($lines, $i, fn($l) => str_starts_with($l, '>'));
                $blocks[] = ['type' => 'blockquote', 'content' => implode("\n", $quoteLines)];
                $i = $newIndex;
                continue;
            }

            // 4. Horizontal Rule
            if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', trim($line))) {
                $blocks[] = ['type' => 'hr'];
                $i++;
                continue;
            }

            // 5. Listen und Absätze
            if (trim($line) === '') {
                $i++;
                continue;
            }

            if (preg_match('/^(\s*)([\*\-\+]|\d+\.)\s+(.+)$/', $line)) {
                [$listBlock, $newIndex] = $this->parseListBlock($lines, $i);
                $blocks[] = $listBlock;
                $i = $newIndex;
                continue;
            }

            // 6. Überschrift
            if (preg_match('/^#{1,6}\s+/', $line)) {
                $blocks[] = ['type' => 'heading', 'content' => $line];
                $i++;
                continue;
            }

            // 7. Absatz
            [$paraLines, $newIndex] = $this->collectLinesUntil($lines, $i, fn($l, $next) => 
                !in_array('', [$l, $next]) && 
                !str_starts_with($next, '|') && 
                !str_starts_with($next, '>') && 
                !preg_match('/^#{1,6}\s+/', $next) &&
                !str_starts_with(trim($next), '```')
            );
            $blocks[] = ['type' => 'paragraph', 'content' => implode("\n", $paraLines)];
            $i = $newIndex;
        }

        return $blocks;
    }

    private function parseCodeBlock(array $lines, int $start): array
    {
        $firstLine = $lines[$start];
        $language = '';
        if (preg_match('/^```(\w+)?/', $firstLine, $m)) {
            $language = $m[1] ?? '';
        }
        $code = '';
        $i = $start + 1;
        while ($i < count($lines) && !str_starts_with(trim($lines[$i]), '```')) {
            $code .= $lines[$i] . "\n";
            $i++;
        }
        $i++; // Skip closing ```
        $content = rtrim($code, "\n");
        return [['type' => 'code_block', 'content' => $content, 'language' => $language], $i];
    }

    private function parseTableBlock(array $lines, int $start): array
    {
        $rows = [];
        $i = $start;
        while ($i < count($lines) && str_starts_with($lines[$i], '|')) {
            $rows[] = $lines[$i];
            $i++;
        }
        return [['type' => 'table', 'rows' => $rows], $i];
    }

    private function parseListBlock(array $lines, int $start): array
    {
        $items = [];
        $i = $start;

        while ($i < count($lines)) {
            $line = $lines[$i];
            if (trim($line) === '' || !preg_match('/^(\s*)([\*\-\+]|\d+\.)\s+(.+)$/', $line)) {
                break;
            }

            $indent = $this->getIndentLevel($line);
            $content = preg_replace('/^(\s*)([\*\-\+]|\d+\.)\s+/', '', $line);
            $items[] = ['content' => $content, 'indent' => $indent];
            $i++;
        }

        $tag = preg_match('/^(\s*)\d+\./', $lines[$start]) ? 'ordered_list' : 'unordered_list';
        return [['type' => $tag, 'items' => $items], $i];
    }

    private function collectLines(array $lines, int $start, callable $condition): array
    {
        $collected = [];
        $i = $start;
        while ($i < count($lines) && $condition($lines[$i])) {
            $collected[] = $lines[$i];
            $i++;
        }
        return [$collected, $i];
    }

    private function collectLinesUntil(array $lines, int $start, callable $stopWhenTrue): array
    {
        $collected = [$lines[$start]];
        $i = $start + 1;
        while ($i < count($lines)) {
            $current = $lines[$i];
            $next = $i + 1 < count($lines) ? $lines[$i + 1] : '';
            if ($stopWhenTrue($current, $next)) {
                break;
            }
            $collected[] = $current;
            $i++;
        }
        return [$collected, $i];
    }

    private function getIndentLevel(string $line): int
    {
        return (int) preg_match('/^(\s*)/', $line, $m) ? strlen($m[1]) : 0;
    }

    private function renderBlock(array $block): string
    {
        return match ($block['type']) {
            'heading' => $this->renderHeading($block['content']),
            'paragraph' => $this->renderParagraph($block['content']),
            'code_block' => $this->renderCodeBlock($block['content'], $block['language'] ?? ''),
            'hr' => "<hr>\n",
            'blockquote' => $this->renderBlockquote($block['content']),
            'unordered_list' => $this->renderList($block['items'], 'ul'),
            'ordered_list' => $this->renderList($block['items'], 'ol'),
            'table' => $this->renderTable($block['rows']),
            default => $this->renderParagraph($block['content']),
        };
    }

    private function renderHeading(string $line): string
    {
        if (!preg_match('/^(#{1,6})\s+(.+)$/', $line, $m)) {
            return '';
        }
        $level = strlen($m[1]);
        $text = $this->renderInline($m[2]);
        $id = $this->generateUniqueId($m[2]);
        return "<h{$level} id=\"{$id}\">{$text}</h{$level}>\n";
    }

    private function renderParagraph(string $content): string
    {
        $content = str_replace("\n", ' ', $content);
        $content = $this->renderInline($content);
        return "<p>{$content}</p>\n";
    }

    private function renderCodeBlock(string $content, string $language): string
    {
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $class = $language ? " class=\"language-{$language}\"" : '';
        return "<pre><code{$class}>{$content}</code></pre>\n";
    }

    private function renderBlockquote(string $content): string
    {
        $lines = explode("\n", $content);
        $parsed = [];
        foreach ($lines as $line) {
            $line = trim(preg_replace('/^>\s*/', '', $line));
            if ($line !== '') {
                $parsed[] = $this->renderInline($line);
            }
        }
        $content = implode(' ', $parsed);
        return "<blockquote><p>{$content}</p></blockquote>\n";
    }

    private function renderList(array $items, string $tag): string
    {
        if (empty($items)) return '';

        $tree = $this->buildListTree($items);
        return $this->renderListTree($tree, $tag);
    }

    private function buildListTree(array $items): array
    {
        $tree = [];
        $stack = [];

        foreach ($items as $item) {
            $level = (int) floor($item['indent'] / 4); // 4 Leerzeichen pro Ebene (CommonMark)
            $node = ['content' => $item['content'], 'level' => $level, 'children' => []];

            while (count($stack) > $level) {
                array_pop($stack);
            }

            if (empty($stack)) {
                $tree[] = $node;
                $stack[] = &$tree[count($tree) - 1];
            } else {
                $parent = &$stack[count($stack) - 1];
                $parent['children'][] = $node;
                $stack[] = &$parent['children'][count($parent['children']) - 1];
            }
        }

        return $tree;
    }

    private function renderListTree(array $tree, string $tag): string
    {
        $html = "<{$tag}>\n";
        foreach ($tree as $node) {
            $content = $this->renderInline($node['content']);
            $html .= "<li>{$content}";
            if (!empty($node['children'])) {
                $html .= "\n" . $this->renderListTree($node['children'], $tag);
            }
            $html .= "</li>\n";
        }
        $html .= "</{$tag}>\n";
        return $html;
    }

    private function renderTable(array $rows): string
    {
        if (empty($rows)) return '';

        $html = "<table>\n";
        $headerDone = false;

        foreach ($rows as $i => $row) {
            $cells = array_map('trim', explode('|', trim($row, '|')));
            if (!$headerDone && $i === 1 && $this->isTableSeparator($row)) {
                $headerDone = true;
                continue;
            }

            $tag = $i === 0 ? 'th' : 'td';
            $html .= "<tr>\n";
            foreach ($cells as $cell) {
                $content = $this->renderInline($cell);
                $html .= "  <{$tag}>{$content}</{$tag}>\n";
            }
            $html .= "</tr>\n";

            if ($i === 0) $headerDone = true;
        }

        $html .= "</table>\n";
        return $html;
    }

    private function isTableSeparator(string $row): bool
    {
        $cells = array_map('trim', explode('|', trim($row, '|')));
        foreach ($cells as $cell) {
            if (!preg_match('/^:?-+:?$/', $cell)) {
                return false;
            }
        }
        return true;
    }

    private function renderInline(string $text): string
    {
        $placeholders = [];
        $counter = 0;

        // 1. Escapes
        $text = preg_replace_callback($this->inlinePatterns['escape'], function ($m) use (&$placeholders, &$counter) {
            $ph = "___ESC_{$counter}___";
            $placeholders[$ph] = $m[1];
            $counter++;
            return $ph;
        }, $text);

        // 2. Code
        $text = preg_replace_callback($this->inlinePatterns['code'], function ($m) use (&$placeholders, &$counter) {
            $ph = "___CODE_{$counter}___";
            $placeholders[$ph] = '<code>' . htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8') . '</code>';
            $counter++;
            return $ph;
        }, $text);

        // 3. Bilder
        $text = preg_replace_callback($this->inlinePatterns['image'], function ($m) use (&$placeholders, &$counter) {
            $alt = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
            $src = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            $title = $m[3] ? ' title="' . htmlspecialchars($m[3], ENT_QUOTES, 'UTF-8') . '"' : '';
            $ph = "___IMG_{$counter}___";
            $placeholders[$ph] = "<img src=\"{$src}\" alt=\"{$alt}\"{$title}>";
            $counter++;
            return $ph;
        }, $text);

        // 4. Links
        $text = preg_replace_callback($this->inlinePatterns['link'], function ($m) use (&$placeholders, &$counter) {
            $text = $m[1];
            $url = htmlspecialchars($m[2], ENT_QUOTES, 'UTF-8');
            $title = $m[3] ? ' title="' . htmlspecialchars($m[3], ENT_QUOTES, 'UTF-8') . '"' : '';
            $ph = "___LINK_{$counter}___";
            $placeholders[$ph] = "<a href=\"{$url}\"{$title}>{$text}</a>";
            $counter++;
            return $ph;
        }, $text);

        // 5. HTML escapen
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // 6. Sonstige Inline-Elemente
        $text = preg_replace($this->inlinePatterns['strong'], '<strong>$1</strong>', $text);
        $text = preg_replace($this->inlinePatterns['em'], '<em>$1</em>', $text);
        $text = preg_replace($this->inlinePatterns['strikethrough'], '<del>$1</del>', $text);
        $text = preg_replace($this->inlinePatterns['autolink'], '<a href="$1">$1</a>', $text);

        // 7. Platzhalter ersetzen
        foreach ($placeholders as $ph => $html) {
            $text = str_replace($ph, $html, $text);
        }

        // 8. Escapes zurück
        $text = preg_replace_callback('/___ESC_(\d+)___/', function ($m) use ($placeholders) {
            return $placeholders["___ESC_{$m[1]}___"];
        }, $text);

        return $text;
    }

    private function generateUniqueId(string $text): string
    {
        $base = $this->generateId($text);
        $id = $base;
        $suffix = 1;
        while (in_array($id, $this->usedIds)) {
            $id = $base . '-' . $suffix++;
        }
        $this->usedIds[] = $id;
        return $id;
    }

    private function generateId(string $text): string
    {
        $text = preg_replace(['/\*\*([^*]+)\*\*/', '\*([^*]+)\*', '`([^`]+)`'], '$1', $text);
        $id = strtolower($text);
        $id = preg_replace('/[^a-z0-9\s-]/', '', $id);
        $id = preg_replace('/\s+/', '-', $id);
        return trim($id, '-') ?: 'heading';
    }

    private function generateCacheKey(string $markdown): string
    {
        return 'content_' . md5($markdown); // schneller als sha256
    }

    private function generateFileCacheKey(string $filePath, int $modTime): string
    {
        return 'file_' . md5($filePath . '_' . $modTime);
    }
}
