<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Cli\Show;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Cli\Show\EntityResolver;
use SineFine\Mnemosyne\Graph\Experimental\GraphCommand;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;
use SineFine\Mnemosyne\Graph\Experimental\Schema;

class EntityResolverTest extends TestCase
{
    private PDO $pdo;
    private GraphQuery $query;
    private GraphCommand $command;
    private EntityResolver $resolver;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create($this->pdo);
        $this->command = new GraphCommand($this->pdo);
        $this->query = new GraphQuery($this->pdo);
        $this->resolver = new EntityResolver();
    }

    private function insertEntity(string $fqn): int
    {
        $shortName = substr($fqn, strrpos($fqn, '\\') + 1);
        return $this->command->insertEntity($fqn, $shortName, 'class', null, null, null, []);
    }

    public function testResolveByFullyQualifiedName(): void
    {
        $this->insertEntity('App\\Foo');

        $id = $this->resolver->resolve('App\\Foo', $this->query);
        $this->assertIsInt($id);
        $this->assertGreaterThan(0, $id);
        $this->assertSame('App\\Foo', $this->resolver->lastResolvedFqn());
    }

    public function testResolveByShortName(): void
    {
        $this->insertEntity('App\\Foo');

        $id = $this->resolver->resolve('Foo', $this->query);
        $this->assertIsInt($id);
        $this->assertSame('App\\Foo', $this->resolver->lastResolvedFqn());
    }

    public function testResolveReturnsIdForFullyQualifiedName(): void
    {
        $entityId = $this->insertEntity('App\\Foo');

        $id = $this->resolver->resolve('App\\Foo', $this->query);
        $this->assertSame($entityId, $id);
    }

    public function testResolveReturnsIdForShortName(): void
    {
        $entityId = $this->insertEntity('App\\Foo');

        $id = $this->resolver->resolve('Foo', $this->query);
        $this->assertSame($entityId, $id);
    }

    public function testLastResolvedFqnReturnsEmptyByDefault(): void
    {
        $this->assertSame('', $this->resolver->lastResolvedFqn());
    }
}
