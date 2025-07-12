<?php

namespace SquareRouting\Core;
use Exception;

class MarkdownRenderer
{
    private array $blockPatterns;
    private array $inlinePatterns;
    private array $listStack = [];
    private array $usedIds = [];
    private ?Cache $cache = null;

    public function __construct(?Cache $cache = null)
    {
        $this->cache = $cache;
        $this->initializePatterns();
    }

    public function render(string $markdown): string
    {
        // Wenn Cache verfügbar ist, nutze ihn
        if ($this->cache) {
            $cacheKey = $this->generateCacheKey($markdown);

            return $this->cache->get('markdown', $cacheKey, function ($markdown) {
                return $this->doRender($markdown);
            }, 3600, [$markdown]);
        }

        // Ohne Cache direkt rendern
        return $this->doRender($markdown);
    }

    public function renderFile(string $filePath): string
    {
        if (! file_exists($filePath)) {
            throw new Exception("Markdown file not found: {$filePath}");
        }

        $markdown = file_get_contents($filePath);

        // Wenn Cache verfügbar ist, nutze ihn mit Dateiänderungszeit
        if ($this->cache) {
            $fileModTime = filemtime($filePath);
            $cacheKey = $this->generateFileCacheKey($filePath, $fileModTime);

            return $this->cache->get('markdown_file', $cacheKey, function ($markdown) {
                return $this->doRender($markdown);
            }, 3600, [$markdown]);
        }

        // Ohne Cache direkt rendern
        return $this->doRender($markdown);
    }

    public function renderToFile(string $markdown, string $filename): bool
    {
        $html = $this->render($markdown);

        return file_put_contents($filename, $html) !== false;
    }

    public function clearCache(?string $filePath = null): void
    {
        if (! $this->cache) {
            return;
        }

        if ($filePath) {
            // Lösche Cache für spezifische Datei (alle Versionen)
            $baseKey = 'file_' . hash('sha256', $filePath . '_');
            $this->cache->clear('markdown_file');
        } else {
            // Lösche gesamten Markdown-Cache
            $this->cache->clear('markdown');
            $this->cache->clear('markdown_file');
        }
    }

    public function addCustomPattern(string $name, string $pattern, callable $renderer): void
    {
        $this->inlinePatterns[$name] = $pattern;
    }

    public function validate(string $markdown): array
    {
        $errors = [];
        $lines = explode("\n", $markdown);

        foreach ($lines as $lineNum => $line) {
            // Prüfe auf häufige Markdown-Fehler
            if (preg_match('/^#{7,}/', $line)) {
                $errors[] = 'Zeile ' . ($lineNum + 1) . ': Zu viele # für Überschrift (max. 6)';
            }

            if (preg_match('/\[([^\]]+)\]\(([^)]*)\)/', $line, $matches)) {
                if (empty($matches[2])) {
                    $errors[] = 'Zeile ' . ($lineNum + 1) . ': Leere URL in Link';
                }
            }
        }

        return $errors;
    }

    private function initializePatterns(): void
    {
        // Block-level patterns
        $this->blockPatterns = [
            'code_block' => '/^```(\w+)?\s*\n(.*?)\n```$/ms',
            'heading' => '/^(#{1,6})\s+(.+)$/m',
            'hr' => '/^(-{3,}|\*{3,}|_{3,})$/m',
            'blockquote' => '/^>\s*(.*)$/m',
            'unordered_list' => '/^(\s*)[\*\-\+]\s+(.+)$/m',
            'ordered_list' => '/^(\s*)\d+\.\s+(.+)$/m',
            'table' => '/^\|(.+)\|$/m',
            'paragraph' => '/^(?!#|>|\*|\-|\+|\d+\.|```|---|___|\*\*\*|\|)(.+)$/m',
        ];

        // Inline patterns - korrigierte Reihenfolge und Regex
        $this->inlinePatterns = [
            'escape' => '/\\\\([\\`*_{}\[\]()#+\-.!])/',
            'code' => '/`([^`\n]+)`/',
            'strong' => '/\*\*([^*\n]+?)\*\*/',
            'em' => '/(?<!\*)\*([^*\n]+?)\*(?!\*)/',
            'strikethrough' => '/~~([^~\n]+?)~~/',
            'image' => '/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]+)")?\)/',
            'link' => '/\[([^\]]+)\]\(([^)\s]+)(?:\s+"([^"]+)")?\)/',
            'autolink' => '/(?<![\[\(])(https?:\/\/[^\s<>\[\]()]+)(?![^\s]*[\]\)])/', // Verbesserte Autolink-Regex
        ];
    }

