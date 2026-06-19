<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Cli\Show;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Cli\Command;
use SineFine\Ponymator\Cli\Show\ShowImpactCommand;
use SineFine\Ponymator\Graph\Experimental\GraphCommand;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;
use SineFine\Ponymator\Graph\Experimental\Schema;

class ShowImpactCommandTest extends TestCase
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

    private function createCommand(string $entity, ?int $depth = null): Command
    {
        return new Command(
            group: 'show',
            subcommand: 'impact',
            positionalArgs: [],
            configPath: null,
            output: 'md',
            dbPath: null,
            depth: $depth,
            helpRequested: false,
            isDiff: false,
            namedArgs: ['entity' => $entity]
        );
    }

    private function insertEntity(string $fqn, string $type = 'class'): int
    {
        $shortName = substr($fqn, strrpos($fqn, '\\') + 1);
        return $this->command->insertEntity($fqn, $shortName, $type, null, null, null, []);
    }

    public function testNoDependencies(): void
    {
        $this->insertEntity('App\\Foo');

        $cmd = $this->createCommand('App\\Foo');
        $showImpact = new ShowImpactCommand();

        ob_start();
        $showImpact->execute($cmd, $this->query);
        $output = ob_get_clean();

        $this->assertStringContainsString('Impact of changing: App\\Foo', $output);
        $this->assertStringContainsString('Total affected: 0', $output);
        $this->assertStringContainsString('No dependent entitys found.', $output);
    }

    public function testDirectDependency(): void
    {
        $idA = $this->insertEntity('App\\Foo');
        $idB = $this->insertEntity('App\\Bar');
        $this->command->insertRelationship($idB, $idA, null, 'extends', null);

        $cmd = $this->createCommand('App\\Foo');
        $showImpact = new ShowImpactCommand();

        ob_start();
        $showImpact->execute($cmd, $this->query);
        $output = ob_get_clean();

        $this->assertStringContainsString('Impact of changing: App\\Foo', $output);
        $this->assertStringContainsString('Total affected: 1', $output);
        $this->assertStringContainsString('App\\Bar', $output);
        $this->assertStringContainsString('[distance 1]', $output);
    }

    public function testMultipleDependencies(): void
    {
        $idA = $this->insertEntity('App\\Foo');
        $idB = $this->insertEntity('App\\Bar');
        $idC = $this->insertEntity('App\\Baz');
        $this->command->insertRelationship($idB, $idA, null, 'extends', null);
        $this->command->insertRelationship($idC, $idA, null, 'implements', null);

        $cmd = $this->createCommand('App\\Foo');
        $showImpact = new ShowImpactCommand();

        ob_start();
        $showImpact->execute($cmd, $this->query);
        $output = ob_get_clean();

        $this->assertStringContainsString('Total affected: 2', $output);
        $this->assertStringContainsString('App\\Bar', $output);
        $this->assertStringContainsString('App\\Baz', $output);
    }

    public function testTransitiveDependency(): void
    {
        $idA = $this->insertEntity('App\\A');
        $idB = $this->insertEntity('App\\B');
        $idC = $this->insertEntity('App\\C');
        $this->command->insertRelationship($idB, $idA, null, 'extends', null);
        $this->command->insertRelationship($idC, $idB, null, 'extends', null);

        $cmd = $this->createCommand('App\\A');
        $showImpact = new ShowImpactCommand();

        ob_start();
        $showImpact->execute($cmd, $this->query);
        $output = ob_get_clean();

        $this->assertStringContainsString('Total affected: 2', $output);
        $this->assertStringContainsString('App\\B', $output);
        $this->assertStringContainsString('App\\C', $output);
        $this->assertStringContainsString('[distance 1]', $output);
        $this->assertStringContainsString('[distance 2]', $output);
    }

    public function testDepthLimit(): void
    {
        $idA = $this->insertEntity('App\\A');
        $idB = $this->insertEntity('App\\B');
        $idC = $this->insertEntity('App\\C');
        $this->command->insertRelationship($idB, $idA, null, 'extends', null);
        $this->command->insertRelationship($idC, $idB, null, 'extends', null);

        $cmd = $this->createCommand('App\\A', 1);
        $showImpact = new ShowImpactCommand();

        ob_start();
        $showImpact->execute($cmd, $this->query);
        $output = ob_get_clean();

        $this->assertStringContainsString('Total affected: 1', $output);
        $this->assertStringContainsString('App\\B', $output);
        $this->assertStringNotContainsString('App\\C', $output);
    }

    public function testGroupedByType(): void
    {
        $idA = $this->insertEntity('App\\A');
        $idB = $this->insertEntity('App\\B', 'class');
        $idC = $this->insertEntity('App\\C', 'interface');
        $this->command->insertRelationship($idB, $idA, null, 'extends', null);
        $this->command->insertRelationship($idC, $idA, null, 'implements', null);

        $cmd = $this->createCommand('App\\A');
        $showImpact = new ShowImpactCommand();

        ob_start();
        $showImpact->execute($cmd, $this->query);
        $output = ob_get_clean();

        $this->assertStringContainsString('Classes (1):', $output);
        $this->assertStringContainsString('Interfaces (1):', $output);
    }

    public function testSortedByDistance(): void
    {
        $idA = $this->insertEntity('App\\A');
        $idB = $this->insertEntity('App\\B');
        $idC = $this->insertEntity('App\\C');
        $idD = $this->insertEntity('App\\D');
        $this->command->insertRelationship($idB, $idA, null, 'extends', null);
        $this->command->insertRelationship($idC, $idB, null, 'extends', null);
        $this->command->insertRelationship($idD, $idC, null, 'extends', null);

        $cmd = $this->createCommand('App\\A');
        $showImpact = new ShowImpactCommand();

        ob_start();
        $showImpact->execute($cmd, $this->query);
        $output = ob_get_clean();

        $posB = strpos($output, 'App\\B');
        $posC = strpos($output, 'App\\C');
        $posD = strpos($output, 'App\\D');
        
        $this->assertLessThan($posC, $posB);
        $this->assertLessThan($posD, $posC);
    }
}
