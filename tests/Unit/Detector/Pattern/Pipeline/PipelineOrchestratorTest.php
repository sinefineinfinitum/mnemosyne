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
        $this->command->insertRelationship($adapterId, $adapteeId, null, 'dependency', null);

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
        $this->command->insertRelationship($contextId, $strategyId, null, 'dependency', null);
        $strategyPropertyId = $this->command->insertMember(
            entityId: $contextId, name: 'strategy', memberType: 'property',
            visibility: 'private', isStatic: false, isAbstract: false, isFinal: false,
            isReadonly: false, declaredType: 'App\\StrategyInterface', defaultValue: null, returnType: null,
        );
        $this->command->insertType('property', $strategyPropertyId, 'App\\StrategyInterface', $strategyId, false, false, 0);

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
        $this->command->insertRelationship($adapterId, $adapteeId, null, 'dependency', null);

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
        $methodId = $this->command->insertMember(
            entityId: $creatorId,
            name: 'create',
            memberType: 'method',
            visibility: 'public',
            isStatic: false,
            isAbstract: true,
            isFinal: false,
            isReadonly: false,
            declaredType: null,
            defaultValue: null,
            returnType: 'App\\ProductInterface',
        );
        $this->command->insertType('return', $methodId, 'App\\ProductInterface', $productId, false, false, 0);

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
        $this->command->insertRelationship($decoratorId, $componentId, null, 'dependency', null);
        $componentPropertyId = $this->command->insertMember(
            entityId: $decoratorId, name: 'component', memberType: 'property',
            visibility: 'private', isStatic: false, isAbstract: false, isFinal: false,
            isReadonly: false, declaredType: 'App\\ComponentInterface', defaultValue: null, returnType: null,
        );
        $this->command->insertType('property', $componentPropertyId, 'App\\ComponentInterface', $componentId, false, false, 0);

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
        $this->command->insertRelationship($directorId, $builderId, null, 'dependency', null);

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
        $this->command->insertMember(
            entityId: $entityId, name: '__construct', memberType: 'method',
            visibility: 'private', isStatic: false, isAbstract: false, isFinal: false,
            isReadonly: false, declaredType: null, defaultValue: null, returnType: null,
        );
        $this->command->insertMember(
            entityId: $entityId, name: 'instance', memberType: 'property',
            visibility: 'private', isStatic: true, isAbstract: false, isFinal: false,
            isReadonly: false, declaredType: 'self', defaultValue: null, returnType: null,
        );
        $this->command->insertMember(
            entityId: $entityId, name: 'getInstance', memberType: 'method',
            visibility: 'public', isStatic: true, isAbstract: false, isFinal: false,
            isReadonly: false, declaredType: null, defaultValue: null, returnType: 'self',
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
        $processMethodId = $this->command->insertMember(
            entityId: $entityId, name: 'process', memberType: 'method',
            visibility: 'public', isStatic: false, isAbstract: false, isFinal: false,
            isReadonly: false, declaredType: null, defaultValue: null, returnType: 'void',
        );
        $this->command->insertMember(
            entityId: $entityId, name: 'doStep', memberType: 'method',
            visibility: 'protected', isStatic: false, isAbstract: true, isFinal: false,
            isReadonly: false, declaredType: null, defaultValue: null, returnType: 'void',
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
        $this->command->insertRelationship($adapterId, $adapteeId, null, 'dependency', null);

        // Strategy
        $strategyId = $this->command->insertEntity('App\\StrategyInterface', 'StrategyInterface', 'interface', null, null, null, []);
        $impl1 = $this->command->insertEntity('App\\ConcreteStrategy1', 'ConcreteStrategy1', 'class', null, null, null, []);
        $impl2 = $this->command->insertEntity('App\\ConcreteStrategy2', 'ConcreteStrategy2', 'class', null, null, null, []);
        $ctx = $this->command->insertEntity('App\\Client', 'Client', 'class', null, null, null, []);
        $this->command->insertRelationship($impl1, $strategyId, null, 'implements', null);
        $this->command->insertRelationship($impl2, $strategyId, null, 'implements', null);
        $this->command->insertRelationship($ctx, $strategyId, null, 'dependency', null);
        $strategyPropId = $this->command->insertMember(
            entityId: $ctx, name: 'strategy', memberType: 'property',
            visibility: 'private', isStatic: false, isAbstract: false, isFinal: false,
            isReadonly: false, declaredType: 'App\\StrategyInterface', defaultValue: null, returnType: null,
        );
        $this->command->insertType('property', $strategyPropId, 'App\\StrategyInterface', $strategyId, false, false, 0);

        // Factory Method
        $productId = $this->command->insertEntity('App\\ProductInterface', 'ProductInterface', 'interface', null, null, null, []);
        $creatorId = $this->command->insertEntity('App\\AbstractFactory', 'AbstractFactory', 'class', null, null, null, ['abstract']);
        $methodId = $this->command->insertMember(
            entityId: $creatorId, name: 'create', memberType: 'method',
            visibility: 'public', isStatic: false, isAbstract: true, isFinal: false,
            isReadonly: false, declaredType: null, defaultValue: null, returnType: 'App\\ProductInterface',
        );
        $this->command->insertType('return', $methodId, 'App\\ProductInterface', $productId, false, false, 0);
        $childId = $this->command->insertEntity('App\\ConcreteFactory', 'ConcreteFactory', 'class', null, null, null, []);
        $this->command->insertRelationship($childId, $creatorId, null, 'extends', null);
        $this->command->insertRelationship($childId, $productId, null, 'creates', null);

        // Singleton
        $singletonId = $this->command->insertEntity('App\\MySingleton', 'MySingleton', 'class', null, null, null, []);
        $this->command->insertMember(
            entityId: $singletonId, name: '__construct', memberType: 'method',
            visibility: 'private', isStatic: false, isAbstract: false, isFinal: false,
            isReadonly: false, declaredType: null, defaultValue: null, returnType: null,
        );
        $this->command->insertMember(
            entityId: $singletonId, name: 'instance', memberType: 'property',
            visibility: 'private', isStatic: true, isAbstract: false, isFinal: false,
            isReadonly: false, declaredType: 'self', defaultValue: null, returnType: null,
        );
        $this->command->insertMember(
            entityId: $singletonId, name: 'getInstance', memberType: 'method',
            visibility: 'public', isStatic: true, isAbstract: false, isFinal: false,
            isReadonly: false, declaredType: null, defaultValue: null, returnType: 'self',
        );

        // Template Method
        $tmId = $this->command->insertEntity('App\\AbstractProcessor', 'AbstractProcessor', 'class', null, null, null, ['abstract']);
        $tmProcessMethodId = $this->command->insertMember(
            entityId: $tmId, name: 'process', memberType: 'method',
            visibility: 'public', isStatic: false, isAbstract: false, isFinal: false,
            isReadonly: false, declaredType: null, defaultValue: null, returnType: 'void',
        );
        $this->command->insertMember(
            entityId: $tmId, name: 'doStep', memberType: 'method',
            visibility: 'protected', isStatic: false, isAbstract: true, isFinal: false,
            isReadonly: false, declaredType: null, defaultValue: null, returnType: 'void',
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
        $this->command->insertRelationship($directorId, $builderId, null, 'dependency', null);

        // Decorator
        $componentId = $this->command->insertEntity('App\\ComponentInterface', 'ComponentInterface', 'interface', null, null, null, []);
        $decoratorId = $this->command->insertEntity('App\\AbstractDecorator', 'AbstractDecorator', 'class', null, null, null, ['abstract']);
        $this->command->insertRelationship($decoratorId, $componentId, null, 'implements', null);
        $this->command->insertRelationship($decoratorId, $componentId, null, 'dependency', null);
        $decoratorPropId = $this->command->insertMember(
            entityId: $decoratorId, name: 'component', memberType: 'property',
            visibility: 'private', isStatic: false, isAbstract: false, isFinal: false,
            isReadonly: false, declaredType: 'App\\ComponentInterface', defaultValue: null, returnType: null,
        );
        $this->command->insertType('property', $decoratorPropId, 'App\\ComponentInterface', $componentId, false, false, 0);

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
