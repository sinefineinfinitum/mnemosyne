<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer;

final class MarkdownBuilder
{
    /**
     * @param array<string, string> $pairs
     */
    public function frontmatter(array $pairs): string
    {
        $yaml = "---\n";
        foreach ($pairs as $key => $value) {
            $yaml .= "$key: $value\n";
        }
        $yaml .= "---\n";
        return $yaml;
    }

    public function header(int $level, string $text): string
    {
        return str_repeat('#', $level) . ' ' . $text . "\n";
    }

    /**
     * @param string[]                       $headers
     * @param array<int, array<int, string>> $rows
     */
    public function table(array $headers, array $rows): string
    {
        $sep = '|';
        $headerLine = $sep . implode($sep, $headers) . $sep . "\n";
        $separator = $sep . implode($sep, array_fill(0, count($headers), '---')) . $sep . "\n";
        $body = '';
        foreach ($rows as $row) {
            $escaped = array_map(fn($v) => $this->escapeMd((string) $v), $row);
            $body .= $sep . implode($sep, $escaped) . $sep . "\n";
        }
        return $headerLine . $separator . $body;
    }

    public function codeBlock(string $code, string $lang = ''): string
    {
        return "```$lang\n$code\n```\n";
    }

    public function listItem(string $text, string $prefix = '-'): string
    {
        return "$prefix $text\n";
    }

    /**
     * @param array<string, string> $pairs
     */
    public function kvList(array $pairs): string
    {
        $lines = '';
        foreach ($pairs as $key => $value) {
            $lines .= "- **$key:** $value\n";
        }
        return $lines;
    }

    public function section(string $title, int $level, string $content): string
    {
        if ($content === '') {
            return '';
        }
        return $this->header($level, $title) . "\n" . $content . "\n";
    }

    /**
     * @param array<int, array<string, mixed>> $properties
     */
    public function propertiesTable(array $properties): string
    {
        $headers = ['Property', 'Visibility', 'Type', 'Default'];
        $rows = [];
        foreach ($properties as $p) {
            $name = $this->inlineCode('$' . $p['name']);
            if ($p['isStatic']) {
                $name = 'static ' . $name;
            }
            if ($p['isReadonly']) {
                $name = 'readonly ' . $name;
            }

            $rows[] = [
                $name,
                $p['visibility'],
                $p['type'] ?? '—',
                $p['defaultValue'] !== null ? $this->inlineCode($p['defaultValue']) : '—',
            ];
        }
        return $this->table($headers, $rows);
    }

    /**
     * @param array<string, mixed> $method
     */
    public function methodSignature(array $method): string
    {
        $params = [];
        foreach ($method['parameters'] as $param) {
            $params[] = $this->parameterString($param);
        }

        $visibility = $method['visibility'] ?? '';
        $sig = $visibility !== '' ? $visibility . ' function ' : 'function ';
        $sig .= $method['name'] . '(' . implode(', ', $params) . ')';
        if (isset($method['returnType'])) {
            $sig .= ': ' . $method['returnType'];
        }
        return $sig;
    }

    /**
     * @param array<string, mixed> $param
     */
    public function parameterString(array $param): string
    {
        $p = '';
        if ($param['type'] !== null) {
            $p .= $param['type'] . ' ';
        }
        if ($param['isVariadic']) {
            $p .= '...';
        }
        if ($param['isPassedByReference']) {
            $p .= '&';
        }
        $p .= '$' . $param['name'];
        if ($param['defaultValue'] !== null) {
            $p .= ' = ' . $param['defaultValue'];
        }
        return $p;
    }

    /**
     * @param array<int, array<string, mixed>> $constants
     */
    public function constantsTable(array $constants): string
    {
        $headers = ['Constant', 'Visibility', 'Type', 'Value'];
        $rows = [];
        foreach ($constants as $c) {
            $rows[] = [
                $this->inlineCode($c['name']),
                $c['visibility'],
                $c['type'] ?? '—',
                $this->inlineCode($c['value'] ?? '—'),
            ];
        }
        return $this->table($headers, $rows);
    }

    /**
     * @param array<int, array<string, mixed>> $methods
     */
    public function methodsList(array $methods): string
    {
        $result = '';
        foreach ($methods as $method) {
            $sig = $this->methodSignature($method);
            $result .= $this->listItem($this->inlineCode($sig));
        }
        return $result;
    }

    /**
     * @param string[] $deps
     */
    public function dependenciesList(array $deps): string
    {
        $result = '';
        foreach ($deps as $dep) {
            $result .= $this->listItem($dep);
        }
        return $result;
    }

    /**
     * @param string[] $classes
     */
    public function classList(array $classes): string
    {
        $result = '';
        foreach ($classes as $class) {
            $result .= $this->listItem($this->inlineCode($class));
        }
        return $result;
    }

    /**
     * @param string[] $items
     */
    public function itemList(array $items): string
    {
        $result = '';
        foreach ($items as $item) {
            $result .= $this->listItem($this->inlineCode($item));
        }
        return $result;
    }

    /**
     * @param string[] $links Already-rendered Markdown link lines
     */
    public function usedBySection(array $links): string
    {
        if (empty($links)) {
            return '';
        }
        $list = '';
        foreach ($links as $link) {
            $list .= $this->listItem($link);
        }
        return $this->section('Used By', 3, $list);
    }

    public function vendorIndexEmpty(): string
    {
        return "# Vendor Packages\n\nNo external packages are referenced by this project.\n";
    }

    /**
     * @param array<int, array{package: string, version: string, description: string, classes: string[]}> $packages
     */
    public function vendorIndex(string $title, array $packages): string
    {
        $result = '# ' . $title . "\n\n";
        if (empty($packages)) {
            return $result . "No external packages are referenced by this project.\n";
        }
        $headers = ['Package', 'Version', 'Description', 'Referenced Classes'];
        $rows = [];
        foreach ($packages as $pkg) {
            $rows[] = [
                $pkg['package'],
                $pkg['version'],
                $pkg['description'],
                implode(', ', $pkg['classes']),
            ];
        }
        return $result . $this->table($headers, $rows);
    }

    /**
     * Escape pipe and backslash for Markdown table cells and general text.
     */
    private function escapeMd(string $text): string
    {
        return str_replace(['\\', '|'], ['\\\\', '\\|'], $text);
    }

    /**
     * Wrap a value in inline code backticks, handling embedded backticks
     * via variable-length delimiters per CommonMark spec.
     */
    private function inlineCode(string $value): string
    {
        $maxRun = 0;
        $run = 0;
        $len = strlen($value);
        for ($i = 0; $i < $len; $i++) {
            if ($value[$i] === '`') {
                $run++;
                if ($run > $maxRun) {
                    $maxRun = $run;
                }
            } else {
                $run = 0;
            }
        }
        $delim = str_repeat('`', $maxRun + 1);
        if ($len > 0 && ($value[0] === '`' || $value[$len - 1] === '`')) {
            return $delim . ' ' . $value . ' ' . $delim;
        }
        return $delim . $value . $delim;
    }
}
