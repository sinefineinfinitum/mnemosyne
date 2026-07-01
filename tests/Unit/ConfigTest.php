<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Cli\Error\ConfigException;
use SineFine\Mnemosyne\Config;

final class ConfigTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mnemosyne_test_' . uniqid();
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
        $path = $this->tempDir . '/.mnemosyne.json';
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

    public function testMissingConfigDefaults(): void
    {
        $cwd = getcwd();
        chdir($this->tempDir);
        $config = new Config(null);
        chdir($cwd);
        $this->assertSame('src', $config->getSource());
        $this->assertSame('docs', $config->getTarget());
    }

    public function testMissingConfigThrowsException(): void
    {
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Config file not found');
        new Config('non_existent.json');
    }

    public function testMalformedConfigThrowsException(): void
    {
        $path = $this->tempDir . '/malformed.json';
        file_put_contents($path, '{invalid json}');
        $this->expectException(ConfigException::class);
        $this->expectExceptionMessage('Malformed config file');
        new Config($path);
    }

    public function testPartialConfigMergesWithDefaults(): void
    {
        $path = $this->tempDir . '/.mnemosyne.json';
        file_put_contents($path, json_encode(['source' => 'custom_src']));
        $config = new Config($path);
        $this->assertSame('custom_src', $config->getSource());
        $this->assertSame('docs', $config->getTarget());
        $this->assertSame(['vendor', 'tests'], $config->getIgnore());
    }

    public function testDbPathDefaultIsNull(): void
    {
        $cwd = getcwd();
        chdir($this->tempDir);
        $config = new Config(null);
        chdir($cwd);
        $this->assertNull($config->getDbPath());
    }

    public function testDbPathFromConfig(): void
    {
        $path = $this->tempDir . '/.mnemosyne.json';
        file_put_contents($path, json_encode(['dbPath' => 'data/graph.db']));
        $config = new Config($path);
        $this->assertSame('data/graph.db', $config->getDbPath());
    }

    public function testDbPathNotOverriddenByDefault(): void
    {
        $path = $this->tempDir . '/.mnemosyne.json';
        file_put_contents($path, json_encode(['source' => 'app']));
        $config = new Config($path);
        $this->assertNull($config->getDbPath());
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
