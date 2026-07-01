<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Comparator\HashComparator;

final class HashComparatorTest extends TestCase
{
    private HashComparator $comparator;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->comparator = new HashComparator();
        $this->tempDir = sys_get_temp_dir() . '/mnemosyne-hash-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    }

    public function testExtractStoredHashReturnsNullForMissingFile(): void
    {
        $this->assertNull($this->comparator->extractStoredHash('/nonexistent.md'));
    }

    public function testExtractStoredHashReturnsNullForNoFrontmatter(): void
    {
        $path = $this->tempDir . '/no-frontmatter.md';
        file_put_contents($path, '# Just a heading');
        $this->assertNull($this->comparator->extractStoredHash($path));
    }

    public function testExtractStoredHashReturnsHash(): void
    {
        $path = $this->tempDir . '/test.md';
        file_put_contents($path, "---\nhash: abc123def456\n---\n# Doc");
        $this->assertSame('abc123def456', $this->comparator->extractStoredHash($path));
    }
}
