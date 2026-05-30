<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Cli\ArgumentParser;

final class ArgumentParserTest extends TestCase
{
    public function testDefaultModeIsDiff(): void
    {
        $parser = ArgumentParser::parse(['ponimator']);
        $this->assertSame(ArgumentParser::DIFF, $parser->mode);
    }

    public function testFullFlag(): void
    {
        $parser = ArgumentParser::parse(['ponimator', '--full']);
        $this->assertSame(ArgumentParser::FULL, $parser->mode);
    }

    public function testDiffFlag(): void
    {
        $parser = ArgumentParser::parse(['ponimator', '--diff']);
        $this->assertSame(ArgumentParser::DIFF, $parser->mode);
    }

    public function testCheckFlag(): void
    {
        $parser = ArgumentParser::parse(['ponimator', '--check']);
        $this->assertSame(ArgumentParser::CHECK, $parser->mode);
    }

    public function testLastFlagWins(): void
    {
        $parser = ArgumentParser::parse(['ponimator', '--full', '--check', '--diff']);
        $this->assertSame(ArgumentParser::DIFF, $parser->mode);
    }

    public function testConfigPath(): void
    {
        $parser = ArgumentParser::parse(['ponimator', '--config=myconfig.json']);
        $this->assertSame('myconfig.json', $parser->configPath);
    }

    public function testConfigPathWithFull(): void
    {
        $parser = ArgumentParser::parse(['ponimator', '--full', '--config=custom.json']);
        $this->assertSame(ArgumentParser::FULL, $parser->mode);
        $this->assertSame('custom.json', $parser->configPath);
    }

    public function testHelpFlag(): void
    {
        $parser = ArgumentParser::parse(['ponimator', '--help']);
        $this->assertTrue($parser->helpRequested);
    }

    public function testHelpWithMode(): void
    {
        $parser = ArgumentParser::parse(['ponimator', '--check', '--help']);
        $this->assertSame(ArgumentParser::CHECK, $parser->mode);
        $this->assertTrue($parser->helpRequested);
    }

    public function testPrintHelpOutput(): void
    {
        $this->expectOutputRegex('/Usage: ponimator/');
        ArgumentParser::printHelp();
    }
}
