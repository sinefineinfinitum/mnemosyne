<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Linker;

use SineFine\Ponymator\Filesystem\PathResolver;

final class DocLinker
{
    /**
     * @param array<string, string> $fqnToDocPath fqn => relative doc path
     */
    public function __construct(
        private array $fqnToDocPath,
        private PathResolver $pathResolver,
    ) {
    }

    public function resolveTypeLink(string $fqn, string $currentDocPath): ?string
    {
        $normalized = ltrim($fqn, '\\');
        $targetDocPath = $this->fqnToDocPath[$normalized] ?? null;
        if ($targetDocPath === null) {
            return null;
        }
        return $this->pathResolver->relativeDocLink($currentDocPath, $targetDocPath);
    }

    /**
     * @param  string $currentDocPath
     * @return callable(string): ?string
     */
    public function getResolver(string $currentDocPath): callable
    {
        return fn(string $fqn): ?string => $this->resolveTypeLink($fqn, $currentDocPath);
    }

    /**
     * @param  string[] $fqns
     * @param  string   $currentDocPath
     * @return string[]
     */
    public function mapToLinks(array $fqns, string $currentDocPath): array
    {
        $links = [];
        foreach ($fqns as $fqn) {
            $normalized = ltrim($fqn, '\\');
            $targetDocPath = $this->fqnToDocPath[$normalized] ?? null;

            if ($targetDocPath === null) {
                $links[] = '`' . $normalized . '`';
            } else {
                $link = $this->pathResolver->relativeDocLink($currentDocPath, $targetDocPath);
                $links[] = '[' . $normalized . '](' . $link . ')';
            }
        }

        return $links;
    }
}
