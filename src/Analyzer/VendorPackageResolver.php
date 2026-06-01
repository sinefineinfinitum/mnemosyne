<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer;

use SineFine\Ponymator\Analyzer\Metadata\PackageMetadataProvider;

final class VendorPackageResolver
{
    /**
     * @var array<string, string> namespace-prefix => package-name
     */
    private array $prefixMap = [];

    private PackageMetadataProvider $metadataProvider;

    /**
     * @param string[] $installedPackages
     */
    public function __construct(
        array $installedPackages,
        PackageMetadataProvider $metadataProvider,
        ?string $projectRoot = null,
    ) {
        $this->metadataProvider = $metadataProvider;
        $projectRoot = $projectRoot ?? getcwd();
        $this->buildPrefixMap($installedPackages, $projectRoot);
    }

    public function resolve(string $fqn): ?string
    {
        $normalized = ltrim($fqn, '\\');

        if (BuiltinClassList::isBuiltin($normalized)) {
            return null;
        }

        $bestPackage = null;
        $bestLength = 0;

        foreach ($this->prefixMap as $prefix => $package) {
            $prefixNormalized = rtrim($prefix, '\\') . '\\';
            if (stripos($normalized . '\\', $prefixNormalized) === 0
                || stripos($normalized, $prefixNormalized) === 0
            ) {
                $len = strlen($prefix);
                if ($len > $bestLength) {
                    $bestLength = $len;
                    $bestPackage = $package;
                }
            }
        }

        return $bestPackage;
    }

    /**
     * @return array{version: string, description: string}
     */
    public function getPackageInfo(string $packageName): array
    {
        return $this->metadataProvider->getPackageInfo($packageName);
    }

    public function getShortName(string $fqn): string
    {
        $parts = explode('\\', ltrim($fqn, '\\'));
        return end($parts);
    }

    /**
     * @param string[] $installedPackages
     */
    private function buildPrefixMap(array $installedPackages, string $projectRoot): void
    {
        foreach ($installedPackages as $packageName) {
            $jsonPath = $projectRoot . '/vendor/' . $packageName . '/composer.json';
            if (!file_exists($jsonPath)) {
                continue;
            }
            $raw = file_get_contents($jsonPath);
            if ($raw === false) {
                continue;
            }
            $data = json_decode($raw, true);
            if (!is_array($data)) {
                continue;
            }

            $autoload = $data['autoload'] ?? [];
            foreach (['psr-4', 'psr-0'] as $standard) {
                $prefixes = $autoload[$standard] ?? [];
                if (!is_array($prefixes)) {
                    continue;
                }
                foreach ($prefixes as $prefix => $dirs) {
                    if ($prefix === '') {
                        continue;
                    }
                    $ns = ltrim($prefix, '\\');
                    $this->prefixMap[$ns] = $packageName;
                }
            }
        }
    }

    /**
     * @return array<string, string>
     */
    public function getPrefixMap(): array
    {
        return $this->prefixMap;
    }
}
