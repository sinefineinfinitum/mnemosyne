<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Comparator;

final class HashGenerator
{
    public const HASH_LENGTH = 12;

    public static function shortHash(string $content): string
    {
        return substr(hash('sha256', $content), 0, self::HASH_LENGTH);
    }
}
