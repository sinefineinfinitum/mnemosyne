<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Detector\Pattern\Pipeline;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Detector\Pattern\Model\PatternMatch;
use SineFine\Ponymator\Detector\Pattern\Model\PatternParticipant;
use SineFine\Ponymator\Detector\Pattern\Model\PatternResult;
use SineFine\Ponymator\Tests\Unit\Detector\Pattern\Stub\PatternInterfaceStub;

final class EntitiesTest extends TestCase
{
    public function testPatternParticipant(): void
    {
        $p = new PatternParticipant(role: 'adapter', entityId: 42);

        $this->assertSame('adapter', $p->role);
        $this->assertSame(42, $p->entityId);
    }

    public function testPatternMatch(): void
    {
        $def = new PatternInterfaceStub('test', ['a']);
        $participants = [new PatternParticipant(role: 'a', entityId: 1)];
        $match = new PatternMatch(
            pattern: $def,
            participants: $participants,
        );

        $this->assertSame($def, $match->pattern);
        $this->assertSame($participants, $match->participants);
    }

    public function testPatternResult(): void
    {
        $matches = [
            new PatternMatch(
                pattern: new PatternInterfaceStub('a', ['x']),
                participants: [],
            ),
        ];
        $result = new PatternResult(matches: $matches);

        $this->assertSame($matches, $result->matches);
    }
}
