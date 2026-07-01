<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Filesystem;

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
        $this->fileFinder = new FileFinder();
        $this->sourceDir = $this->fileFinder->normalizePath($sourceDir);
        $this->ignorePatterns = $this->fileFinder->normalizePatterns($ignorePatterns);
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
        return $this->fileFinder->getRelativePath($fullPath, $this->sourceDir);
    }
}
