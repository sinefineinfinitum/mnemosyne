<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Analyzer\Visitor;

use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Throw_ as ExprThrow;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\Visitor\ObjectCreationCollectingVisitor;

final class ObjectCreationCollectingVisitorTest extends TestCase
{
    private ObjectCreationCollectingVisitor $visitor;

    protected function setUp(): void
    {
        $this->visitor = new ObjectCreationCollectingVisitor();
    }

    public function testNewInsideMethodCollected(): void
    {
        $class = new Class_('MyClass');
        $class->namespacedName = new Name('App\\MyClass');
        $method = new ClassMethod('doSomething');
        $new = new New_(new Name('App\\Service\\Product'));

        $this->visitor->enterNode($class);
        $this->visitor->enterNode($method);
        $this->visitor->enterNode($new);

        $creates = $this->visitor->getCreates('App\\MyClass');
        $this->assertSame(['doSomething' => ['App\\Service\\Product']], $creates);
    }

    public function testExprThrowNewNotCollected(): void
    {
        $class = new Class_('MyClass');
        $class->namespacedName = new Name('App\\MyClass');
        $method = new ClassMethod('doSomething');
        $throw = new ExprThrow(new New_(new Name('RuntimeException')));
        $new = $throw->expr;

        $this->visitor->enterNode($class);
        $this->visitor->enterNode($method);
        $this->visitor->enterNode($throw);
        $this->visitor->enterNode($new);
        $this->visitor->leaveNode($new);
        $this->visitor->leaveNode($throw);

        $creates = $this->visitor->getCreates('App\\MyClass');
        $this->assertSame([], $creates);
    }

    public function testMixedThrowAndRegularNew(): void
    {
        $class = new Class_('MyClass');
        $class->namespacedName = new Name('App\\MyClass');
        $method = new ClassMethod('doSomething');

        $regularNew = new New_(new Name('App\\Service\\Product'));
        $throw = new ExprThrow(new New_(new Name('InvalidArgumentException')));
        $throwExpr = $throw->expr;

        $this->visitor->enterNode($class);
        $this->visitor->enterNode($method);
        $this->visitor->enterNode($regularNew);
        $this->visitor->leaveNode($regularNew);
        $this->visitor->enterNode($throw);
        $this->visitor->enterNode($throwExpr);
        $this->visitor->leaveNode($throwExpr);
        $this->visitor->leaveNode($throw);

        $creates = $this->visitor->getCreates('App\\MyClass');
        $this->assertSame(['doSomething' => ['App\\Service\\Product']], $creates);
    }
}
