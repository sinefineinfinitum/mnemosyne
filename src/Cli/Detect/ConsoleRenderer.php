<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Detect;

final class ConsoleRenderer
{
    private const HEADERS = ['Pattern', 'Role', 'Class'];
    private const COL_WIDTHS = [9, 15, 46]; // Separators width 2+3+3+2, sum of all widths must be = 80

    /**
     * @param list<list<list<string>>> $blocks
     */
    public function render(array $blocks): void
    {
        if ($blocks === []) {
            echo "No pattern matches found.\n";
            return;
        }

        $widths = self::COL_WIDTHS;

        $border = '+' . implode('+', array_map(fn(int $w) => str_repeat('-', $w + 2), $widths)) . '+';

        echo $border . "\n";
        $this->outputRow(self::HEADERS, $widths);
        echo $border . "\n";

        $last = array_key_last($blocks);
        foreach ($blocks as $idx => $rows) {
            foreach ($rows as $row) {
                $this->outputMultiRow($row, $widths);
            }
            if ($idx !== $last) {
                echo $border . "\n";
            }
        }

        echo $border . "\n";
    }

    /**
     * @param string[] $cells
     * @param int[]    $widths
     */
    private function outputMultiRow(array $cells, array $widths): void
    {
        $wrapped = [];
        $maxLines = 1;
        foreach ($cells as $i => $cell) {
            $separator = match ($i) {
                0 => '_',
                2 => '\\',
                default => '',
            };
            $appendSemicolon = $i === 2;
            $lines = $this->wrapCell($cell, $widths[$i], $separator, $appendSemicolon);
            $wrapped[] = $lines;
            $cnt = count($lines);
            if ($cnt > $maxLines) {
                $maxLines = $cnt;
            }
        }

        for ($line = 0; $line < $maxLines; $line++) {
            $parts = [];
            foreach ($wrapped as $i => $lines) {
                $parts[] = $lines[$line] ?? '';
            }
            $this->outputRow($parts, $widths);
        }
    }

    /**
     * @return list<string>
     */
    private function wrapCell(string $text, int $width, string $separator = '\\', bool $appendSemicolon = false): array
    {
        if ($text === '') {
            return [''];
        }

        if ($separator === '' || mb_strlen($text) <= $width) {
            return [$appendSemicolon ? $text . ';' : $text];
        }

        $segments = explode($separator, $text);

        if (count($segments) <= 1) {
            return [$appendSemicolon ? $text . ';' : $text];
        }

        $lines = [];
        $current = '';

        foreach ($segments as $seg) {
            $candidate = $current === '' ? $seg : $current . $separator . $seg;
            if (mb_strlen($candidate) <= $width) {
                $current = $candidate;
            } else {
                if ($current !== '') {
                    $lines[] = $current;
                }
                $current = ($separator === '_' ? '' : $separator) . $seg;
            }
        }

        if ($current !== '') {
            $lines[] = $appendSemicolon ? $current . ';' : $current;
        }

        return $lines;
    }

    /**
     * @param string[] $cells
     * @param int[]    $widths
     */
    private function outputRow(array $cells, array $widths): void
    {
        echo '|';
        foreach ($cells as $i => $cell) {
            echo ' ' . str_pad($cell, $widths[$i]) . ' |';
        }
        echo "\n";
    }
}
