<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Filesystem\FileSystemException;
use SineFine\Ponymator\Filesystem\Scanner;

final class ScannerTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ponymator_scan_' . uniqid();
        mkdir($this->tempDir . '/sub', 0777, true);
        mkdir($this->tempDir . '/vendor', 0777, true);
        touch($this->tempDir . '/User.php');
        touch($this->tempDir . '/sub/Service.php');
        touch($this->tempDir . '/sub/helper.php');
        touch($this->tempDir . '/vendor/autoload.php');
        touch($this->tempDir . '/README.md');
    }

    protected function tearDown(): void
    {
        $this->rrmdir($this->tempDir);
    }

    public function testScanReturnsPhpFiles(): void
    {
        $scanner = new Scanner($this->tempDir);
        $files = $scanner->scan();
        sort($files);
        $this->assertCount(4, $files);
        $this->assertStringEndsWith('User.php', $files[0]);
    }

    public function testScanIgnoresVendor(): void
    {
        $scanner = new Scanner($this->tempDir, ['vendor']);
        $files = $scanner->scan();
        foreach ($files as $file) {
            $this->assertStringNotContainsString('vendor', $file);
        }
        $this->assertCount(3, $files);
        $this->assertStringEndsWith('User.php', $files[0]);
        $this->assertStringEndsWith('Service.php', $files[1]);
        $this->assertStringEndsWith('helper.php', $files[2]);
    }

    public function testScanEmptyDirectory(): void
    {
        $emptyDir = $this->tempDir . '/empty';
        mkdir($emptyDir);
        $scanner = new Scanner($emptyDir);
        $this->assertSame([], $scanner->scan());
    }

    public function testScanNonExistentDirectory(): void
    {
        $scanner = new Scanner($this->tempDir . '/nonexistent');
        $this->expectException(FileSystemException::class);
        $this->expectExceptionMessage('Source directory does not exist: ');
        $scanner->scan();
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
