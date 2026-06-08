<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\Linker\CrossReferenceContext;
use SineFine\Ponymator\Analyzer\Linker\CrossReferenceIndexBuilder;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Filesystem\PathResolver;

final class CrossReferenceIndexBuilderTest extends TestCase
{
    public function testBuildReturnsCrossReferenceContext(): void
    {
        $parser = $this->createMock(Parser::class);
        $pathResolver = $this->createMock(PathResolver::class);

        $builder = new CrossReferenceIndexBuilder($parser, $pathResolver);

        $parser->method('parseFile')
            ->willReturnCallback(
                fn(string $path) => match ($path) {
                '/project/src/A.php' => $this->parseCode('<?php namespace App; class A {}'),
                '/project/src/B.php' => $this->parseCode('<?php namespace App; class B extends \App\A {}'),
                }
            );

        $pathResolver->method('sourcePath')
            ->willReturnCallback(fn(string $r) => '/project/src/' . $r);
        $pathResolver->method('docRelativePath')
            ->willReturnCallback(fn(string $r) => preg_replace('/\.php$/', '.md', $r));

        $context = $builder->build(['A.php', 'B.php']);

        $this->assertInstanceOf(CrossReferenceContext::class, $context);

        $usedBy = $context->getIndex()->getUsedBy('App\A');
        $this->assertSame(['App\B'], $usedBy);

        $this->assertSame(
            ['A.md', 'B.md'], [
            $context->getFqnToDocPath()['App\A'],
            $context->getFqnToDocPath()['App\B'],
            ]
        );
    }

    public function testBuildHandlesParseErrorsGracefully(): void
    {
        $parser = $this->createMock(Parser::class);
        $pathResolver = $this->createMock(PathResolver::class);

        $builder = new CrossReferenceIndexBuilder($parser, $pathResolver);

        $parser->method('parseFile')
            ->willReturnCallback(
                fn(string $path) => match ($path) {
                '/project/src/good.php' => $this->parseCode('<?php namespace App; class Good {}'),
                default => throw new \RuntimeException('Parse error'),
                }
            );

        $pathResolver->method('sourcePath')
            ->willReturnCallback(fn(string $r) => '/project/src/' . $r);
        $pathResolver->method('docRelativePath')
            ->willReturnCallback(fn(string $r) => preg_replace('/\.php$/', '.md', $r));

        $context = $builder->build(['good.php', 'broken.php']);

        $this->assertInstanceOf(CrossReferenceContext::class, $context);
        $this->assertArrayHasKey('App\Good', $context->getFqnToDocPath());
        $this->assertArrayNotHasKey('App\Broken', $context->getFqnToDocPath());
    }

    public function testBuildEmptyFilesReturnsEmptyContext(): void
    {
        $parser = $this->createMock(Parser::class);
        $pathResolver = $this->createMock(PathResolver::class);

        $builder = new CrossReferenceIndexBuilder($parser, $pathResolver);

        $context = $builder->build([]);

        $this->assertInstanceOf(CrossReferenceContext::class, $context);
        $this->assertSame([], $context->getFqnToDocPath());
        $this->assertSame([], $context->getIndex()->getUsedBy('Any\Fqn'));
    }

    public function testBuildExcludesSelfReferences(): void
    {
        $parser = $this->createMock(Parser::class);
        $pathResolver = $this->createMock(PathResolver::class);

        $builder = new CrossReferenceIndexBuilder($parser, $pathResolver);

        $parser->method('parseFile')
            ->willReturn($this->parseCode('<?php namespace App; class A { public function foo(): \App\A {} }'));

        $pathResolver->method('sourcePath')
            ->willReturn('/project/src/A.php');
        $pathResolver->method('docRelativePath')
            ->willReturn('A.md');

        $context = $builder->build(['A.php']);

        $this->assertSame([], $context->getIndex()->getUsedBy('App\A'));
    }

