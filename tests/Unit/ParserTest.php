<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\Parser;

final class ParserTest extends TestCase
{
    private string $tempDir;

    protected function setUp(): void
    {
        $this->tempDir = sys_get_temp_dir() . '/mnemosyne_parse_' . uniqid();
        mkdir($this->tempDir);
    }

    protected function tearDown(): void
    {
        $files = array_diff(scandir($this->tempDir), ['.', '..']);
        foreach ($files as $f) {
            unlink($this->tempDir . '/' . $f);
        }
        rmdir($this->tempDir);
    }

    public function testParseValidPhp(): void
    {
        $path = $this->tempDir . '/test.php';
        file_put_contents($path, '<?php namespace App; class Foo {}');
        $parser = new Parser();
        $ast = $parser->parseFile($path);
        $this->assertIsArray($ast);
        $this->assertNotEmpty($ast);
    }

    public function testParseSyntaxErrorThrows(): void
    {
        $path = $this->tempDir . '/broken.php';
        file_put_contents($path, '<?php class { invalid');
        $parser = new Parser();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Parse error');
        $parser->parseFile($path);
    }

    public function testParseMissingFileThrows(): void
    {
        $parser = new Parser();
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('File not found');
        $parser->parseFile('/nonexistent/file.php');
    }
}