    private function doRender(string $markdown): string
    {
        // Reset state
        $this->usedIds = [];
        $this->listStack = [];

        // Normalisiere Zeilenenden
        $markdown = str_replace(["\r\n", "\r"], "\n", $markdown);

        // Entferne Front Matter falls vorhanden
        $markdown = $this->removeFrontMatter($markdown);

        // Teile in Blöcke auf
        $blocks = $this->parseBlocks($markdown);

        // Rendere jeden Block
        $html = '';
        foreach ($blocks as $block) {
            $html .= $this->renderBlock($block);
        }

        return trim($html);
    }

    private function removeFrontMatter(string $markdown): string
    {
        if (preg_match('/^---\s*\n.*?\n---\s*\n/s', $markdown)) {
            return preg_replace('/^---\s*\n.*?\n---\s*\n/s', '', $markdown);
        }

        return $markdown;
    }

    private function parseBlocks(string $markdown): array
    {
        $lines = explode("\n", $markdown);
        $blocks = [];
        $currentBlock = null;
        $i = 0;

        while ($i < count($lines)) {
            $line = $lines[$i];

            // Code-Blöcke haben absolute Priorität
            if (preg_match('/^```(\w+)?/', $line, $matches)) {
                if ($currentBlock) {
                    $blocks[] = $currentBlock;
                    $currentBlock = null;
                }

                $language = $matches[1] ?? '';
                $code = '';
                $i++;

                while ($i < count($lines) && ! preg_match('/^```$/', $lines[$i])) {
                    $code .= $lines[$i] . "\n";
                    $i++;
                }

                $blocks[] = [
                    'type' => 'code_block',
                    'content' => rtrim($code),
                    'language' => $language,
                ];
                $i++;

                continue;
            }

            // Blockquote-Handling - sammle mehrere Zeilen
            if (preg_match('/^>\s*/', $line)) {
                if ($currentBlock && $currentBlock['type'] === 'blockquote') {
                    $currentBlock['content'] .= "\n" . $line;
                } else {
                    if ($currentBlock) {
                        $blocks[] = $currentBlock;
                    }
                    $currentBlock = [
                        'type' => 'blockquote',
                        'content' => $line,
                    ];
                }
                $i++;

                continue;
            }

            // Tabellen-Erkennung
            if (preg_match('/^\|(.+)\|$/', $line)) {
                if ($currentBlock) {
                    $blocks[] = $currentBlock;
                    $currentBlock = null;
                }

                $tableRows = [$line];
                $i++;

                // Sammle alle Tabellenzeilen
                while ($i < count($lines) && preg_match('/^\|(.+)\|$/', $lines[$i])) {
                    $tableRows[] = $lines[$i];
                    $i++;
                }

                $blocks[] = [
                    'type' => 'table',
                    'rows' => $tableRows,
                ];

                continue;
            }

            // Leere Zeilen beenden Blöcke
            if (trim($line) === '') {
                if ($currentBlock) {
                    $blocks[] = $currentBlock;
                    $currentBlock = null;
                }
                $i++;

                continue;
            }

            // Bestimme Block-Typ
            $blockType = $this->getBlockType($line);

            // Listen-Handling
            if (in_array($blockType, ['unordered_list', 'ordered_list'])) {
                $indent = $this->getIndentLevel($line);
                $content = $this->getListItemContent($line);

                if ($currentBlock && $currentBlock['type'] === $blockType) {
                    $currentBlock['items'][] = [
                        'content' => $content,
                        'indent' => $indent,
                    ];
                } else {
                    if ($currentBlock) {
                        $blocks[] = $currentBlock;
                    }
                    $currentBlock = [
                        'type' => $blockType,
                        'items' => [[
                            'content' => $content,
                            'indent' => $indent,
                        ]],
                    ];
                }
            } else {
                if ($currentBlock) {
                    $blocks[] = $currentBlock;
                    $currentBlock = null;
                }

                $blocks[] = [
                    'type' => $blockType,
                    'content' => $line,
                ];
            }

            $i++;
        }

        if ($currentBlock) {
            $blocks[] = $currentBlock;
        }

        return $blocks;
    }

