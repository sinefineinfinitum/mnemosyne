<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Cli\Command;

final class CommandTest extends TestCase
{
    public function testConstruction(): void
    {
        $cmd = new Command(
            'show',
            'symbol',
            ['ArgumentParser'],
            null,
            'md',
            'graph.db',
            1,
            false,
            false,
        );

        $this->assertSame('show', $cmd->group);
        $this->assertSame('symbol', $cmd->subcommand);
        $this->assertSame(['ArgumentParser'], $cmd->positionalArgs);
        $this->assertSame([], $cmd->namedArgs);
        $this->assertNull($cmd->configPath);
        $this->assertSame('md', $cmd->output);
        $this->assertSame('graph.db', $cmd->dbPath);
        $this->assertSame(1, $cmd->depth);
        $this->assertFalse($cmd->helpRequested);
        $this->assertFalse($cmd->isDiff);
    }

    public function testDefaults(): void
    {
        $cmd = new Command('generate', null, [], null, 'md', null, null, false, true);

        $this->assertSame('generate', $cmd->group);
        $this->assertNull($cmd->subcommand);
        $this->assertSame([], $cmd->positionalArgs);
        $this->assertNull($cmd->dbPath);
        $this->assertNull($cmd->depth);
        $this->assertFalse($cmd->helpRequested);
        $this->assertTrue($cmd->isDiff);
    }
}
