<?php declare(strict_types=1);

namespace SineFine\Ponymator\Filesystem;

use SineFine\Ponymator\Config;

class PathResolver
{
    public function __construct(
        private Config $config,
    ) {
    }

    public function sourcePath(string $relativePath): string
    {
        return $this->config->getSourceAbsolute() . '/' . $relativePath;
    }

    public function docPath(string $sourceRelative): string
    {
        return $this->config->getTargetAbsolute() . '/' . $this->docRelativePath($sourceRelative);
    }

    public function docRelativePath(string $sourceRelative): string
    {
        return preg_replace('/\.php$/i', '.md', $sourceRelative) ?? $sourceRelative;
    }

    public function relativeTargetPath(string $fullPath): string
    {
        $prefix = rtrim(str_replace('\\', '/', $this->config->getTargetAbsolute()), '/') . '/';
        $path = str_replace('\\', '/', $fullPath);

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    public function targetDir(): string
    {
        return $this->config->getTargetAbsolute();
    }

}
