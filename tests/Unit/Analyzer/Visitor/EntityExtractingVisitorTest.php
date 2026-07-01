<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Analyzer\Visitor;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\Extractor\AstHelper;
use SineFine\Mnemosyne\Analyzer\Extractor\ClassExtractor;
use SineFine\Mnemosyne\Analyzer\Extractor\EntityExtractorInterface;
use SineFine\Mnemosyne\Analyzer\Extractor\InterfaceExtractor;
use SineFine\Mnemosyne\Analyzer\Visitor\EntityExtractingVisitor;

final class EntityExtractingVisitorTest extends TestCase
{
    public function testExtractsClass(): void
    {
        $visitor = new EntityExtractingVisitor(
            [
            new ClassExtractor('App', new AstHelper()),
            ]
        );

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($visitor);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php namespace App; class Foo {}');
        $traverser->traverse($ast);

        $entities = $visitor->entities();
        $this->assertCount(1, $entities);
        $this->assertSame('App\Foo', $entities[0]['fqn']);
    }

    public function testExtractsFirstMatchingExtractorOnly(): void
    {
        $visitor = new EntityExtractingVisitor(
            [
            new ClassExtractor('App', new AstHelper()),
            new InterfaceExtractor('App', new AstHelper()),
            ]
        );

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($visitor);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php namespace App; class Foo {}');
        $traverser->traverse($ast);

        $entities = $visitor->entities();
        $this->assertCount(1, $entities);
        $this->assertSame('App\Foo', $entities[0]['fqn']);
    }

    public function testNoMatchingExtractorEmitsEmpty(): void
    {
        $neverMatch = new class implements EntityExtractorInterface {
            public function supports(Node $node): bool
            {
                return false;
            }
            public function extract(Node $node): array
            {
                return [];
            }
        };

        $visitor = new EntityExtractingVisitor([$neverMatch]);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($visitor);

        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php class Foo {}');
        $traverser->traverse($ast);

        $this->assertSame([], $visitor->entities());
    }
}
