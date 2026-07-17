<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Msv1Parser\Internal;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Filesystem\FileLoader;
use SineFine\Mnemosyne\Filesystem\FileSystemException;

final class FileLoaderTest extends TestCase
{
    private string $tempDir = '';

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/ponymator-loader-test-' . uniqid();
        mkdir($this->tempDir, 0777, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->tempDir . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->tempDir);
    }

    public function testLoadReadsFileContent(): void
    {
        $path = $this->tempDir . '/sample.msv1';
        file_put_contents($path, "@class Foo\n");

        $this->assertSame("@class Foo\n", (new FileLoader())->load($path));
    }

    public function testLoadReadsEmptyFile(): void
    {
        $path = $this->tempDir . '/empty.msv1';
        file_put_contents($path, '');

        $this->assertSame('', (new FileLoader())->load($path));
    }

    public function testLoadThrowsOnMissingFile(): void
    {
        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('File not found');
        (new FileLoader())->load($this->tempDir . '/does-not-exist.msv1');
    }

    public function testLoadThrowsOnDirectory(): void
    {
        $this->expectException(FilesystemException::class);
        $this->expectExceptionMessage('File not found');
        (new FileLoader())->load($this->tempDir);
    }

    public function testLoadAllReadsMultipleFilesInOrder(): void
    {
        $a = $this->tempDir . '/a.msv1';
        $b = $this->tempDir . '/b.msv1';
        file_put_contents($a, '@class A');
        file_put_contents($b, '@class B');

        $contents = (new FileLoader())->loadAll([$a, $b]);

        $this->assertSame(['@class A', '@class B'], $contents);
    }

    public function testLoadAllAcceptsGeneratorInput(): void
    {
        $a = $this->tempDir . '/a.msv1';
        file_put_contents($a, '@class A');

        $generator = (function () use ($a) {
            yield $a;
        })();

        $this->assertSame(['@class A'], (new FileLoader())->loadAll($generator));
    }

    public function testLoadAllShortCircuitsOnFirstError(): void
    {
        $a = $this->tempDir . '/a.msv1';
        file_put_contents($a, '@class A');

        $this->expectException(FilesystemException::class);
        (new FileLoader())->loadAll([$a, $this->tempDir . '/missing.msv1']);
    }
}
