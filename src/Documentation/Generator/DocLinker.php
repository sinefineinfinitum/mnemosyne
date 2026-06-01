<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Generator;

use SineFine\Ponymator\Analyzer\VendorPackageResolver;
use SineFine\Ponymator\Filesystem\PathResolver;

final class DocLinker
{
    /**
     * @param array<string, string> $fqnToDocPath fqn => relative doc path
     */
    public function __construct(
        private array $fqnToDocPath,
        private PathResolver $pathResolver,
        private ?VendorPackageResolver $vendorResolver = null,
    ) {
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

            if ($targetDocPath !== null) {
                $link = $this->pathResolver->relativeDocLink($currentDocPath, $targetDocPath);
                $links[] = '[' . $normalized . '](' . $link . ')';
            } elseif ($this->vendorResolver !== null) {
                $packageName = $this->vendorResolver->resolve($normalized);
                if ($packageName !== null) {
                    $vendorLink = $this->pathResolver->relativeDocLink($currentDocPath, 'vendor.md');
                    $links[] = '[' . $normalized . '](' . $vendorLink . ')';
                } else {
                    $links[] = '`' . $normalized . '`';
                }
            } else {
                $links[] = '`' . $normalized . '`';
            }
        }

        return $links;
    }
}
