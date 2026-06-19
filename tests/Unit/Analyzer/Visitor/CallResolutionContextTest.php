<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Analyzer\Visitor;

use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Name\FullyQualified;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\PropertyProperty;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\UnionType;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\Visitor\CallResolutionContext;

final class CallResolutionContextTest extends TestCase
{
    private CallResolutionContext $context;

    protected function setUp(): void
    {
        $this->context = new CallResolutionContext();
    }

    public function testInitialState(): void
    {
        $this->assertNull($this->context->getCurrentClass());
        $this->assertNull($this->context->getCurrentMethod());
        $this->assertNull($this->context->getCurrentFunction());
        $this->assertNull($this->context->getCurrentClassParent());
        $this->assertFalse($this->context->isCurrentClassFinal());
        $this->assertFalse($this->context->isInScope());
        $this->assertSame([], $this->context->getVariableTypes());
    }

    public function testEnterClass(): void
    {
        $class = new Class_('Foo');
        $class->namespacedName = new Name\FullyQualified('App\Foo');
        $this->context->enterClass($class);

        $this->assertSame('App\Foo', $this->context->getCurrentClass());
        $this->assertNull($this->context->getCurrentMethod());
        $this->assertNull($this->context->getCurrentFunction());
    }

    public function testEnterClassWithParentAndFinal(): void
    {
        $class = new Class_(
            'Foo', [
            'flags' => Class_::MODIFIER_FINAL,
            'extends' => new FullyQualified('App\Base'),
            ]
        );
        $class->namespacedName = new Name\FullyQualified('App\Foo');

        $this->context->enterClass($class);
        $this->assertSame('App\Base', $this->context->getCurrentClassParent());
        $this->assertTrue($this->context->isCurrentClassFinal());
    }

    public function testEnterClassAnonymous(): void
    {
        $class = new Class_(null);
        $this->context->enterClass($class);
        $this->assertNull($this->context->getCurrentClass());
    }

    public function testEnterClassWithoutNamespacedName(): void
    {
        $class = new Class_('Foo');
        $class->namespacedName = null;
        $this->context->enterClass($class);
        $this->assertNull($this->context->getCurrentClass());
    }

    public function testTraitDoesNotSetParentAndFinal(): void
    {
        $trait = new Trait_('LoggableTrait');
        $trait->namespacedName = new Name\FullyQualified('App\LoggableTrait');
        $this->context->enterClass($trait);

        $this->assertSame('App\LoggableTrait', $this->context->getCurrentClass());
        $this->assertNull($this->context->getCurrentClassParent());
        $this->assertFalse($this->context->isCurrentClassFinal());
    }

    public function testLeaveClass(): void
    {
        $class = new Class_('Foo');
        $class->namespacedName = new Name\FullyQualified('App\Foo');
        $this->context->enterClass($class);
        $this->context->leaveClass();

        $this->assertNull($this->context->getCurrentClass());
        $this->assertFalse($this->context->isInScope());
    }

    public function testEnterMethod(): void
    {
        $class = new Class_('Foo');
        $class->namespacedName = new Name\FullyQualified('App\Foo');
        $this->context->enterClass($class);

        $method = new ClassMethod('bar');
        $this->context->enterMethod('bar', $method);

        $this->assertSame('bar', $this->context->getCurrentMethod());
        $this->assertNull($this->context->getCurrentFunction());
    }

    public function testEnterMethodPopulatesParams(): void
    {
        $class = new Class_('Foo');
        $class->namespacedName = new Name\FullyQualified('App\Foo');
        $this->context->enterClass($class);

        $param = new Param(new Variable('user'), type: new Name\FullyQualified('App\Entity\User'));
        $method = new ClassMethod('bar', ['params' => [$param]]);
        $this->context->enterMethod('bar', $method);

        $types = $this->context->getVariableTypes();
        $this->assertArrayHasKey('$user', $types);
        $this->assertSame(['App\Entity\User'], $types['$user']);
    }

    public function testEnterMethodMergePropertyAndParamTypes(): void
    {
        $prop = new Property(Class_::MODIFIER_PUBLIC, [new PropertyProperty('name')]);
        $prop->type = new Name\FullyQualified('App\PropType');

        $class = new Class_(
            'Foo', [
            'stmts' => [$prop],
            ]
        );
        $class->namespacedName = new Name\FullyQualified('App\Foo');
        $this->context->enterClass($class);

        $param = new Param(new Variable('user'), type: new Name\FullyQualified('App\Entity\User'));
        $method = new ClassMethod('bar', ['params' => [$param]]);
        $this->context->enterMethod('bar', $method);

        $types = $this->context->getVariableTypes();
        $this->assertArrayHasKey('$user', $types);
        $this->assertArrayHasKey('$name', $types);
    }

    public function testLeaveMethod(): void
    {
        $class = new Class_('Foo');
        $class->namespacedName = new Name\FullyQualified('App\Foo');
        $this->context->enterClass($class);
        $this->context->enterMethod('bar', new ClassMethod('bar'));
        $this->context->leaveMethod();

        $this->assertNull($this->context->getCurrentMethod());
    }

    public function testEnterFunction(): void
    {
        $method = new ClassMethod('foo');
        $this->context->enterFunction('foo', $method);

        $this->assertSame('foo', $this->context->getCurrentFunction());
        $this->assertNull($this->context->getCurrentClass());
        $this->assertNull($this->context->getCurrentMethod());
        $this->assertTrue($this->context->isInScope());
    }

    public function testEnterFunctionClearsClassState(): void
    {
        $class = new Class_('Foo');
        $class->namespacedName = new Name\FullyQualified('App\Foo');
        $this->context->enterClass($class);
        $this->context->enterFunction('fn', new ClassMethod('fn'));

        $this->assertNull($this->context->getCurrentClass());
        $this->assertNull($this->context->getCurrentClassParent());
        $this->assertFalse($this->context->isCurrentClassFinal());
    }

