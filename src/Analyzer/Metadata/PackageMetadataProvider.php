<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Metadata;

final class PackageMetadataProvider
{
    private ?string $projectRoot;
    private ?array $lockData = null;

    public function __construct(?string $projectRoot = null)
    {
        $this->projectRoot = $projectRoot ?? getcwd();
    }

    /**
     * @return array{version: string, description: string}
     */
    public function getPackageInfo(string $packageName): array
    {
        $info = $this->getFromLock($packageName);
        if ($info !== null) {
            return $info;
        }

        $info = $this->getFromVendorJson($packageName);
        if ($info !== null) {
            return $info;
        }

        return ['version' => 'unknown', 'description' => 'unknown'];
    }

    /**
     * @return string[]
     */
    public function getInstalledPackageNames(): array
    {
        $lock = $this->loadLock();
        if ($lock === null) {
            return [];
        }
        return array_column(array_merge($lock['packages'] ?? [], $lock['packages-dev'] ?? []), 'name');
    }

    private function loadLock(): ?array
    {
        if ($this->lockData !== null) {
            return $this->lockData;
        }
        $path = $this->projectRoot . '/composer.lock';
        if (!file_exists($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }
        $this->lockData = $data;
        return $data;
    }

    /**
     * @return array{version: string, description: string}|null
     */
    private function getFromLock(string $packageName): ?array
    {
        $lock = $this->loadLock();
        if ($lock === null) {
            return null;
        }
        foreach (['packages', 'packages-dev'] as $key) {
            foreach ($lock[$key] ?? [] as $pkg) {
                if (($pkg['name'] ?? '') === $packageName) {
                    $version = $pkg['version'] ?? 'unknown';
                    $version = ltrim($version, 'v');
                    return [
                        'version' => $version,
                        'description' => $pkg['description'] ?? 'unknown',
                    ];
                }
            }
        }
        return null;
    }

    /**
     * @return array{version: string, description: string}|null
     */
    private function getFromVendorJson(string $packageName): ?array
    {
        $path = $this->projectRoot . '/vendor/' . $packageName . '/composer.json';
        if (!file_exists($path)) {
            return null;
        }
        $raw = file_get_contents($path);
        if ($raw === false) {
            return null;
        }
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }
        $version = isset($data['version']) ? ltrim($data['version'], 'v') : 'unknown';
        return [
            'version' => $version,
            'description' => $data['description'] ?? 'unknown',
        ];
    }
}
