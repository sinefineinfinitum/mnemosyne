<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Documentation\Linker\DocLinker;
use SineFine\Mnemosyne\Filesystem\PathResolver;

final class DocLinkerTest extends TestCase
{
    public function testMapsKnownFqnToMarkdownLink(): void
    {
        $resolver = $this->createMock(PathResolver::class);
        $resolver->method('relativeDocLink')
            ->with('current/Page.md', 'target/Foo.md')
            ->willReturn('../target/Foo.md');

        $linker = new DocLinker(
            ['App\Foo' => 'target/Foo.md'],
            $resolver,
        );

        $result = $linker->mapToLinks(['App\Foo'], 'current/Page.md');

        $this->assertSame(['[App\Foo](../target/Foo.md)'], $result);
    }

    public function testMapsUnknownFqnToCodeSpan(): void
    {
        $resolver = $this->createMock(PathResolver::class);

        $linker = new DocLinker([], $resolver);

        $result = $linker->mapToLinks(['App\Unknown'], 'current/Page.md');

        $this->assertSame(['`App\Unknown`'], $result);
    }

    public function testNormalizesLeadingBackslash(): void
    {
        $resolver = $this->createMock(PathResolver::class);
        $resolver->method('relativeDocLink')
            ->with('current/Page.md', 'target/Foo.md')
            ->willReturn('../target/Foo.md');

        $linker = new DocLinker(
            ['App\Foo' => 'target/Foo.md'],
            $resolver,
        );

        $result = $linker->mapToLinks(['\App\Foo'], 'current/Page.md');

        $this->assertSame(['[App\Foo](../target/Foo.md)'], $result);
    }

    public function testMapsMultipleFqns(): void
    {
        $resolver = $this->createMock(PathResolver::class);
        $resolver->method('relativeDocLink')
            ->willReturnCallback(
                fn(string $from, string $to) => match ($to) {
                'target/Foo.md' => 'Foo.md',
                'target/Bar.md' => 'Bar.md',
                }
            );

        $linker = new DocLinker(
            [
                'App\Foo' => 'target/Foo.md',
                'App\Bar' => 'target/Bar.md',
            ],
            $resolver,
        );

        $result = $linker->mapToLinks(['App\Foo', 'App\Baz', 'App\Bar'], 'current/Page.md');

        $this->assertSame(
            ['[App\Foo](Foo.md)', '`App\Baz`', '[App\Bar](Bar.md)'],
            $result,
        );
    }

    public function testEmptyInputReturnsEmptyArray(): void
    {
        $resolver = $this->createMock(PathResolver::class);

        $linker = new DocLinker([], $resolver);

        $this->assertSame([], $linker->mapToLinks([], 'any/Page.md'));
    }
}