    private function getBlockType(string $line): string
    {
        if (preg_match('/^#{1,6}\s+/', $line)) {
            return 'heading';
        }
        if (preg_match('/^(-{3,}|\*{3,}|_{3,})$/', $line)) {
            return 'hr';
        }
        if (preg_match('/^>\s*/', $line)) {
            return 'blockquote';
        }
        if (preg_match('/^(\s*)[\*\-\+]\s+/', $line)) {
            return 'unordered_list';
        }
        if (preg_match('/^(\s*)\d+\.\s+/', $line)) {
            return 'ordered_list';
        }
        if (preg_match('/^\|(.+)\|$/', $line)) {
            return 'table';
        }

        return 'paragraph';
    }

    private function getIndentLevel(string $line): int
    {
        preg_match('/^(\s*)/', $line, $matches);

        return strlen($matches[1] ?? '');
    }

    private function getListItemContent(string $line): string
    {
        if (preg_match('/^(\s*)[\*\-\+]\s+(.+)$/', $line, $matches)) {
            return $matches[2];
        }
        if (preg_match('/^(\s*)\d+\.\s+(.+)$/', $line, $matches)) {
            return $matches[2];
        }

        return trim($line);
    }

    private function renderBlock(array $block): string
    {
        switch ($block['type']) {
            case 'heading':
                return $this->renderHeading($block['content']);

            case 'paragraph':
                return $this->renderParagraph($block['content']);

            case 'code_block':
                return $this->renderCodeBlock($block['content'], $block['language'] ?? '');

            case 'hr':
                return "<hr>\n";

            case 'blockquote':
                return $this->renderBlockquote($block['content']);

            case 'unordered_list':
                return $this->renderList($block['items'], 'ul');

            case 'ordered_list':
                return $this->renderList($block['items'], 'ol');

            case 'table':
                return $this->renderTable($block['rows']);

            default:
                return $this->renderParagraph($block['content']);
        }
    }

    private function renderHeading(string $content): string
    {
        if (preg_match('/^(#{1,6})\s+(.+)$/', $content, $matches)) {
            $level = strlen($matches[1]);
            $text = $this->renderInline($matches[2]);
            $id = $this->generateUniqueId($matches[2]);

            return "<h{$level} id=\"{$id}\">{$text}</h{$level}>\n";
        }

        return '';
    }

    private function renderParagraph(string $content): string
    {
        $content = $this->renderInline($content);

        return "<p>{$content}</p>\n";
    }

    private function renderCodeBlock(string $content, string $language = ''): string
    {
        $content = htmlspecialchars($content, ENT_QUOTES, 'UTF-8');
        $langClass = $language ? " class=\"language-{$language}\"" : '';

        return "<pre><code{$langClass}>{$content}</code></pre>\n";
    }

    private function renderBlockquote(string $content): string
    {
        // Verarbeite mehrere Zeilen
        $lines = explode("\n", $content);
        $processedLines = [];

        foreach ($lines as $line) {
            $line = preg_replace('/^>\s*/', '', $line);
            if (trim($line) !== '') {
                $processedLines[] = $this->renderInline($line);
            }
        }

        if (empty($processedLines)) {
            return "<blockquote><p></p></blockquote>\n";
        }

        $content = implode(' ', $processedLines);

        return "<blockquote><p>{$content}</p></blockquote>\n";
    }

    private function renderList(array $items, string $tag): string
    {
        if (empty($items)) {
            return '';
        }

        // Erstelle hierarchische Struktur
        $tree = $this->buildListTree($items);

        return $this->renderListTree($tree, $tag);
    }

    private function buildListTree(array $items): array
    {
        $tree = [];
        $stack = [];

        foreach ($items as $item) {
            $level = intval($item['indent'] / 2); // 2 Leerzeichen pro Ebene
            $node = [
                'content' => $item['content'],
                'level' => $level,
                'children' => [],
            ];

            // Bereinige den Stack bis zur aktuellen Ebene
            while (count($stack) > $level) {
                array_pop($stack);
            }

            if (empty($stack)) {
                // Top-level Element
                $tree[] = $node;
                $stack[] = &$tree[count($tree) - 1];
            } else {
                // Kind-Element
                $parent = &$stack[count($stack) - 1];
                $parent['children'][] = $node;
                $stack[] = &$parent['children'][count($parent['children']) - 1];
            }
        }

        return $tree;
    }

    private function renderListTree(array $tree, string $tag): string
    {
        if (empty($tree)) {
            return '';
        }

        $html = "<{$tag}>\n";

        foreach ($tree as $node) {
            $content = $this->renderInline($node['content']);
            $html .= "<li>{$content}";

            if (! empty($node['children'])) {
                $html .= "\n" . $this->renderListTree($node['children'], $tag);
            }

            $html .= "</li>\n";
        }

        $html .= "</{$tag}>\n";

        return $html;
    }

