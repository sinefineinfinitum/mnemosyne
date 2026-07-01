<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Config;
use SineFine\Mnemosyne\Filesystem\PathResolver;

final class PathResolverTest extends TestCase
{
    private PathResolver $resolver;

    protected function setUp(): void
    {
        $config = new Config(null);
        $ref = new \ReflectionProperty(Config::class, 'config');
        $ref->setAccessible(true);
        $ref->setValue(
            $config, [
            'source' => '/project/src',
            'target' => '/project/docs',
            'ignore' => ['vendor', 'tests'],
            ]
        );
        $this->resolver = new PathResolver($config);
    }

    public function testRelativeDocLinkSameDirectory(): void
    {
        $link = $this->resolver->relativeDocLink(
            'src/Config.md',
            'src/Helper.md'
        );
        $this->assertSame('Helper.md', $link);
    }

    public function testRelativeDocLinkSubdirectory(): void
    {
        $link = $this->resolver->relativeDocLink(
            'src/Config.md',
            'src/Service/UserService.md'
        );
        $this->assertSame('Service/UserService.md', $link);
    }

    public function testRelativeDocLinkParentDirectory(): void
    {
        $link = $this->resolver->relativeDocLink(
            'src/Service/UserService.md',
            'src/Config.md'
        );
        $this->assertSame('../Config.md', $link);
    }

    public function testRelativeDocLinkDifferentBranches(): void
    {
        $link = $this->resolver->relativeDocLink(
            'src/Analyzer/Foo.md',
            'src/Renderer/Bar.md'
        );
        $this->assertSame('../Renderer/Bar.md', $link);
    }

    public function testRelativeDocLinkDeepNesting(): void
    {
        $link = $this->resolver->relativeDocLink(
            'src/A/B/C/D.md',
            'src/A/B/E/F.md'
        );
        $this->assertSame('../E/F.md', $link);
    }

    public function testRelativeDocLinkRootLevel(): void
    {
        $link = $this->resolver->relativeDocLink(
            'readme.md',
            'docs/guide.md'
        );
        $this->assertSame('docs/guide.md', $link);
    }

    public function testRelativeDocLinkIdentical(): void
    {
        $link = $this->resolver->relativeDocLink(
            'src/Foo.md',
            'src/Foo.md'
        );
        $this->assertSame('Foo.md', $link);
    }

    public function testRelativeDocLinkSameDirNoSubdir(): void
    {
        $link = $this->resolver->relativeDocLink(
            'foo.md',
            'bar.md'
        );
        $this->assertSame('bar.md', $link);
    }

    public function testRelativeDocLinkNoCommonPrefix(): void
    {
        $link = $this->resolver->relativeDocLink(
            'a/b/c.md',
            'd/e/f.md'
        );
        $this->assertSame('../../d/e/f.md', $link);
    }

    public function testRelativeDocLinkFromDeeperThanToPartialCommon(): void
    {
        $link = $this->resolver->relativeDocLink(
            'a/b/c/d.md',
            'a/e.md'
        );
        $this->assertSame('../../e.md', $link);
    }

    public function testRelativeDocLinkWindowsBackslashes(): void
    {
        $link = $this->resolver->relativeDocLink(
            'src\Analyzer\Foo.md',
            'src\Renderer\Bar.md'
        );
        $this->assertSame('../Renderer/Bar.md', $link);
    }

    public function testRelativeDocLinkToMuchDeeper(): void
    {
        $link = $this->resolver->relativeDocLink(
            'a/b.md',
            'a/b/c/d/e.md'
        );
        $this->assertSame('b/c/d/e.md', $link);
    }

    public function testRelativeDocLinkFromMuchDeeperPartialCommon(): void
    {
        $link = $this->resolver->relativeDocLink(
            'x/y/z/deep.md',
            'x/w.md'
        );
        $this->assertSame('../../w.md', $link);
    }

    public function testRelativeDocLinkNoDirectoryDotSlash(): void
    {
        $link = $this->resolver->relativeDocLink(
            'foo.md',
            'bar/baz.md'
        );
        $this->assertSame('bar/baz.md', $link);
    }

    public function testRelativeDocLinkAllUpNoDown(): void
    {
        $link = $this->resolver->relativeDocLink(
            'a/b/c/d.md',
            'a/b/e.md'
        );
        $this->assertSame('../e.md', $link);
    }
}
