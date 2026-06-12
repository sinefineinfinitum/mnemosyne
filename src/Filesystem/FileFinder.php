<?php declare(strict_types=1);

namespace SineFine\Ponymator\Filesystem;

use FilesystemIterator;
use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class FileFinder
{
    /**
     * Find files recursively matching given extensions.
     *
     * @param string   $dir            Root directory
     * @param string[] $extensions     Allowed extensions without dot (e.g. ['php', 'psv1'])
     * @param string[] $ignorePatterns Directory name patterns to skip
     * @return string[] Absolute paths, sorted
     */
    public function find(string $dir, array $extensions, array $ignorePatterns = []): array
    {
        $dir = $this->normalizePath($dir);

        if (!is_dir($dir)) {
            return [];
        }

        $normalizedIgnore = $this->normalizePatterns($ignorePatterns);

        $iterator = new RecursiveIteratorIterator(
            new RecursiveCallbackFilterIterator(
                new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
                function (SplFileInfo $file) use ($dir, $normalizedIgnore): bool {
                    if (!$file->isDir()) {
                        return true;
                    }
                    $relative = $this->getRelativePath($file->getPathname(), $dir);
                    return !$this->matchesAny($relative, $normalizedIgnore);
                }
            )
        );

        $extensions = array_map('strtolower', $extensions);
        $files = [];

        foreach ($iterator as $file) {
            if ($file->isFile() && in_array(strtolower($file->getExtension()), $extensions, true)) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);
        return $files;
    }

    public function normalizePath(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }

    public function getRelativePath(string $fullPath, string $baseDir): string
    {
        $fullPath = $this->normalizePath($fullPath);
        return str_starts_with($fullPath, $baseDir . '/')
            ? substr($fullPath, strlen($baseDir) + 1)
            : $fullPath;
    }

    private function matchesAny(string $relativePath, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $relativePath)
                || fnmatch($pattern, basename($relativePath))
                || str_starts_with($relativePath, $pattern . '/')
                || str_contains($relativePath, '/' . $pattern . '/')
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * @param string[] $patterns
     * @return string[]
     */
    public function normalizePatterns(array $patterns): array
    {
        $result = [];
        foreach ($patterns as $pattern) {
            $pattern = trim(ltrim($this->normalizePath($pattern), '/'));
            if ($pattern !== '') {
                $result[] = $pattern;
            }
        }
        return array_values(array_unique($result));
    }
}