    private function renderTable(array $rows): string
    {
        if (empty($rows)) {
            return '';
        }

        $html = "<table>\n";
        $rowIndex = 0;

        foreach ($rows as $row) {
            $cells = array_map('trim', explode('|', trim($row, '|')));

            // Prüfe ob es eine Separator-Zeile ist (zweite Zeile)
            if ($rowIndex === 1 && $this->isTableSeparator($row)) {
                $rowIndex++;

                continue;
            }

            $tag = ($rowIndex === 0) ? 'th' : 'td';
            $html .= "<tr>\n";

            foreach ($cells as $cell) {
                $content = $this->renderInline($cell);
                $html .= "<{$tag}>{$content}</{$tag}>\n";
            }

            $html .= "</tr>\n";
            $rowIndex++;
        }

        $html .= "</table>\n";

        return $html;
    }

    private function isTableSeparator(string $row): bool
    {
        $row = trim($row, '|');
        $cells = explode('|', $row);

        foreach ($cells as $cell) {
            $cell = trim($cell);
            if (! preg_match('/^:?-+:?$/', $cell)) {
                return false;
            }
        }

        return true;
    }

    private function renderInline(string $text): string
    {
        $placeholders = [];
        $placeholderCounter = 0;

        // 1. Handle escape sequences FIRST
        $text = preg_replace_callback('/\\\\([\\`*_{}\[\]()#+\-.!])/', function ($matches) {
            return 'ESCAPED_' . ord($matches[1]) . '_CHAR';
        }, $text);

        // 2. Handle code spans BEFORE anything else
        $text = preg_replace_callback('/`([^`\n]+)`/', function ($matches) use (&$placeholders, &$placeholderCounter) {
            $placeholder = "___CODE_PLACEHOLDER_{$placeholderCounter}___";
            $placeholders[$placeholder] = '<code>' . htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8') . '</code>';
            $placeholderCounter++;

            return $placeholder;
        }, $text);

        // 3. Handle images BEFORE links to avoid conflicts
        $text = preg_replace_callback('/!\[([^\]]*)\]\(([^)\s]+)(?:\s+"([^"]+)")?\)/', function ($matches) use (&$placeholders, &$placeholderCounter) {
            $alt = htmlspecialchars($matches[1], ENT_QUOTES, 'UTF-8');
            $src = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            $title = isset($matches[3]) ? ' title="' . htmlspecialchars($matches[3], ENT_QUOTES, 'UTF-8') . '"' : '';
            $placeholder = "___IMG_PLACEHOLDER_{$placeholderCounter}___";
            $placeholders[$placeholder] = "<img src=\"{$src}\" alt=\"{$alt}\"{$title}>";
            $placeholderCounter++;

            return $placeholder;
        }, $text);

        // 4. Handle explicit links BEFORE autolinks
        $text = preg_replace_callback('/\[([^\]]+)\]\(([^)\s]+)(?:\s+"([^"]+)")?\)/', function ($matches) use (&$placeholders, &$placeholderCounter) {
            $linkText = $matches[1];
            $url = htmlspecialchars($matches[2], ENT_QUOTES, 'UTF-8');
            $title = isset($matches[3]) ? ' title="' . htmlspecialchars($matches[3], ENT_QUOTES, 'UTF-8') . '"' : '';
            $placeholder = "___LINK_PLACEHOLDER_{$placeholderCounter}___";
            $placeholders[$placeholder] = "<a href=\"{$url}\"{$title}>{$linkText}</a>";
            $placeholderCounter++;

            return $placeholder;
        }, $text);

        // 5. Escape HTML
        $text = htmlspecialchars($text, ENT_QUOTES, 'UTF-8');

        // 6. Apply remaining inline patterns
        $patterns = [
            'strong' => '/\*\*([^*\n]+?)\*\*/',
            'em' => '/(?<!\*)\*([^*\n]+?)\*(?!\*)/',
            'strikethrough' => '/~~([^~\n]+?)~~/',
            'autolink' => '/(?<![\[\(])(https?:\/\/[^\s&lt;&gt;\[\]()]{2,})(?![^\s]*[\]\)])/',
        ];

        foreach ($patterns as $type => $pattern) {
            $text = preg_replace_callback($pattern, function ($matches) use ($type) {
                return $this->renderInlineElement($type, $matches);
            }, $text);
        }

        // 7. Replace placeholders with actual HTML
        foreach ($placeholders as $placeholder => $html) {
            $text = str_replace($placeholder, $html, $text);
        }

        // 8. Restore escaped characters
        $text = preg_replace_callback('/ESCAPED_(\d+)_CHAR/', function ($matches) {
            return chr((int) $matches[1]);
        }, $text);

        return $text;
    }

