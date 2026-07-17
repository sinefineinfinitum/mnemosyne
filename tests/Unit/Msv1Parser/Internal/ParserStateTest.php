<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Msv1Parser\Internal;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Msv1Parser\Ast\EntityNode;
use SineFine\Mnemosyne\Msv1Parser\Ast\MemberNode;
use SineFine\Mnemosyne\Msv1Parser\Internal\ParserState;

final class ParserStateTest extends TestCase
{
    public function testInitialStateHasNoEntityAndNoMethod(): void
    {
        $state = new ParserState();

        $this->assertNull($state->entity());
        $this->assertNull($state->method());
    }

    public function testOpenEntitySetsEntity(): void
    {
        $state = new ParserState();
        $entity = new EntityNode('class', 'Foo');

        $state->openEntity($entity);

        $this->assertSame($entity, $state->entity());
    }

    public function testOpenEntityClosesActiveMethod(): void
    {
        $state = new ParserState();
        $entity = new EntityNode('class', 'Foo');
        $method = new MemberNode('m', 'method', $entity);

        $state->openEntity($entity);
        $state->openMethod($method);
        $this->assertSame($method, $state->method());

        $state->openEntity(new EntityNode('class', 'Bar'));
        $this->assertNull($state->method());
    }

    public function testOpenMethodSetsMethod(): void
    {
        $state = new ParserState();
        $entity = new EntityNode('class', 'Foo');
        $method = new MemberNode('m', 'method', $entity);

        $state->openEntity($entity);
        $state->openMethod($method);

        $this->assertSame($method, $state->method());
    }

    public function testOpenMethodThrowsWithoutActiveEntity(): void
    {
        $state = new ParserState();
        $method = new MemberNode('m', 'method', new EntityNode('class', 'Foo'));

        $this->expectException(\LogicException::class);
        $this->expectExceptionMessage('Cannot open method without an active entity');
        $state->openMethod($method);
    }

    public function testCloseMethodResetsMethodOnly(): void
    {
        $state = new ParserState();
        $entity = new EntityNode('class', 'Foo');
        $method = new MemberNode('m', 'method', $entity);

        $state->openEntity($entity);
        $state->openMethod($method);
        $state->closeMethod();

        $this->assertNull($state->method());
        $this->assertSame($entity, $state->entity());
    }

    public function testCloseMethodIsIdempotent(): void
    {
        $state = new ParserState();
        $state->closeMethod();
        $state->closeMethod();

        $this->assertNull($state->method());
    }

    public function testEntityCanBeReplacedWithoutExplicitClose(): void
    {
        $state = new ParserState();
        $a = new EntityNode('class', 'A');
        $b = new EntityNode('class', 'B');

        $state->openEntity($a);
        $state->openEntity($b);

        $this->assertSame($b, $state->entity());
    }
}
