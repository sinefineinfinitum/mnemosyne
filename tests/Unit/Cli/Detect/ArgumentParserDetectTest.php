<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Cli\Detect;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Cli\ArgumentParser;

final class ArgumentParserDetectTest extends TestCase
{
    public function testParsesDetectCommand(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'detect']);

        $this->assertSame('detect', $cmd->group);
        $this->assertNull($cmd->subcommand);
        $this->assertTrue($cmd->helpRequested);
    }

    public function testParsesDetectWithDbPath(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'detect', '--db-path=/tmp/test.db']);

        $this->assertSame('detect', $cmd->group);
        $this->assertSame('/tmp/test.db', $cmd->dbPath);
    }

    public function testParsesDetectWithConfigPath(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'detect', '--config=/path/to/config.json']);

        $this->assertSame('detect', $cmd->group);
        $this->assertSame('/path/to/config.json', $cmd->configPath);
    }

    public function testParsesDetectHelp(): void
    {
        $cmd = ArgumentParser::parse(['ponymator', 'detect', '--help']);

        $this->assertTrue($cmd->helpRequested);
    }

}