    public function testBuildIncludesTypeIndexFromPsv1Files(): void
    {
        $tempDir = $this->createPsv1Fixture(
            '@class App\Foo' . PHP_EOL .
            '.+bar' . PHP_EOL .
            '.+baz' . PHP_EOL .
            '$prop:string' . PHP_EOL
        );

        $parser = $this->createMock(Parser::class);
        $pathResolver = $this->createMock(PathResolver::class);

        $pathResolver->method('sourcePath')->willReturn('/project/src/A.php');
        $pathResolver->method('docRelativePath')->willReturn('A.md');
        $pathResolver->method('targetDir')->willReturn($tempDir);

        $builder = new CrossReferenceIndexBuilder($parser, $pathResolver);
        $context = $builder->build([]);

        $this->assertNotEmpty($context->getTypeIndex());
        $this->assertArrayHasKey('App\Foo', $context->getTypeIndex());
        $typeInfo = $context->getTypeIndex()['App\Foo'];
        $this->assertSame(['bar', 'baz'], $typeInfo->methods);
        $this->assertSame(['prop'], $typeInfo->properties);
        $this->assertSame('class', $typeInfo->kind);

        $this->removeDir($tempDir);
    }

    public function testBuildTypeIndexEmptyWhenTargetMissing(): void
    {
        $parser = $this->createMock(Parser::class);
        $pathResolver = $this->createMock(PathResolver::class);

        $pathResolver->method('sourcePath')->willReturn('/project/src/A.php');
        $pathResolver->method('docRelativePath')->willReturn('A.md');
        $pathResolver->method('targetDir')->willReturn('/nonexistent/target/' . uniqid());

        $builder = new CrossReferenceIndexBuilder($parser, $pathResolver);
        $context = $builder->build([]);

        $this->assertSame([], $context->getTypeIndex());
    }

    public function testBuildTypeIndexSkipsFileEntity(): void
    {
        $tempDir = $this->createPsv1Fixture(
            '@file src/functions' . PHP_EOL .
            '.+helper' . PHP_EOL
        );

        $parser = $this->createMock(Parser::class);
        $pathResolver = $this->createMock(PathResolver::class);

        $pathResolver->method('sourcePath')->willReturn('/project/src/A.php');
        $pathResolver->method('docRelativePath')->willReturn('A.md');
        $pathResolver->method('targetDir')->willReturn($tempDir);

        $builder = new CrossReferenceIndexBuilder($parser, $pathResolver);
        $context = $builder->build([]);

        $this->assertArrayNotHasKey('src/functions', $context->getTypeIndex());

        $this->removeDir($tempDir);
    }

    public function testBuildTypeIndexHandlesMalformedPsv1(): void
    {
        $tempDir = sys_get_temp_dir() . '/ponymator-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        file_put_contents($tempDir . '/bad.psv1', '@class');

        $parser = $this->createMock(Parser::class);
        $pathResolver = $this->createMock(PathResolver::class);

        $pathResolver->method('sourcePath')->willReturn('/project/src/A.php');
        $pathResolver->method('docRelativePath')->willReturn('A.md');
        $pathResolver->method('targetDir')->willReturn($tempDir);

        $builder = new CrossReferenceIndexBuilder($parser, $pathResolver);
        $context = $builder->build([]);

        $this->assertSame([], $context->getTypeIndex());

        $this->removeDir($tempDir);
    }

    private function createPsv1Fixture(string $contents): string
    {
        $tempDir = sys_get_temp_dir() . '/ponymator-test-' . uniqid();
        mkdir($tempDir, 0755, true);
        file_put_contents($tempDir . '/Fixture.psv1', $contents);
        return $tempDir;
    }

    private function removeDir(string $path): void
    {
        if (!is_dir($path)) {
            return;
        }
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($iterator as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }
        rmdir($path);
    }

    /**
     * @return array<int, \PhpParser\Node>
     */
    private function parseCode(string $code): array
    {
        $parser = (new \PhpParser\ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);
        $traverser = new \PhpParser\NodeTraverser();
        $traverser->addVisitor(new \PhpParser\NodeVisitor\NameResolver());
        return $traverser->traverse($ast);
    }
}
