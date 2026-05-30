<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer;

abstract class BaseRenderer
{
    /**
     * @param array<string, string> $pairs
     */
    public function buildFrontmatter(array $pairs): string
    {
        $yaml = "---\n";
        foreach ($pairs as $key => $value) {
            $yaml .= "$key: $value\n";
        }
        $yaml .= "---\n";
        return $yaml;
    }

    public function buildHeader(int $level, string $text): string
    {
        return str_repeat('#', $level) . ' ' . $text . "\n";
    }

    /**
     * @param string[]                       $headers
     * @param array<int, array<int, string>> $rows
     */
    public function buildTable(array $headers, array $rows): string
    {
        $sep = '|';
        $headerLine = $sep . implode($sep, $headers) . $sep . "\n";
        $separator = $sep . implode($sep, array_fill(0, count($headers), '---')) . $sep . "\n";
        $body = '';
        foreach ($rows as $row) {
            $escaped = array_map(fn($v) => str_replace('|', '\\|', (string) $v), $row);
            $body .= $sep . implode($sep, $escaped) . $sep . "\n";
        }
        return $headerLine . $separator . $body;
    }

    public function buildCodeBlock(string $code, string $lang = ''): string
    {
        return "```$lang\n$code\n```\n";
    }

    public function buildListItem(string $text, string $prefix = '-'): string
    {
        return "$prefix $text\n";
    }
}
