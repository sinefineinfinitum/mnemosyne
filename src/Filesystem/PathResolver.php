<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Filesystem;

use SineFine\Mnemosyne\Config;

class PathResolver
{
    public function __construct(
        private Config $config,
        private string $docExtension = 'md',
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
        return preg_replace('/\.php$/i', '.' . $this->docExtension, $sourceRelative) ?? $sourceRelative;
    }

    public function docExtension(): string
    {
        return $this->docExtension;
    }

    public function relativeTargetPath(string $fullPath): string
    {
        $prefix = rtrim(str_replace('\\', '/', $this->config->getTargetAbsolute()), '/') . '/';
        $path = str_replace('\\', '/', $fullPath);

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    public function relativeDocLink(string $fromDocPath, string $toDocPath): string
    {
        $normalize = fn(string $p) => str_replace('\\', '/', $p);

        $from = $normalize($fromDocPath);
        $to = $normalize($toDocPath);

        if ($from === $to) {
            return basename($to);
        }

        $fromDir = dirname($from);

        if ($fromDir === '.') {
            return $to;
        }

        $fromParts = $fromDir === '/' ? [] : explode('/', $fromDir);
        $toParts = explode('/', $to);
        $common = 0;
        $max = min(count($fromParts), count($toParts));
        for ($i = 0; $i < $max; $i++) {
            if ($fromParts[$i] !== $toParts[$i]) {
                break;
            }
            $common++;
        }

        $up = array_fill(0, count($fromParts) - $common, '..');
        $down = array_slice($toParts, $common);

        return implode('/', array_merge($up, $down));
    }

    public function targetDir(): string
    {
        return $this->config->getTargetAbsolute();
    }

}
