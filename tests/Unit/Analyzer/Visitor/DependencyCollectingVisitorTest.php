<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Analyzer\Visitor;

use PHPUnit\Framework\TestCase;
use PhpParser\Node\Name;
use PhpParser\Node\Param;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use SineFine\Ponymator\Analyzer\Visitor\DependencyCollectingVisitor;

final class DependencyCollectingVisitorTest extends TestCase
{
    private DependencyCollectingVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new DependencyCollectingVisitor();
    }

    public function testSelfNotCollectedAsDependency(): void
    {
        $this->visitor->enterNode(new ClassMethod('foo', ['returnType' => new Name('self')]));
        $this->assertSame([], $this->visitor->dependencies());
    }

    public function testParentNotCollectedAsDependency(): void
    {
        $this->visitor->enterNode(new ClassMethod('foo', ['returnType' => new Name('parent')]));
        $this->assertSame([], $this->visitor->dependencies());
    }

    public function testExplicitFqcnCollectedAsDependency(): void
    {
        $this->visitor->enterNode(new ClassMethod('foo', ['returnType' => new Name('App\\Service\\ExplicitService')]));
        $this->assertSame(['App\\Service\\ExplicitService'], $this->visitor->dependencies());
    }
}
