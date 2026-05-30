<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Comparator\HashComparator;

final class HashComparatorTest extends TestCase
{
    private HashComparator $comparator;
    private string $tempDir;

    protected function setUp(): void
    {
        $this->comparator = new HashComparator();
        $this->tempDir = sys_get_temp_dir() . '/ponimator-hash-test-' . uniqid();
        mkdir($this->tempDir, 0755, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tempDir)) {
            array_map('unlink', glob($this->tempDir . '/*'));
            rmdir($this->tempDir);
        }
    }

    public function testComputeHashReturnsString(): void
    {
        $path = $this->tempDir . '/test.php';
        file_put_contents($path, '<?php echo "hello";');
        $hash = $this->comparator->computeHash($path);
        $this->assertSame(64, strlen($hash));
    }

    public function testSameContentSameHash(): void
    {
        $a = $this->tempDir . '/a.php';
        $b = $this->tempDir . '/b.php';
        file_put_contents($a, 'same content');
        file_put_contents($b, 'same content');
        $this->assertSame($this->comparator->computeHash($a), $this->comparator->computeHash($b));
    }

    public function testDifferentContentDifferentHash(): void
    {
        $a = $this->tempDir . '/a.php';
        $b = $this->tempDir . '/b.php';
        file_put_contents($a, 'content a');
        file_put_contents($b, 'content b');
        $this->assertNotSame($this->comparator->computeHash($a), $this->comparator->computeHash($b));
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
        file_put_contents($path, "---\nsource_hash: abc123\n---\n# Doc");
        $this->assertSame('abc123', $this->comparator->extractStoredHash($path));
    }

    public function testHasChangedReturnsTrueForMissingDoc(): void
    {
        $src = $this->tempDir . '/source.php';
        file_put_contents($src, '<?php');
        $this->assertTrue($this->comparator->hasChanged($src, '/nonexistent.md'));
    }

    public function testHasChangedReturnsFalseForIdentical(): void
    {
        $src = $this->tempDir . '/source.php';
        $doc = $this->tempDir . '/doc.md';
        file_put_contents($src, '<?php echo "hi";');
        $hash = $this->comparator->computeHash($src);
        file_put_contents($doc, "---\nsource_hash: $hash\n---\n# Doc");
        $this->assertFalse($this->comparator->hasChanged($src, $doc));
    }

    public function testHasChangedReturnsTrueForModified(): void
    {
        $src = $this->tempDir . '/source.php';
        $doc = $this->tempDir . '/doc.md';
        file_put_contents($src, '<?php echo "original";');
        file_put_contents($doc, "---\nsource_hash: " . $this->comparator->computeHash($src) . "\n---\n# Doc");
        file_put_contents($src, '<?php echo "modified";');
        $this->assertTrue($this->comparator->hasChanged($src, $doc));
    }
}
