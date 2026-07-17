<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Detect;

final class ConsoleRenderer
{
    private const COL_PATTERN = 0;
    private const COL_CLASS = 2;

    private const HEADERS = ['Pattern', 'Role', 'Class'];
    private const COL_WIDTHS = [9, 15, 46];

    private const SEPARATOR_NAMESPACE = '\\';
    private const SEPARATOR_UNDERSCORE = '_';

    /**
     * @param list<list<list<string>>> $blocks
     */
    public function render(array $blocks): void
    {
        if ($blocks === []) {
            echo "No pattern matches found.\n";
            return;
        }

        $this->renderTable($blocks);
    }

    /**
     * @param list<list<list<string>>> $blocks
     */
    private function renderTable(array $blocks): void
    {
        $border = $this->buildBorder();

        echo $border . "\n";
        $this->printRow(self::HEADERS);
        echo $border . "\n";

        $this->printDataRows($blocks, $border);

        echo $border . "\n";
    }

    private function buildBorder(): string
    {
        $segments = array_map(
            fn(int $width) => str_repeat('-', $width + 2),
            self::COL_WIDTHS
        );

        return '+' . implode('+', $segments) . '+';
    }

    /**
     * @param list<list<list<string>>> $blocks
     */
    private function printDataRows(array $blocks, string $border): void
    {
        $lastBlockIndex = array_key_last($blocks);

        foreach ($blocks as $blockIndex => $rows) {
            foreach ($rows as $row) {
                $this->printWrappedRow($row);
            }

            if ($blockIndex !== $lastBlockIndex) {
                echo $border . "\n";
            }
        }
    }

    /**
     * @param list<string> $cells
     */
    private function printWrappedRow(array $cells): void
    {
        $wrappedCells = $this->wrapAllCells($cells);
        $maxLines = $this->getMaxLines($wrappedCells);

        for ($lineIndex = 0; $lineIndex < $maxLines; $lineIndex++) {
            $lineParts = $this->extractLineParts($wrappedCells, $lineIndex);
            $this->printRow($lineParts);
        }
    }

    /**
     * @param  list<string> $cells
     * @return list<list<string>>
     */
    private function wrapAllCells(array $cells): array
    {
        $wrapped = [];

        foreach ($cells as $columnIndex => $cell) {
            $wrapped[] = $this->wrapCell($cell, $columnIndex);
        }

        return $wrapped;
    }

    /**
     * @param list<list<string>> $wrappedCells
     */
    private function getMaxLines(array $wrappedCells): int
    {
        $maxLines = 1;

        foreach ($wrappedCells as $lines) {
            $lineCount = count($lines);
            if ($lineCount > $maxLines) {
                $maxLines = $lineCount;
            }
        }

        return $maxLines;
    }

    /**
     * @param  list<list<string>> $wrappedCells
     * @return list<string>
     */
    private function extractLineParts(array $wrappedCells, int $lineIndex): array
    {
        $parts = [];

        foreach ($wrappedCells as $lines) {
            $parts[] = $lines[$lineIndex] ?? '';
        }

        return $parts;
    }

    /**
     * @return list<string>
     */
    private function wrapCell(string $text, int $columnIndex): array
    {
        if ($text === '') {
            return [''];
        }

        $width = self::COL_WIDTHS[$columnIndex];
        $separator = $this->getSeparatorForColumn($columnIndex);
        $appendSemicolon = $columnIndex === self::COL_CLASS;

        if ($separator === '' || mb_strlen($text) <= $width) {
            return [$appendSemicolon ? $text . ';' : $text];
        }

        return $this->wrapLongText($text, $width, $separator, $appendSemicolon);
    }

    private function getSeparatorForColumn(int $columnIndex): string
    {
        return match ($columnIndex) {
            self::COL_PATTERN => self::SEPARATOR_UNDERSCORE,
            self::COL_CLASS => self::SEPARATOR_NAMESPACE,
            default => '',
        };
    }

    /**
     * @param  non-empty-string $text
     * @param  int              $width
     * @param  string           $separator
     * @param  bool             $appendSemicolon
     * @return list<string>
     */
    private function wrapLongText(string $text, int $width, string $separator, bool $appendSemicolon): array
    {
        if ($separator === '') {
            return [$appendSemicolon ? $text . ';' : $text];
        }

        $segments = explode($separator, $text);

        if (count($segments) <= 1) {
            return [$appendSemicolon ? $text . ';' : $text];
        }

        $lines = [];
        $currentLine = '';

        foreach ($segments as $segment) {
            $candidate = $this->buildCandidate($currentLine, $segment, $separator);

            if (mb_strlen($candidate) <= $width) {
                $currentLine = $candidate;
            } else {
                if ($currentLine !== '') {
                    $lines[] = $currentLine;
                }
                $currentLine = $this->startNewLine($segment, $separator);
            }
        }

        if ($currentLine !== '') {
            $lines[] = $appendSemicolon ? $currentLine . ';' : $currentLine;
        }

        return $lines;
    }

    private function buildCandidate(string $currentLine, string $segment, string $separator): string
    {
        return $currentLine === '' ? $segment : $currentLine . $separator . $segment;
    }

    private function startNewLine(string $segment, string $separator): string
    {
        $prefix = $separator === self::SEPARATOR_UNDERSCORE ? '' : $separator;
        return $prefix . $segment;
    }

    /**
     * @param list<string> $cells
     */
    private function printRow(array $cells): void
    {
        echo '|';

        foreach ($cells as $columnIndex => $cell) {
            $width = self::COL_WIDTHS[$columnIndex];
            echo ' ' . str_pad($cell, $width) . ' |';
        }

        echo "\n";
    }
}
