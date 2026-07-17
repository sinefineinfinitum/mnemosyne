<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Filesystem;

final class FileLoader
{
    public function load(string $path): string
    {
        if (!is_file($path)) {
            throw new FileSystemException(sprintf('File not found: %s', $path));
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            throw new FileSystemException(sprintf('Failed to read file: %s', $path));
        }

        return $content;
    }

    /**
     * @param  iterable<string> $paths
     * @return string[]
     */
    public function loadAll(iterable $paths): array
    {
        $contents = [];
        foreach ($paths as $path) {
            $contents[] = $this->load($path);
        }
        return $contents;
    }
}
