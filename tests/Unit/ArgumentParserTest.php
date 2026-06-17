<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Cli\ArgumentParser;

use SineFine\Ponymator\Cli\HelpPrinter;

final class ArgumentParserTest extends TestCase
{
    public function testNoArgsReturnsHelpRequested(): void
    {
        $cmd = ArgumentParser::parse(['ponymator']);
        $this->assertTrue($cmd->helpRequested);
        $this->assertSame('', $cmd->group);
    }

    public function testHelpFlagReturnsHelpRequested(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', '--help']);
        $this->assertTrue($cmd->helpRequested);
    }

    public function testGenerateDefaultIsDiff(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'generate']);
        $this->assertSame('generate', $cmd->group);
        $this->assertNull($cmd->subcommand);
    }

    public function testGenerateFull(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'generate', '--full']);
        $this->assertSame('generate', $cmd->group);
    }

    public function testGenerateDiff(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'generate', '--diff']);
        $this->assertSame('generate', $cmd->group);
    }

    public function testGenerateWithConfig(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'generate', '--full', '--config=custom.json']);
        $this->assertSame('generate', $cmd->group);
        $this->assertSame('custom.json', $cmd->configPath);
    }

    public function testGenerateWithOutput(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'generate', '--output=psv1']);
        $this->assertSame('psv1', $cmd->output);
    }

    public function testGenerateHelp(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'generate', '--help']);
        $this->assertTrue($cmd->helpRequested);
        $this->assertSame('generate', $cmd->group);
    }

    public function testGraphImport(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'graph', 'import', '--db-path=test.db']);
        $this->assertSame('graph', $cmd->group);
        $this->assertSame('import', $cmd->subcommand);
        $this->assertSame('test.db', $cmd->dbPath);
    }

    public function testGraphClear(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'graph', 'clear', '--db-path=graph.db']);
        $this->assertSame('graph', $cmd->group);
        $this->assertSame('clear', $cmd->subcommand);
        $this->assertSame('graph.db', $cmd->dbPath);
    }

    public function testGraphClearNoDbPath(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'graph', 'clear']);
        $this->assertSame('graph', $cmd->group);
        $this->assertSame('clear', $cmd->subcommand);
        $this->assertNull($cmd->dbPath);
    }

    public function testGraphHelp(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'graph', '--help']);
        $this->assertTrue($cmd->helpRequested);
        $this->assertSame('graph', $cmd->group);
    }

    public function testGraphNoSubcommandReturnsHelp(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'graph']);
        $this->assertTrue($cmd->helpRequested);
        $this->assertSame('graph', $cmd->group);
    }

    public function testShowEntity(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'show', 'entity', 'ArgumentParser', '--db-path=graph.db']);
        $this->assertSame('show', $cmd->group);
        $this->assertSame('entity', $cmd->subcommand);
        $this->assertSame(['ArgumentParser'], $cmd->positionalArgs);
        $this->assertSame(['entity' => 'ArgumentParser'], $cmd->namedArgs);
        $this->assertSame('graph.db', $cmd->dbPath);
    }

    public function testShowImpact(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'show', 'impact', 'ArgumentParser', '--depth=2']);
        $this->assertSame('show', $cmd->group);
        $this->assertSame('impact', $cmd->subcommand);
        $this->assertSame(['ArgumentParser'], $cmd->positionalArgs);
        $this->assertSame(['entity' => 'ArgumentParser'], $cmd->namedArgs);
        $this->assertSame(2, $cmd->depth);
    }

    public function testShowPath(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'show', 'path', 'ClassA', 'ClassB', '--depth=5']);
        $this->assertSame('show', $cmd->group);
        $this->assertSame('path', $cmd->subcommand);
        $this->assertSame(['ClassA', 'ClassB'], $cmd->positionalArgs);
        $this->assertSame(['from' => 'ClassA', 'to' => 'ClassB'], $cmd->namedArgs);
        $this->assertSame(5, $cmd->depth);
    }

    public function testShowHelp(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'show', '--help']);
        $this->assertTrue($cmd->helpRequested);
        $this->assertSame('show', $cmd->group);
    }

    public function testShowNoSubcommandReturnsHelp(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'show']);
        $this->assertTrue($cmd->helpRequested);
        $this->assertSame('show', $cmd->group);
    }

    public function testShowEntityDefaultDepthIsNull(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'show', 'entity', 'Foo']);
        $this->assertNull($cmd->depth);
    }

    public function testPrintHelpOutput(): void
    {
        $this->expectOutputRegex('/Usage: ponymator/');
        HelpPrinter::printHelp();
    }

    public function testPrintGenerateHelpOutput(): void
    {
        $this->expectOutputRegex('/generate/');
        HelpPrinter::printGenerateHelp();
    }

    public function testPrintGraphHelpOutput(): void
    {
        $this->expectOutputRegex('/graph/');
        HelpPrinter::printGraphHelp();
    }

    public function testPrintShowHelpOutput(): void
    {
        $this->expectOutputRegex('/show/');
        HelpPrinter::printShowHelp();
    }
}