    public function testLeaveFunction(): void
    {
        $this->context->enterFunction('foo', new ClassMethod('foo'));
        $this->context->leaveFunction();
        $this->assertNull($this->context->getCurrentFunction());
        $this->assertFalse($this->context->isInScope());
    }

    public function testIsInScopeFalse(): void
    {
        $this->assertFalse($this->context->isInScope());
    }

    public function testAddAssignmentWithNew(): void
    {
        $class = new Class_('Foo');
        $class->namespacedName = new Name\FullyQualified('App\Foo');
        $this->context->enterClass($class);
        $this->context->enterMethod('bar', new ClassMethod('bar'));

        $assign = new Assign(
            new Variable('obj'),
            new New_(new Name\FullyQualified('App\Entity\User'))
        );
        $this->context->addAssignment($assign);

        $types = $this->context->getVariableTypes();
        $this->assertArrayHasKey('$obj', $types);
        $this->assertSame(['App\Entity\User'], $types['$obj']);
    }

    public function testAddAssignmentWithVariableCopy(): void
    {
        $class = new Class_('Foo');
        $class->namespacedName = new Name\FullyQualified('App\Foo');
        $this->context->enterClass($class);

        $param = new Param(new Variable('src'), type: new Name\FullyQualified('App\Entity\User'));
        $method = new ClassMethod('bar', ['params' => [$param]]);
        $this->context->enterMethod('bar', $method);

        $assign = new Assign(
            new Variable('dst'),
            new Variable('src')
        );
        $this->context->addAssignment($assign);

        $types = $this->context->getVariableTypes();
        $this->assertArrayHasKey('$dst', $types);
        $this->assertSame(['App\Entity\User'], $types['$dst']);
    }

    public function testAddAssignmentNonVariableLhs(): void
    {
        $this->context->addAssignment(
            new Assign(new Int_(1), new New_(new Name('Foo')))
        );
        $this->assertSame([], $this->context->getVariableTypes());
    }

    public function testAddAssignmentNonStringVarName(): void
    {
        $assign = new Assign(new Variable(new Int_(1)), new New_(new Name('Foo')));
        $this->context->addAssignment($assign);
        $this->assertSame([], $this->context->getVariableTypes());
    }

    public function testExtractTypeNamesBuiltin(): void
    {
        $class = new Class_('Foo');
        $class->namespacedName = new Name\FullyQualified('App\Foo');

        $param = new Param(new Variable('x'), type: new Name('int'));
        $method = new ClassMethod('bar', ['params' => [$param]]);
        $this->context->enterClass($class);
        $this->context->enterMethod('bar', $method);

        $types = $this->context->getVariableTypes();
        $this->assertArrayHasKey('$x', $types);
        $this->assertSame([], $types['$x']);
    }

    public function testExtractTypeNamesNullable(): void
    {
        $class = new Class_('Foo');
        $class->namespacedName = new Name\FullyQualified('App\Foo');

        $param = new Param(new Variable('x'), type: new NullableType(new Name\FullyQualified('App\Entity\User')));
        $method = new ClassMethod('bar', ['params' => [$param]]);
        $this->context->enterClass($class);
        $this->context->enterMethod('bar', $method);

        $types = $this->context->getVariableTypes();
        $this->assertSame(['App\Entity\User'], $types['$x']);
    }

    public function testExtractTypeNamesUnion(): void
    {
        $class = new Class_('Foo');
        $class->namespacedName = new Name\FullyQualified('App\Foo');

        $param = new Param(
            new Variable('x'),
            type: new UnionType([new Name\FullyQualified('App\Entity\User'), new Name('int')])
        );
        $method = new ClassMethod('bar', ['params' => [$param]]);
        $this->context->enterClass($class);
        $this->context->enterMethod('bar', $method);

        $types = $this->context->getVariableTypes();
        $this->assertSame(['App\Entity\User'], $types['$x']);
    }

    public function testExtractTypeNamesIntersection(): void
    {
        $class = new Class_('Foo');
        $class->namespacedName = new Name\FullyQualified('App\Foo');

        $param = new Param(
            new Variable('x'),
            type: new IntersectionType([new Name\FullyQualified('App\A'), new Name\FullyQualified('App\B')])
        );
        $method = new ClassMethod('bar', ['params' => [$param]]);
        $this->context->enterClass($class);
        $this->context->enterMethod('bar', $method);

        $types = $this->context->getVariableTypes();
        $this->assertContains('App\A', $types['$x']);
        $this->assertContains('App\B', $types['$x']);
    }

    public function testBuildPropertyTypeMapWithTypedProperties(): void
    {
        $prop = new Property(Class_::MODIFIER_PUBLIC, [new PropertyProperty('user')]);
        $prop->type = new Name\FullyQualified('App\Entity\User');

        $class = new Class_('Foo', ['stmts' => [$prop]]);
        $class->namespacedName = new Name\FullyQualified('App\Foo');
        $this->context->enterClass($class);

        $types = $this->context->getVariableTypes();
        $this->assertArrayHasKey('$user', $types);
        $this->assertSame(['App\Entity\User'], $types['$user']);
    }

    public function testBuildPropertyTypeMapSkipsUntypedProperties(): void
    {
        $prop = new Property(Class_::MODIFIER_PUBLIC, [new PropertyProperty('name')]);

        $class = new Class_('Foo', ['stmts' => [$prop]]);
        $class->namespacedName = new Name\FullyQualified('App\Foo');
        $this->context->enterClass($class);

        $this->assertArrayNotHasKey('$name', $this->context->getVariableTypes());
    }
}
