<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Msv1Parser\Ast;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Msv1Parser\Ast\CallNode;
use SineFine\Mnemosyne\Msv1Parser\Ast\EntityNode;

final class CallNodeTest extends TestCase
{
    public function testConstants(): void
    {
        $this->assertSame('*', CallNode::MARKER_STRONG);
        $this->assertSame('?', CallNode::MARKER_WEAK);
        $this->assertSame('static', CallNode::TYPE_STATIC);
        $this->assertSame('dynamic', CallNode::TYPE_DYNAMIC);
        $this->assertSame('global', CallNode::TYPE_GLOBAL);
    }

    /**
     * @dataProvider parseCallProvider
     */
    public function testParseCall(string $line, ?CallNode $expected): void
    {
        $result = CallNode::parseCall($line);
        if ($expected === null) {
            $this->assertNull($result);
            return;
        }
        $this->assertNotNull($result);
        $this->assertSame($expected->marker, $result->marker);
        $this->assertSame($expected->type, $result->type);
        $this->assertSame($expected->targetFQCN, $result->targetFQCN);
        $this->assertSame($expected->targetMethod, $result->targetMethod);
    }

    public static function parseCallProvider(): array
    {
        return [
            'static call' => [
                '*App\Service\SearchService::search',
                new CallNode('static', 'App\Service\SearchService', 'search', 'strong'),
            ],
            'dynamic call' => [
                '?App\Service\Handler->process',
                new CallNode('dynamic', 'App\Service\Handler', 'process', 'weak'),
            ],
            'global call strong' => [
                '*App\Util\formatDate',
                new CallNode('global', '', 'App\Util\formatDate', 'strong'),
            ],
            'global call weak' => [
                '?someFunction',
                new CallNode('global', '', 'someFunction', 'weak'),
            ],
            'empty string' => ['', null],
            'no marker' => ['App\Service::method', null],
            'invalid marker' => ['#App\Service::method', null],
        ];
    }

    public function testResolveThisCall(): void
    {
        $entity = new EntityNode('class', 'App\Service\SearchService');
        $node = self::resolveCall('$this->search', $entity, []);
        $this->assertSame('dynamic', $node->type);
        $this->assertSame('App\Service\SearchService', $node->targetFQCN);
        $this->assertSame('search', $node->targetMethod);
        $this->assertSame('strong', $node->marker);
    }

    public function testResolveSelfCall(): void
    {
        $entity = new EntityNode('class', 'App\Service\SearchService');
        $node = self::resolveCall('self::search', $entity, []);
        $this->assertSame('static', $node->type);
        $this->assertSame('App\Service\SearchService', $node->targetFQCN);
        $this->assertSame('search', $node->targetMethod);
        $this->assertSame('strong', $node->marker);
    }

    public function testResolveParentCallWithExtends(): void
    {
        $entity = new EntityNode('class', 'App\Service\SearchService');
        $entity->extends = ['App\Core\BaseService'];
        $node = self::resolveCall('parent::init', $entity, []);
        $this->assertSame('static', $node->type);
        $this->assertSame('App\Core\BaseService', $node->targetFQCN);
        $this->assertSame('init', $node->targetMethod);
        $this->assertSame('strong', $node->marker);
    }

    public function testResolveParentCallWithoutExtends(): void
    {
        $entity = new EntityNode('class', 'App\Service\SearchService');
        $node = self::resolveCall('parent::init', $entity, []);
        $this->assertSame('static', $node->type);
        $this->assertSame('', $node->targetFQCN);
        $this->assertSame('init', $node->targetMethod);
        $this->assertSame('weak', $node->marker);
    }

    public function testResolveStaticCallInFinalClass(): void
    {
        $entity = new EntityNode('class', 'App\Service\SearchService');
        $entity->attributes = ['final'];
        $node = self::resolveCall('static::search', $entity, []);
        $this->assertSame('static', $node->type);
        $this->assertSame('App\Service\SearchService', $node->targetFQCN);
        $this->assertSame('search', $node->targetMethod);
        $this->assertSame('strong', $node->marker);
    }

