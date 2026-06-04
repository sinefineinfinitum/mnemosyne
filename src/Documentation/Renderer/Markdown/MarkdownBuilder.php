<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer\Markdown;

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
     * @param string          $typeLabel
     * @param string|null     $parentFqn
     * @param string|null     $parentLink
     * @param string[]        $interfaceFqns
     * @param (string|null)[] $interfaceLinks
     * @param string|null     $backingType
     * @param string          $extendsWord
     * @param string          $implementsWord
     */
    public function declarationLine(string $typeLabel, ?string $parentFqn = null, ?string $parentLink = null, array $interfaceFqns = [], array $interfaceLinks = [], ?string $backingType = null, string $extendsWord = 'extends', string $implementsWord = 'implements'): string
    {
        $line = $this->inlineCode($typeLabel);

        if ($backingType !== null) {
            $line .= ' of ' . $this->inlineCode($backingType);
        }

        if ($parentFqn !== null) {
            $line .= ' ' . $extendsWord . ' ';
            if ($parentLink !== null) {
                $line .= '[' . $parentFqn . '](' . $parentLink . ')';
            } else {
                $line .= $this->inlineCode($parentFqn);
            }
        }

        if (!empty($interfaceFqns)) {
            $line .= ' ' . $implementsWord . ' ';
            $interfaceParts = [];
            foreach ($interfaceFqns as $i => $interface) {
                $link = $interfaceLinks[$i] ?? null;
                if ($link !== null) {
                    $interfaceParts[] = '[' . $interface . '](' . $link . ')';
                } else {
                    $interfaceParts[] = $this->inlineCode($interface);
                }
            }
            $line .= implode(', ', $interfaceParts);
        }

        return $line . "\n";
    }

    public function renderType(string $type, callable $linkResolver): string
    {
        if ($type === '') {
            return '';
        }

        // Handle nullable prefix: ?Type
        if (str_starts_with($type, '?')) {
            $inner = substr($type, 1);
            $rendered = $this->renderType($inner, $linkResolver);
            // If inner is a link (starts with '['), prepend ? outside link
            if ($rendered !== '' && $rendered[0] === '[') {
                return '?' . $rendered;
            }
            // Otherwise put ? inside the code span
            $normalized = ltrim($inner, '\\');
            return $this->inlineCode('?' . $normalized);
        }

        // Handle union types: TypeA|TypeB
        $unionParts = explode('|', $type);
        if (count($unionParts) > 1) {
            return implode('|', array_map(fn(string $part) => $this->renderType($part, $linkResolver), $unionParts));
        }

        // Handle intersection types: TypeA&TypeB
        $intersectionParts = explode('&', $type);
        if (count($intersectionParts) > 1) {
            return implode('&', array_map(fn(string $part) => $this->renderType($part, $linkResolver), $intersectionParts));
        }

        $normalized = ltrim($type, '\\');
        $link = $linkResolver($normalized);
        if ($link !== null) {
            return '[' . $normalized . '](' . $link . ')';
        }

        return $this->inlineCode($normalized);
    }

    /**
     * Render properties as a compact bullet list.
     *
     * @param array<int, array<string, mixed>> $properties
     * @param callable(string): ?string        $typeLinkResolver
     */
    public function propertiesList(array $properties, callable $typeLinkResolver): string
    {
        $result = '';
        foreach ($properties as $p) {
            $parts = [];

            $parts[] = $this->inlineCode($p['visibility']);

            if (!empty($p['isStatic'])) {
                $parts[] = 'static';
            }
            if (!empty($p['isReadonly'])) {
                $parts[] = 'readonly';
            }

            if ($p['type'] !== null) {
                $parts[] = $this->renderType($p['type'], $typeLinkResolver);
            }

            $parts[] = $this->inlineCode('$' . $p['name']);

            if ($p['defaultValue'] !== null) {
                $parts[count($parts) - 1] = $this->inlineCode('$' . $p['name'] . ' = ' . $p['defaultValue']);
            }

            $result .= $this->listItem(implode(' ', $parts));
        }
        return $result;
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
     * @param callable(string): ?string        $typeLinkResolver
     */
    public function methodsList(array $methods, callable $typeLinkResolver): string
    {
        $result = '';
        foreach ($methods as $method) {
            $result .= $this->listItem($this->renderMethodSignature($method, $typeLinkResolver));
        }
        return $result;
    }

    /**
     * Render a method signature with type references as separate link/code spans.
     *
     * @param array<string, mixed>      $method
     * @param callable(string): ?string $linkResolver
     */
    private function renderMethodSignature(array $method, callable $linkResolver): string
    {
        $visibility = $method['visibility'] ?? '';
        $sig = $visibility !== '' ? $visibility . ' function ' : 'function ';
        $sig .= $method['name'] . '(';

        $rendered = $this->inlineCode($sig);
        $firstParam = true;

        foreach ($method['parameters'] as $param) {
            if ($firstParam) {
                $firstParam = false;
            } else {
                $rendered .= $this->inlineCode(', ');
            }

            if ($param['type'] !== null) {
                $rendered .= $this->renderType($param['type'], $linkResolver);
            }

            $paramSig = '';
            if (!empty($param['isVariadic'])) {
                $paramSig .= '...';
            }
            if (!empty($param['isPassedByReference'])) {
                $paramSig .= '&';
            }
            $paramSig .= '$' . $param['name'];
            if ($param['defaultValue'] !== null) {
                $paramSig .= ' = ' . $param['defaultValue'];
            }

            if ($param['type'] !== null) {
                $rendered .= $this->inlineCode(' ' . $paramSig);
            } else {
                $rendered .= $this->inlineCode($paramSig);
            }
        }

        if (isset($method['returnType'])) {
            $rendered .= $this->inlineCode('): ');
            $rendered .= $this->renderType($method['returnType'], $linkResolver);
        } else {
            $rendered .= $this->inlineCode(')');
        }

        return $rendered;
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
     * Render the Creates section content.
     *
     * @param array<string, list<string>> $creates
     * @param callable(string): ?string   $linkResolver
     */
    public function createsSection(array $creates, callable $linkResolver): string
    {
        if (empty($creates)) {
            return '';
        }

        $lines = '';
        foreach ($creates as $method => $fqcns) {
            foreach ($fqcns as $fqcn) {
                $link = $linkResolver($fqcn);
                if ($link !== null) {
                    $lines .= $this->listItem($this->inlineCode($method) . ': [' . $fqcn . '](' . $link . ')');
                } else {
                    $lines .= $this->listItem($this->inlineCode($method) . ': ' . $this->inlineCode($fqcn));
                }
            }
        }

        return $lines;
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
    public function inlineCode(string $value): string
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
