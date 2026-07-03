<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Msv1Parser\Internal;

use SineFine\Mnemosyne\Msv1Parser\SyntaxException;

final class Lexer
{
    public const INDENT_WIDTH = 4;

    /**
     * Splits raw MSV1 content into a stream of validated Line objects.
     * Empty and whitespace-only lines are skipped. Tabs and any indentation
     * other than 0 or INDENT_WIDTH lead to a SyntaxException.
     *
     * @return Line[]
     * @throws SyntaxException If a line contains invalid indentation.
     */
    public function tokenize(string $content): array
    {
        $lines = [];
        foreach (explode("\n", $content) as $lineNum => $rawLine) {
            $raw = rtrim($rawLine, "\r");
            $trimmed = trim($raw);
            if ($trimmed === '') {
                continue;
            }

            $line = new Line($lineNum, $raw, $trimmed, 0);
            $line->indentation = $this->validateIndentation($line);
            $lines[] = $line;
        }
        return $lines;
    }

    /**
     * @throws SyntaxException If the line contains tabs or non-{0, INDENT_WIDTH} indentation.
     */
    private function validateIndentation(Line $line): int
    {
        $raw = $line->raw;
        if (str_contains($raw, "\t")) {
            throw SyntaxException::atLine('Invalid indentation or tabs', $line->number + 1, $raw);
        }

        $leading = strspn($raw, ' ');
        if ($leading !== 0 && $leading !== self::INDENT_WIDTH) {
            throw SyntaxException::atLine('Invalid indentation or tabs', $line->number + 1, $raw);
        }

        return $leading;
    }
}