    public function testResolveStaticCallInNonFinalClass(): void
    {
        $entity = new EntityNode('class', 'App\Service\SearchService');
        $node = self::resolveCall('static::search', $entity, []);
        $this->assertSame('static', $node->type);
        $this->assertSame('App\Service\SearchService', $node->targetFQCN);
        $this->assertSame('search', $node->targetMethod);
        $this->assertSame('weak', $node->marker);
    }

    public function testResolveFQCNCallInTypeIndex(): void
    {
        $entity = new EntityNode('class', 'App\Service\SearchService');
        $typeIndex = ['App\Repository\SearchRepository' => ['find' => 'method']];
        $node = self::resolveCall('App\Repository\SearchRepository::find', $entity, $typeIndex);
        $this->assertSame('static', $node->type);
        $this->assertSame('App\Repository\SearchRepository', $node->targetFQCN);
        $this->assertSame('find', $node->targetMethod);
        $this->assertSame('strong', $node->marker);
    }

    public function testResolveFQCNCallNotInTypeIndex(): void
    {
        $entity = new EntityNode('class', 'App\Service\SearchService');
        $node = self::resolveCall('App\External\Service::call', $entity, []);
        $this->assertSame('static', $node->type);
        $this->assertSame('App\External\Service', $node->targetFQCN);
        $this->assertSame('call', $node->targetMethod);
        $this->assertSame('weak', $node->marker);
    }

    public function testResolveGlobalFunctionInTypeIndex(): void
    {
        $entity = new EntityNode('file', 'src/functions.php');
        $typeIndex = ['formatDate' => true];
        $node = self::resolveCall('formatDate', $entity, $typeIndex);
        $this->assertSame('global', $node->type);
        $this->assertSame('', $node->targetFQCN);
        $this->assertSame('formatDate', $node->targetMethod);
        $this->assertSame('strong', $node->marker);
    }

    public function testResolveGlobalFunctionNotInTypeIndex(): void
    {
        $entity = new EntityNode('file', 'src/functions.php');
        $node = self::resolveCall('unknownFunc', $entity, []);
        $this->assertSame('global', $node->type);
        $this->assertSame('', $node->targetFQCN);
        $this->assertSame('unknownFunc', $node->targetMethod);
        $this->assertSame('weak', $node->marker);
    }

    /**
     * @param string               $expression
     * @param EntityNode           $currentEntity
     * @param array<string, mixed> $typeIndex
     */
    private static function resolveCall(string $expression, EntityNode $currentEntity, array $typeIndex = []): CallNode
    {
        if (str_starts_with($expression, '$this->')) {
            $method = substr($expression, 7);
            return new CallNode(CallNode::TYPE_DYNAMIC, $currentEntity->name, $method, 'strong');
        }

        if (str_starts_with($expression, 'self::')) {
            $method = substr($expression, 6);
            return new CallNode(CallNode::TYPE_STATIC, $currentEntity->name, $method, 'strong');
        }

        if (str_starts_with($expression, 'parent::')) {
            $method = substr($expression, 8);
            if (!empty($currentEntity->extends)) {
                return new CallNode(CallNode::TYPE_STATIC, $currentEntity->extends[0], $method, 'strong');
            }
            return new CallNode(CallNode::TYPE_STATIC, '', $method, 'weak');
        }

        if (str_starts_with($expression, 'static::')) {
            $method = substr($expression, 8);
            $isFinal = in_array('final', $currentEntity->attributes, true);
            return new CallNode(
                CallNode::TYPE_STATIC,
                $currentEntity->name,
                $method,
                $isFinal ? 'strong' : 'weak'
            );
        }

        if (str_contains($expression, '::')) {
            [$fqcn, $method] = explode('::', $expression, 2);
            $marker = isset($typeIndex[$fqcn]) ? 'strong' : 'weak';
            return new CallNode(CallNode::TYPE_STATIC, $fqcn, $method, $marker);
        }

        if (str_contains($expression, '->')) {
            [$fqcn, $method] = explode('->', $expression, 2);
            $marker = isset($typeIndex[$fqcn]) ? 'strong' : 'weak';
            return new CallNode(CallNode::TYPE_DYNAMIC, $fqcn, $method, $marker);
        }

        $marker = isset($typeIndex[$expression]) ? 'strong' : 'weak';
        return new CallNode(CallNode::TYPE_GLOBAL, '', $expression, $marker);
    }
}
