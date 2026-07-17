<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Msv1Parser\Internal;

/**
 * @internal
 */
final class Line
{
    public function __construct(
        public int $number,
        public string $raw,
        public string $trimmed,
        public int $indentation,
    ) {
    }

    public function startsWith(string $prefix): bool
    {
        return str_starts_with($this->trimmed, $prefix);
    }
}
