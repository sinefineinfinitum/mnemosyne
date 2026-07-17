<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Pipeline;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Adapter;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Builder;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Decorator;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\FactoryMethod;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Singleton;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\Strategy;
use SineFine\Mnemosyne\Detector\Pattern\Catalog\TemplateMethod;
use SineFine\Mnemosyne\Detector\Pattern\Engine\PatternRegistry;
use SineFine\Mnemosyne\Detector\Pattern\Engine\Engine;
use SineFine\Mnemosyne\Graph\Experimental\GraphCommand;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;
use SineFine\Mnemosyne\Graph\Experimental\Schema;

final class PipelineOrchestratorTest extends TestCase
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

    public function testRunWithAdapterPattern(): void
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

        $registry = new PatternRegistry([new Adapter()]);
        $orchestrator = new Engine($registry, $this->pdo);
        $result = $orchestrator->run();

        $this->assertGreaterThanOrEqual(1, count($result->matches));
        $this->assertSame('adapter', $result->matches[0]->pattern->name());
    }

    public function testRunWithStrategyPattern(): void
    {
        $strategyId = $this->command->insertEntity(
            'App\\StrategyInterface', 'StrategyInterface', 'interface', null, null, null, [],
        );
        $impl1Id = $this->command->insertEntity(
            'App\\ConcreteStrategy1', 'ConcreteStrategy1', 'class', null, null, null, [],
        );
        $impl2Id = $this->command->insertEntity(
            'App\\ConcreteStrategy2', 'ConcreteStrategy2', 'class', null, null, null, [],
        );
        $contextId = $this->command->insertEntity(
            'App\\Client', 'Client', 'class', null, null, null, [],
        );
        $this->command->insertRelationship($impl1Id, $strategyId, null, 'implements', null);
        $this->command->insertRelationship($impl2Id, $strategyId, null, 'implements', null);
        $this->command->insertRelationship($contextId, $strategyId, null, 'creates', null);
        $strategyPropertyId = $this->command->insertProperty(
            entityId: $contextId, name: 'strategy', memberType: 'property',
            visibility: 'private', isStatic: false, isReadonly: false,
            declaredTypeEntityId: $strategyId, declaredTypeName: 'App\\StrategyInterface', defaultValue: null,
        );

        $registry = new PatternRegistry([new Strategy()]);
        $orchestrator = new Engine($registry, $this->pdo);
        $result = $orchestrator->run();

        $this->assertGreaterThanOrEqual(1, count($result->matches));
        $this->assertSame('strategy', $result->matches[0]->pattern->name());
    }

    public function testRunWithEmptyDatabase(): void
    {
        $registry = new PatternRegistry([new Adapter(), new Strategy()]);
        $orchestrator = new Engine($registry, $this->pdo);
        $result = $orchestrator->run();

        $this->assertCount(0, $result->matches);
    }

    public function testRunPersistsMatches(): void
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

        $registry = new PatternRegistry([new Adapter()]);
        $orchestrator = new Engine($registry, $this->pdo);
        $orchestrator->run();

        $stored = $this->pdo->query('SELECT * FROM pattern_matches')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $stored);

        $participants = $this->pdo->query('SELECT * FROM pattern_participants')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(3, $participants);
    }

    public function testRunWithFactoryMethodPattern(): void
    {
        $productId = $this->command->insertEntity(
            'App\\ProductInterface', 'ProductInterface', 'interface', null, null, null, [],
        );
        $creatorId = $this->command->insertEntity(
            'App\\AbstractFactory', 'AbstractFactory', 'class', null, null, null, ['abstract'],
        );
        $methodId = $this->command->insertMethod(
            entityId: $creatorId,
            name: 'create',
            visibility: 'public',
            isStatic: false,
            isAbstract: true,
            isFinal: false,
            returnTypeEntityId: null,
            returnTypeName: 'App\\ProductInterface',
        );

        $childId = $this->command->insertEntity(
            'App\\ConcreteFactory', 'ConcreteFactory', 'class', null, null, null, [],
        );
        $this->command->insertRelationship($childId, $creatorId, null, 'extends', null);
        $this->command->insertRelationship($childId, $productId, null, 'creates', null);

        $registry = new PatternRegistry([new FactoryMethod()]);
        $orchestrator = new Engine($registry, $this->pdo);
        $result = $orchestrator->run();

        $this->assertGreaterThanOrEqual(1, count($result->matches));
        $this->assertSame('factory_method', $result->matches[0]->pattern->name());
    }

    public function testRunWithDecoratorPattern(): void
    {
        $componentId = $this->command->insertEntity(
            'App\\ComponentInterface', 'ComponentInterface', 'interface', null, null, null, [],
        );
        $decoratorId = $this->command->insertEntity(
            'App\\AbstractDecorator', 'AbstractDecorator', 'class', null, null, null, ['abstract'],
        );
        $this->command->insertRelationship($decoratorId, $componentId, null, 'implements', null);
        $this->command->insertRelationship($decoratorId, $componentId, null, 'creates', null);
        $componentPropertyId = $this->command->insertProperty(
            entityId: $decoratorId, name: 'component', memberType: 'property',
            visibility: 'private', isStatic: false, isReadonly: false,
            declaredTypeEntityId: $componentId, declaredTypeName: 'App\\ComponentInterface', defaultValue: null,
        );

        $registry = new PatternRegistry([new Decorator()]);
        $orchestrator = new Engine($registry, $this->pdo);
        $result = $orchestrator->run();

        $this->assertGreaterThanOrEqual(1, count($result->matches));
        $this->assertSame('decorator', $result->matches[0]->pattern->name());
    }

    public function testRunWithBuilderPattern(): void
    {
        $builderId = $this->command->insertEntity(
            'App\\BuilderInterface', 'BuilderInterface', 'interface', null, null, null, [],
        );
        $concreteId = $this->command->insertEntity(
            'App\\ConcreteBuilder', 'ConcreteBuilder', 'class', null, null, null, [],
        );
        $directorId = $this->command->insertEntity(
            'App\\Director', 'Director', 'class', null, null, null, [],
        );
        $this->command->insertRelationship($concreteId, $builderId, null, 'implements', null);
        $this->command->insertRelationship($directorId, $builderId, null, 'creates', null);

        $registry = new PatternRegistry([new Builder()]);
        $orchestrator = new Engine($registry, $this->pdo);
        $result = $orchestrator->run();

        $this->assertGreaterThanOrEqual(1, count($result->matches));
        $this->assertSame('builder', $result->matches[0]->pattern->name());
    }

    public function testRunWithSingletonPattern(): void
    {
        $entityId = $this->command->insertEntity(
            'App\\MySingleton', 'MySingleton', 'class', null, null, null, [],
        );
        $this->command->insertMethod(
            entityId: $entityId, name: '__construct',
            visibility: 'private', isStatic: false, isAbstract: false, isFinal: false,
            returnTypeEntityId: null, returnTypeName: null,
        );
        $this->command->insertProperty(
            entityId: $entityId, name: 'instance', memberType: 'property',
            visibility: 'private', isStatic: true, isReadonly: false,
            declaredTypeEntityId: null, declaredTypeName: 'self', defaultValue: null,
        );
        $this->command->insertMethod(
            entityId: $entityId, name: 'getInstance',
            visibility: 'public', isStatic: true, isAbstract: false, isFinal: false,
            returnTypeEntityId: null, returnTypeName: 'self',
        );

        $registry = new PatternRegistry([new Singleton()]);
        $orchestrator = new Engine($registry, $this->pdo);
        $result = $orchestrator->run();

        $this->assertGreaterThanOrEqual(1, count($result->matches));
        $this->assertSame('singleton', $result->matches[0]->pattern->name());
    }

    public function testRunWithTemplateMethodPattern(): void
    {
        $entityId = $this->command->insertEntity(
            'App\\AbstractProcessor', 'AbstractProcessor', 'class', null, null, null, ['abstract'],
        );
        $processMethodId = $this->command->insertMethod(
            entityId: $entityId, name: 'process',
            visibility: 'public', isStatic: false, isAbstract: false, isFinal: false,
            returnTypeEntityId: null, returnTypeName: 'void',
        );
        $this->command->insertMethod(
            entityId: $entityId, name: 'doStep',
            visibility: 'protected', isStatic: false, isAbstract: true, isFinal: false,
            returnTypeEntityId: null, returnTypeName: 'void',
        );
        $this->command->insertRelationship($entityId, null, null, 'call_dynamic_weak', $processMethodId);
        $concreteId = $this->command->insertEntity(
            'App\\ConcreteProcessor', 'ConcreteProcessor', 'class', null, null, null, [],
        );
        $this->command->insertRelationship($concreteId, $entityId, null, 'extends', null);

        $registry = new PatternRegistry([new TemplateMethod()]);
        $orchestrator = new Engine($registry, $this->pdo);
        $result = $orchestrator->run();

        $this->assertGreaterThanOrEqual(1, count($result->matches));
        $this->assertSame('template_method', $result->matches[0]->pattern->name());
    }

    public function testRunWithAllFivePatterns(): void
    {
        // Adapter
        $targetId = $this->command->insertEntity('App\\TargetInterface', 'TargetInterface', 'interface', null, null, null, []);
        $adapteeId = $this->command->insertEntity('App\\LegacyService', 'LegacyService', 'class', null, null, null, []);
        $adapterId = $this->command->insertEntity('App\\DatabaseAdapter', 'DatabaseAdapter', 'class', null, null, null, []);
        $this->command->insertRelationship($adapterId, $targetId, null, 'implements', null);
        $this->command->insertRelationship($adapterId, $adapteeId, null, 'creates', null);

        // Strategy
        $strategyId = $this->command->insertEntity('App\\StrategyInterface', 'StrategyInterface', 'interface', null, null, null, []);
        $impl1 = $this->command->insertEntity('App\\ConcreteStrategy1', 'ConcreteStrategy1', 'class', null, null, null, []);
        $impl2 = $this->command->insertEntity('App\\ConcreteStrategy2', 'ConcreteStrategy2', 'class', null, null, null, []);
        $ctx = $this->command->insertEntity('App\\Client', 'Client', 'class', null, null, null, []);
        $this->command->insertRelationship($impl1, $strategyId, null, 'implements', null);
        $this->command->insertRelationship($impl2, $strategyId, null, 'implements', null);
        $this->command->insertRelationship($ctx, $strategyId, null, 'creates', null);
        $strategyPropId = $this->command->insertProperty(
            entityId: $ctx, name: 'strategy', memberType: 'property',
            visibility: 'private', isStatic: false, isReadonly: false,
            declaredTypeEntityId: $strategyId, declaredTypeName: 'App\\StrategyInterface', defaultValue: null,
        );

        // Factory Method
        $productId = $this->command->insertEntity('App\\ProductInterface', 'ProductInterface', 'interface', null, null, null, []);
        $creatorId = $this->command->insertEntity('App\\AbstractFactory', 'AbstractFactory', 'class', null, null, null, ['abstract']);
        $methodId = $this->command->insertMethod(
            entityId: $creatorId, name: 'create',
            visibility: 'public', isStatic: false, isAbstract: true, isFinal: false,
            returnTypeEntityId: null, returnTypeName: 'App\\ProductInterface',
        );
        $childId = $this->command->insertEntity('App\\ConcreteFactory', 'ConcreteFactory', 'class', null, null, null, []);
        $this->command->insertRelationship($childId, $creatorId, null, 'extends', null);
        $this->command->insertRelationship($childId, $productId, null, 'creates', null);

        // Singleton
        $singletonId = $this->command->insertEntity('App\\MySingleton', 'MySingleton', 'class', null, null, null, []);
        $this->command->insertMethod(
            entityId: $singletonId, name: '__construct',
            visibility: 'private', isStatic: false, isAbstract: false, isFinal: false,
            returnTypeEntityId: null, returnTypeName: null,
        );
        $this->command->insertProperty(
            entityId: $singletonId, name: 'instance', memberType: 'property',
            visibility: 'private', isStatic: true, isReadonly: false,
            declaredTypeEntityId: null, declaredTypeName: 'self', defaultValue: null,
        );
        $this->command->insertMethod(
            entityId: $singletonId, name: 'getInstance',
            visibility: 'public', isStatic: true, isAbstract: false, isFinal: false,
            returnTypeEntityId: null, returnTypeName: 'self',
        );

        // Template Method
        $tmId = $this->command->insertEntity('App\\AbstractProcessor', 'AbstractProcessor', 'class', null, null, null, ['abstract']);
        $tmProcessMethodId = $this->command->insertMethod(
            entityId: $tmId, name: 'process',
            visibility: 'public', isStatic: false, isAbstract: false, isFinal: false,
            returnTypeEntityId: null, returnTypeName: 'void',
        );
        $this->command->insertMethod(
            entityId: $tmId, name: 'doStep',
            visibility: 'protected', isStatic: false, isAbstract: true, isFinal: false,
            returnTypeEntityId: null, returnTypeName: 'void',
        );
        $this->command->insertRelationship($tmId, null, null, 'call_dynamic_weak', $tmProcessMethodId);
        $concreteTm = $this->command->insertEntity(
            'App\\ConcreteProcessor', 'ConcreteProcessor', 'class', null, null, null, [],
        );
        $this->command->insertRelationship($concreteTm, $tmId, null, 'extends', null);

        // Builder
        $builderId = $this->command->insertEntity('App\\BuilderInterface', 'BuilderInterface', 'interface', null, null, null, []);
        $concreteBuilderId = $this->command->insertEntity('App\\ConcreteBuilder', 'ConcreteBuilder', 'class', null, null, null, []);
        $directorId = $this->command->insertEntity('App\\Director', 'Director', 'class', null, null, null, []);
        $this->command->insertRelationship($concreteBuilderId, $builderId, null, 'implements', null);
        $this->command->insertRelationship($directorId, $builderId, null, 'creates', null);

        // Decorator
        $componentId = $this->command->insertEntity('App\\ComponentInterface', 'ComponentInterface', 'interface', null, null, null, []);
        $decoratorId = $this->command->insertEntity('App\\AbstractDecorator', 'AbstractDecorator', 'class', null, null, null, ['abstract']);
        $this->command->insertRelationship($decoratorId, $componentId, null, 'implements', null);
        $this->command->insertRelationship($decoratorId, $componentId, null, 'creates', null);
        $decoratorPropId = $this->command->insertProperty(
            entityId: $decoratorId, name: 'component', memberType: 'property',
            visibility: 'private', isStatic: false, isReadonly: false,
            declaredTypeEntityId: $componentId, declaredTypeName: 'App\\ComponentInterface', defaultValue: null,
        );

        $registry = new PatternRegistry([
            new Adapter(), new Strategy(), new Singleton(),
            new TemplateMethod(), new FactoryMethod(),
            new Decorator(), new Builder(),
        ]);
        $orchestrator = new Engine($registry, $this->pdo);
        $result = $orchestrator->run();

        $names = array_map(fn($m) => $m->pattern->name(), $result->matches);
        $this->assertContains('adapter', $names);
        $this->assertContains('strategy', $names);
        $this->assertContains('factory_method', $names);
        $this->assertContains('builder', $names);
        $this->assertContains('decorator', $names);
    }
}
