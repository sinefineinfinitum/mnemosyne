<?php declare(strict_types=1);

namespace SineFine\Ponymator\Filesystem;

final class Scanner
{
    private string $sourceDir;

    /**
     * @var string[]
     */
    private array $ignorePatterns;

    private FileFinder $fileFinder;

    /**
     * @param string   $sourceDir
     * @param string[] $ignorePatterns
     */
    public function __construct(
        string $sourceDir,
        array $ignorePatterns = [],
    ) {
        $this->sourceDir = $this->normalizePath($sourceDir);
        $this->ignorePatterns = $this->normalizePatterns($ignorePatterns);
        $this->fileFinder = new FileFinder();
    }

    /**
     * @return string[]
     * @throws FileSystemException
     */
    public function scan(): array
    {
        if (!is_dir($this->sourceDir)) {
            throw new FileSystemException("Source directory does not exist: " . $this->sourceDir);
        }

        $absoluteFiles = $this->fileFinder->find($this->sourceDir, ['php'], $this->ignorePatterns);

        return array_map(fn(string $path): string => $this->getRelativePath($path), $absoluteFiles);
    }

    private function getRelativePath(string $fullPath): string
    {
        $path = $this->normalizePath($fullPath);

        return str_starts_with($path, $this->sourceDir . '/')
            ? substr($path, strlen($this->sourceDir) + 1)
            : $path;
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    /**
     * @param string[] $patterns
     * @return string[]
     */
    private function normalizePatterns(array $patterns): array
    {
        $result = [];

        foreach ($patterns as $pattern) {
            $pattern = trim(ltrim($this->normalizePath($pattern), '/'));

            if ($pattern === '') {
                continue;
            }

            $result[] = $pattern;
        }

        return array_values(array_unique($result));
    }
}
