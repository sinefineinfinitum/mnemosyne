<?php declare(strict_types=1);

namespace SineFine\Ponymator;

use SineFine\Ponymator\Cli\Error\ConfigException;

class Config
{
    private const DEFAULTS = [
        'source' => 'src',
        'target' => 'docs',
        'ignore' => ['vendor', 'tests'],
    ];

    /**
     * @var array{source: string, target: string, ignore: string[]} 
     */
    private array $config;

    public function __construct(?string $configPath = null)
    {
        $this->config = self::DEFAULTS;

        $path = $configPath ?? getcwd() . '/.ponymator.json';

        if (!file_exists($path)) {
            if ($configPath !== null) {
                throw new ConfigException("Config file not found at $path");
            }
            return;
        }

        $raw = @file_get_contents($path);
        if ($raw === false) {
            throw new ConfigException("Could not read config file at $path");
        }

        $parsed = json_decode($raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new ConfigException("Malformed config file at $path: " . json_last_error_msg());
        }

        if (isset($parsed['source'])) {
            $this->config['source'] = $parsed['source'];
        }
        if (isset($parsed['target'])) {
            $this->config['target'] = $parsed['target'];
        }
        if (isset($parsed['ignore']) && is_array($parsed['ignore'])) {
            $this->config['ignore'] = $parsed['ignore'];
        }
    }

    public function getSource(): string
    {
        return $this->config['source'];
    }

    public function getTarget(): string
    {
        return $this->config['target'];
    }

    /**
     * @return string[]
     */
    public function getIgnore(): array
    {
        return $this->config['ignore'];
    }

    public function getSourceAbsolute(): string
    {
        $path = $this->config['source'];
        if (str_starts_with($path, '/') || str_contains($path, ':\\') || str_contains($path, ':/')) {
            return $path;
        }
        return getcwd() . '/' . $path;
    }

    public function getTargetAbsolute(): string
    {
        $path = $this->config['target'];
        if (str_starts_with($path, '/') || str_contains($path, ':\\') || str_contains($path, ':/')) {
            return $path;
        }
        return getcwd() . '/' . $path;
    }
}