    private function renderInlineElement(string $type, array $matches): string
    {
        switch ($type) {
            case 'strong':
                return '<strong>' . $matches[1] . '</strong>';

            case 'em':
                return '<em>' . $matches[1] . '</em>';

            case 'strikethrough':
                return '<del>' . $matches[1] . '</del>';

            case 'autolink':
                $url = htmlspecialchars_decode($matches[1]);

                return "<a href=\"{$url}\">{$url}</a>";

            default:
                return $matches[0];
        }
    }

    private function generateUniqueId(string $text): string
    {
        $baseId = $this->generateId($text);
        $id = $baseId;
        $counter = 1;

        while (in_array($id, $this->usedIds)) {
            $id = $baseId . '-' . $counter;
            $counter++;
        }

        $this->usedIds[] = $id;

        return $id;
    }

    private function generateId(string $text): string
    {
        // Entferne Markdown-Syntax für ID-Generierung
        $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text);
        $text = preg_replace('/\*([^*]+)\*/', '$1', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);

        $id = strtolower($text);
        $id = preg_replace('/[^a-z0-9\s-]/', '', $id);
        $id = preg_replace('/\s+/', '-', $id);
        $id = trim($id, '-');

        return $id ?: 'heading';
    }

    private function generateCacheKey(string $markdown): string
    {
        // Verwende Hash des Inhalts für konsistente Cache-Keys
        return 'content_' . hash('sha256', $markdown);
    }

    private function generateFileCacheKey(string $filePath, int $modTime): string
    {
        // Verwende Dateipfad + Änderungszeit für eindeutige Cache-Keys
        return 'file_' . hash('sha256', $filePath . '_' . $modTime);
    }
}

// Utility-Klasse bleibt unverändert
class MarkdownUtils
{
    public static function tableOfContents(string $markdown): array
    {
        $toc = [];
        $lines = explode("\n", $markdown);
        $usedIds = [];

        foreach ($lines as $line) {
            if (preg_match('/^(#{1,6})\s+(.+)$/', $line, $matches)) {
                $level = strlen($matches[1]);
                $text = trim($matches[2]);
                $id = self::generateUniqueId($text, $usedIds);

                $toc[] = [
                    'level' => $level,
                    'text' => $text,
                    'id' => $id,
                ];
            }
        }

        return $toc;
    }

    public static function extractMetadata(string $markdown): array
    {
        $metadata = [];

        if (preg_match('/^---\s*\n(.*?)\n---\s*\n/s', $markdown, $matches)) {
            $lines = explode("\n", $matches[1]);
            foreach ($lines as $line) {
                if (preg_match('/^(\w+):\s*(.+)$/', trim($line), $lineMatches)) {
                    $metadata[$lineMatches[1]] = trim($lineMatches[2], '"\'');
                }
            }
        }

        return $metadata;
    }

    public static function getWordCount(string $markdown): int
    {
        $text = preg_replace('/^#{1,6}\s+/', '', $markdown);
        $text = preg_replace('/\*\*([^*]+)\*\*/', '$1', $text);
        $text = preg_replace('/\*([^*]+)\*/', '$1', $text);
        $text = preg_replace('/`([^`]+)`/', '$1', $text);
        $text = preg_replace('/\[([^\]]+)\]\([^)]+\)/', '$1', $text);
        $text = preg_replace('/```.*?```/s', '', $text);
        $text = strip_tags($text);

        return str_word_count($text);
    }

    public static function getReadingTime(string $markdown, int $wordsPerMinute = 200): int
    {
        $wordCount = self::getWordCount($markdown);

        return max(1, round($wordCount / $wordsPerMinute));
    }

    private static function generateUniqueId(string $text, array &$usedIds): string
    {
        $baseId = self::generateId($text);
        $id = $baseId;
        $counter = 1;

        while (in_array($id, $usedIds)) {
            $id = $baseId . '-' . $counter;
            $counter++;
        }

        $usedIds[] = $id;

        return $id;
    }

    private static function generateId(string $text): string
    {
        $id = strtolower($text);
        $id = preg_replace('/[^a-z0-9\s-]/', '', $id);
        $id = preg_replace('/\s+/', '-', $id);

        return trim($id, '-') ?: 'heading';
    }
}
