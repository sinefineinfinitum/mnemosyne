<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Comparator;

final class HashComparator
{
    public function extractStoredHash(string $docPath): ?string
    {
        if (!file_exists($docPath)) {
            return null;
        }

        $content = file_get_contents($docPath);
        if ($content === false) {
            return null;
        }

        if (!str_starts_with($content, '---')) {
            return null;
        }

        $end = strpos($content, '---', 3);
        if ($end === false) {
            return null;
        }

        $frontmatter = substr($content, 3, $end - 3);

        foreach (explode("\n", $frontmatter) as $line) {
            $line = trim($line);
            if (str_starts_with($line, 'hash:')) {
                $hash = trim(substr($line, 5));
                return $hash !== '' ? $hash : null;
            }
        }

        return null;
    }
}
