<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Config;

class ConfigTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ponimator_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tempDir);
    }

    public function testDefaults(): void
    {
        $cwd = getcwd();
        chdir($this->tempDir);
        $config = new Config();
        chdir($cwd);
        $this->assertSame('src', $config->getSource());
        $this->assertSame('docs', $config->getTarget());
        $this->assertSame(['vendor', 'tests'], $config->getIgnore());
    }

    public function testCustomConfig(): void
    {
        $path = $this->tempDir . '/.ponimator.json';
        file_put_contents(
            $path, json_encode(
                [
                'source' => 'app',
                'target' => 'api-docs',
                'ignore' => ['vendor', 'node_modules'],
                ]
            )
        );
        $config = new Config($path);
        $this->assertSame('app', $config->getSource());
        $this->assertSame('api-docs', $config->getTarget());
        $this->assertSame(['vendor', 'node_modules'], $config->getIgnore());
    }

    public function testMissingFileWarning(): void
    {
        $path = $this->tempDir . '/nonexistent.json';
        $config = new Config($path);
        $this->assertSame('src', $config->getSource());
    }

    public function testMalformedJsonFallsBackToDefaults(): void
    {
        $path = $this->tempDir . '/.ponimator.json';
        file_put_contents($path, '{invalid json}');
        $config = new Config($path);
        $this->assertSame('src', $config->getSource());
        $this->assertSame('docs', $config->getTarget());
    }

    public function testPartialConfigMergesWithDefaults(): void
    {
        $path = $this->tempDir . '/.ponimator.json';
        file_put_contents($path, json_encode(['source' => 'custom_src']));
        $config = new Config($path);
        $this->assertSame('custom_src', $config->getSource());
        $this->assertSame('docs', $config->getTarget());
        $this->assertSame(['vendor', 'tests'], $config->getIgnore());
    }

    private function rrmdir(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->rrmdir($path) : unlink($path);
        }
        rmdir($dir);
    }
}
