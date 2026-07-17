<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Cli\Detect;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Cli\Command;
use SineFine\Mnemosyne\Cli\Detect\DetectCommand;
use SineFine\Mnemosyne\Graph\Experimental\GraphCommand;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;
use SineFine\Mnemosyne\Graph\Experimental\Schema;

final class DetectCommandTest extends TestCase
{
    private PDO $pdo;
    private GraphQuery $query;
    private GraphCommand $command;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create($this->pdo);
        $this->command = new GraphCommand($this->pdo);
        $this->query = new GraphQuery($this->pdo);
    }

    public function testRenderTableWithResults(): void
    {
        $targetId = $this->command->insertEntity(
            'App\\TargetInterface', 'TargetInterface', 'interface', null, null, null, [],
        );
        $adapteeId = $this->command->insertEntity(
            'App\\LegacyService', 'LegacyService', 'class', null, null, null, [],
        );
        $adapterId = $this->command->insertEntity(
            'App\\ConcreteAdapter', 'ConcreteAdapter', 'class', null, null, null, [],
        );
        $this->command->insertRelationship($adapterId, $targetId, null, 'implements', null);
        $this->command->insertRelationship($adapterId, $adapteeId, null, 'creates', null);

        $cmd = new Command(
            group: 'detect',
            subcommand: null,
            positionalArgs: [],
            configPath: null,
            output: 'md',
            dbPath: null,
            depth: null,
            helpRequested: false,
        );

        $detectCommand = new DetectCommand();

        ob_start();
        $detectCommand->execute($cmd, $this->query);
        $output = ob_get_clean();

        $this->assertStringContainsString('Pattern', $output);
        $this->assertStringContainsString('Role', $output);
        $this->assertStringContainsString('Class', $output);
        $this->assertStringContainsString('adapter', $output);
    }

    public function testRenderTableNoMatches(): void
    {
        $cmd = new Command(
            group: 'detect',
            subcommand: null,
            positionalArgs: [],
            configPath: null,
            output: 'md',
            dbPath: null,
            depth: null,
            helpRequested: false,
        );

        $detectCommand = new DetectCommand();

        ob_start();
        $detectCommand->execute($cmd, $this->query);
        $output = ob_get_clean();

        $this->assertStringContainsString('No pattern matches found.', $output);
    }

}
