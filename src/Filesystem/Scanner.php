<?php declare(strict_types=1);

namespace SineFine\Ponymator\Filesystem;

use FilesystemIterator;

use InvalidArgumentException;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

class Scanner
{
    private string $sourceDir;

    /**
     * @var string[]
     */
    private array $ignorePatterns;

    /**
     * @param string   $sourceDir
     * @param string[] $ignorePatterns
     */
    public function __construct(
        string $sourceDir,
        array $ignorePatterns = [],
    ) {
        $this->sourceDir = $this->normalizePath($sourceDir);
        $this->ignorePatterns = $this->normalizeIgnorePatterns($ignorePatterns);
    }

    /**
     * @return string[]
     * @throws InvalidArgumentException
     */
    public function scan(): array
    {
        if (!is_dir($this->sourceDir)) {
            throw new InvalidArgumentException("Source directory does not exist: " . $this->sourceDir);
        }

        $files = [];
        $iterator = new RecursiveIteratorIterator(
            $this->createDirectoryIterator($this->sourceDir)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$this->isPhpFile($file)) {
                continue;
            }

            $relativePath = $this->getRelativePath($file->getPathname());

            if ($this->isIgnored($relativePath)) {
                continue;
            }

            $files[] = $relativePath;
        }

        sort($files);

        return $files;
    }

    /**
     * @return RecursiveCallbackFilterIterator<string, SplFileInfo, RecursiveDirectoryIterator>
     */
    private function createDirectoryIterator(string $directory): RecursiveCallbackFilterIterator
    {
        $iterator = new RecursiveDirectoryIterator($directory, FilesystemIterator::SKIP_DOTS);

        return new RecursiveCallbackFilterIterator(
            $iterator,
            function (SplFileInfo $file): bool {
                if (!$file->isDir()) {
                    return true;
                }

                $relativePath = $this->getRelativePath($file->getPathname());

                return !$this->isIgnored($relativePath);
            }
        );
    }

    private function isPhpFile(SplFileInfo $file): bool
    {
        return $file->isFile() && strtolower($file->getExtension()) === 'php';
    }

    private function getRelativePath(string $fullPath): string
    {
        $path = $this->normalizePath($fullPath);
        $prefix = $this->sourceDir . '/';

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    private function isIgnored(string $relativePath): bool
    {
        $relativePath = $this->normalizeRelativePath($relativePath);

        foreach ($this->ignorePatterns as $pattern) {
            if ($this->matchesIgnorePattern($relativePath, $pattern)) {
                return true;
            }
        }

        return false;
    }

    private function matchesIgnorePattern(string $relativePath, string $pattern): bool
    {
        return fnmatch($pattern, $relativePath)
            || fnmatch($pattern, basename($relativePath))
            || str_starts_with($relativePath, $pattern . '/')
            || str_contains($relativePath, '/' . $pattern . '/');
    }

    private function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    private function normalizeRelativePath(string $path): string
    {
        return ltrim($this->normalizePath($path), '/');
    }

    /**
     * @param string[] $patterns
     *
     * @return string[]
     */
    private function normalizeIgnorePatterns(array $patterns): array
    {
        $normalized = [];

        foreach ($patterns as $pattern) {
            $pattern = trim($this->normalizeRelativePath($pattern));

            if ($pattern === '') {
                continue;
            }

            $normalized[] = $pattern;
        }

        return array_values(array_unique($normalized));
    }
}
