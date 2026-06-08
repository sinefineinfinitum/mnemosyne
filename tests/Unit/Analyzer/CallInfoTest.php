<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\CallInfo;
use SineFine\Ponymator\Analyzer\ParserException;

final class CallInfoTest extends TestCase
{
    public function testDefaultsAreWeakAssociation(): void
    {
        $call = new CallInfo(CallInfo::KIND_DYNAMIC, 'doSomething');
        $this->assertSame(CallInfo::WEAK, $call->association);
        $this->assertNull($call->resolvedTargetFqcn);
        $this->assertSame([], $call->candidateTypes);
        $this->assertFalse($call->isResolved());
    }

    public function testWithAssociationReturnsNewInstance(): void
    {
        $call = new CallInfo(CallInfo::KIND_DYNAMIC, 'doSomething');
        $resolved = $call->withAssociation(CallInfo::STRONG, 'App\\Service\\X');

        $this->assertNotSame($call, $resolved);
        $this->assertSame(CallInfo::WEAK, $call->association);
        $this->assertSame(CallInfo::STRONG, $resolved->association);
        $this->assertSame('App\\Service\\X', $resolved->resolvedTargetFqcn);
        $this->assertTrue($resolved->isResolved());
    }

    public function testWithAssociationPreservesCandidates(): void
    {
        $call = new CallInfo(CallInfo::KIND_DYNAMIC, 'doSomething', ['App\\A', 'App\\B']);
        $resolved = $call->withAssociation(CallInfo::WEAK);

        $this->assertSame(['App\\A', 'App\\B'], $resolved->candidateTypes);
    }

    public function testWithAssociationOverridesCandidates(): void
    {
        $call = new CallInfo(CallInfo::KIND_DYNAMIC, 'doSomething', ['App\\A', 'App\\B']);
        $resolved = $call->withAssociation(CallInfo::STRONG, 'App\\A', ['App\\A']);

        $this->assertSame(['App\\A'], $resolved->candidateTypes);
    }

    public function testInvalidKindThrows(): void
    {
        $this->expectException(ParserException::class);
        new CallInfo('unknown', 'doSomething');
    }

    public function testInvalidAssociationThrows(): void
    {
        $this->expectException(ParserException::class);
        new CallInfo(CallInfo::KIND_STATIC, 'doSomething', association: '#');
    }

    public function testEmptyTargetNameThrows(): void
    {
        $this->expectException(ParserException::class);
        new CallInfo(CallInfo::KIND_STATIC, '');
    }

    public function testSortKeyIsKind(): void
    {
        $call = new CallInfo(CallInfo::KIND_STATIC, 'foo');
        $this->assertSame('static', $call->sortKey());
    }

    public function testAllKindsAccepted(): void
    {
        foreach (CallInfo::KINDS as $kind) {
            $call = new CallInfo($kind, 'target');
            $this->assertSame($kind, $call->kind);
        }
    }
}
