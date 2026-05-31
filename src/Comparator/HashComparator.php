<?php declare(strict_types=1);

namespace SineFine\Ponymator\Comparator;

final class HashComparator
{
    public function computeContentHash(string $content): string
    {
        return hash('sha256', $content);
    }

    public function computeHash(string $filePath): string
    {
        $hash = hash_file('sha256', $filePath);
        if ($hash === false) {
            throw new \RuntimeException("Failed to compute hash for: $filePath");
        }
        return $hash;
    }

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
            if (str_starts_with($line, 'source_hash:')) {
                $hash = trim(substr($line, 12));
                return $hash !== '' ? $hash : null;
            }
        }

        return null;
    }

    public function hasChanged(string $sourcePath, string $docPath): bool
    {
        $stored = $this->extractStoredHash($docPath);
        if ($stored === null) {
            return true;
        }
        return $this->computeHash($sourcePath) !== $stored;
    }
}
