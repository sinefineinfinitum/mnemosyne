<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Cli\Show;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Cli\Command;
use SineFine\Ponymator\Cli\Show\ShowPathCommand;
use SineFine\Ponymator\Graph\Experimental\GraphCommand;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;
use SineFine\Ponymator\Graph\Experimental\Schema;

class ShowPathCommandTest extends TestCase
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

    private function createCommand(string $from, string $to, ?int $depth = null): Command
    {
        return new Command(
            group: 'show',
            subcommand: 'path',
            positionalArgs: [],
            configPath: null,
            output: 'md',
            dbPath: null,
            depth: $depth,
            helpRequested: false,
            isDiff: false,
            namedArgs: ['from' => $from, 'to' => $to]
        );
    }

    private function insertEntity(string $fqn): int
    {
        $shortName = substr($fqn, strrpos($fqn, '\\') + 1);
        return $this->command->insertEntity($fqn, $shortName, 'class', null, null, null, []);
    }

    public function testSameSourceAndTarget(): void
    {
        $this->insertEntity('App\\Foo');

        $cmd = $this->createCommand('App\\Foo', 'App\\Foo');
        $showPath = new ShowPathCommand();

        ob_start();
        $showPath->execute($cmd, $this->query);
        $output = ob_get_clean();

        $this->assertSame("Path from App\\Foo to App\\Foo:\n\n  App\\Foo\n\n(0 hops)\n", $output);
    }

    public function testDirectPathForward(): void
    {
        $idA = $this->insertEntity('App\\Foo');
        $idB = $this->insertEntity('App\\Bar');
        $this->command->insertRelationship($idA, $idB, null, 'extends', null);

        $cmd = $this->createCommand('App\\Foo', 'App\\Bar');
        $showPath = new ShowPathCommand();

        ob_start();
        $showPath->execute($cmd, $this->query);
        $output = ob_get_clean();

        $this->assertSame("Path from App\\Foo to App\\Bar:\n\n  App\\Foo --[extends]--> App\\Bar\n\n(1 hops)\n", $output);
    }

    public function testDirectPathReverse(): void
    {
        $idA = $this->insertEntity('App\\Foo');
        $idB = $this->insertEntity('App\\Bar');
        $this->command->insertRelationship($idB, $idA, null, 'implements', null);

        $cmd = $this->createCommand('App\\Foo', 'App\\Bar');
        $showPath = new ShowPathCommand();

        ob_start();
        $showPath->execute($cmd, $this->query);
        $output = ob_get_clean();

        $this->assertSame("Path from App\\Foo to App\\Bar:\n\n  App\\Foo <--[implements]-- App\\Bar\n\n(1 hops)\n", $output);
    }

    public function testNoPathFound(): void
    {
        $this->insertEntity('App\\Foo');
        $this->insertEntity('App\\Bar');

        $cmd = $this->createCommand('App\\Foo', 'App\\Bar');
        $showPath = new ShowPathCommand();

        ob_start();
        $showPath->execute($cmd, $this->query);
        $output = ob_get_clean();

        $this->assertSame("No path found from App\\Foo to App\\Bar.\n", $output);
    }

    public function testMultiHopPath(): void
    {
        $idA = $this->insertEntity('App\\A');
        $idB = $this->insertEntity('App\\B');
        $idC = $this->insertEntity('App\\C');
        $this->command->insertRelationship($idA, $idB, null, 'extends', null);
        $this->command->insertRelationship($idB, $idC, null, 'implements', null);

        $cmd = $this->createCommand('App\\A', 'App\\C');
        $showPath = new ShowPathCommand();

        ob_start();
        $showPath->execute($cmd, $this->query);
        $output = ob_get_clean();

        $this->assertSame("Path from App\\A to App\\C:\n\n  App\\A --[extends]--> App\\B --[implements]--> App\\C\n\n(2 hops)\n", $output);
    }

    public function testDepthLimit(): void
    {
        $idA = $this->insertEntity('App\\A');
        $idB = $this->insertEntity('App\\B');
        $idC = $this->insertEntity('App\\C');
        $this->command->insertRelationship($idA, $idB, null, 'extends', null);
        $this->command->insertRelationship($idB, $idC, null, 'implements', null);

        $cmd = $this->createCommand('App\\A', 'App\\C', 1);
        $showPath = new ShowPathCommand();

        ob_start();
        $showPath->execute($cmd, $this->query);
        $output = ob_get_clean();

        $this->assertSame("No path found from App\\A to App\\C.\n", $output);
    }
}
