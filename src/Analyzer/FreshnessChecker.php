<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer;
use SineFine\Ponymator\Comparator\HashComparator;
use SineFine\Ponymator\Filesystem\PathResolver;

final class FreshnessChecker
{
    public function __construct(
        private PathResolver $paths,
        private HashComparator $hashComparator,
    ) {
    }

    /**
     * @param string[] $sourceFiles
     */
    public function check(array $sourceFiles): int
    {
        $staleCount = 0;

        foreach ($sourceFiles as $relativePath) {
            $sourcePath = $this->paths->sourcePath($relativePath);
            $docPath = $this->paths->docPath($relativePath);

            if (!file_exists($docPath)) {
                fwrite(STDERR, "Missing doc: $relativePath\n");
                $staleCount++;
                continue;
            }

            $currentHash = $this->hashComparator->computeHash($sourcePath);
            $storedHash = $this->hashComparator->extractStoredHash($docPath);

            if ($storedHash === null || $currentHash !== $storedHash) {
                fwrite(STDERR, "Stale doc: $relativePath\n");
                $staleCount++;
            }
        }

        return $staleCount;
    }
}
