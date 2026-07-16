<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Msv1Parser;

use Eris\Generators;
use Eris\TestTrait;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Msv1Parser\Ast\CallNode;
use SineFine\Mnemosyne\Msv1Parser\Ast\Document;
use SineFine\Mnemosyne\Msv1Parser\Ast\EntityNode;
use SineFine\Mnemosyne\Msv1Parser\Ast\MemberNode;
use SineFine\Mnemosyne\Msv1Parser\Ast\ParameterNode;
use SineFine\Mnemosyne\Msv1Parser\Parser;
use SineFine\Mnemosyne\Msv1Parser\SyntaxException;

if (!trait_exists(TestTrait::class)) {
    return;
}

/**
 * @requires PHP >= 8.1
 */
class ParserRoundTripTest extends TestCase
{
    use TestTrait;

    private const FQCNS = [
        'App\Foo',
        'App\Bar\Baz',
        'App\Service\UserService',
        'Lib\Helper',
        'Some\Long\Namespace\ClassName',
        'App\X',
        'App\Traits\Loggable',
        'App\Contracts\ServiceInterface',
        'App\Enum\Status',
    ];

    private const FILE_PATHS = [
        'src/functions.php',
        'lib/helper.php',
        'src/Sub/Module/script.php',
        'config/settings.php',
        'src/main.php',
    ];

    private const TYPES = ['int', 'string', 'bool', 'array', 'void', 'null', 'mixed', 'float'];
    private const OBJECT_TYPES = ['App\Entity\User', 'App\Dto\Result', 'App\Service\Manager'];

    private const VALUES = ['1', '0', 'true', 'false', 'null', "'hello'", '3.14', '[]', "''", '42'];

    private const VISIBILITIES = ['public', 'private', 'protected'];

    // ── Helpers ───────────────────────────────────────────────────────

    private function serializeDocument(Document $doc): string
    {
        $out = '';
        foreach ($doc->entities as $entity) {
            $out .= $this->serializeEntity($entity);
        }
        return $out;
    }

    private function serializeEntity(EntityNode $entity): string
    {
        $parts = ['@' . $entity->type, $entity->name];
        foreach ($entity->attributes as $attr) {
            $parts[] = $attr;
        }
        $out = implode(' ', $parts) . "\n";

        foreach ($entity->extends as $e) {
            $out .= '>' . $e . "\n";
        }
        foreach ($entity->implements as $i) {
            $out .= '<' . $i . "\n";
        }
        foreach ($entity->traits as $t) {
            $out .= '%' . $t . "\n";
        }

        $isFile = $entity->isFile();
        foreach ($entity->members as $member) {
            $out .= $this->serializeMember($member, $isFile);
        }

        return $out;
    }

    private function serializeMember(MemberNode $member, bool $isFile): string
    {
        $out = '';
        $symbol = $this->symbolForType($member->type);

        if (in_array($member->type, ['method', 'function'], true)) {
            $out .= $symbol;
            if ($member->type === 'method') {
                $out .= $this->visSigil($member->visibility);
            }
            $out .= $member->name;
            if (!empty($member->attributes)) {
                $out .= ' ' . implode(' ', $member->attributes);
            }
            $out .= "\n";

            foreach ($member->parameters as $param) {
                $out .= '    ' . $this->serializeParameter($param) . "\n";
            }
            if ($member->returnType !== null) {
                $out .= '    :' . $member->returnType . "\n";
            }
            foreach ($member->creates as $create) {
                $out .= '    ^' . $create . "\n";
            }
            foreach ($member->calls as $call) {
                $out .= '    ' . $this->serializeCall($call) . "\n";
            }
        } elseif ($member->type === 'enum_case') {
            $out .= '~' . $member->name;
            if ($member->dataType !== null) {
                $out .= ':' . $member->dataType;
            }
            if ($member->value !== null) {
                $out .= '=' . $member->value;
            }
            $out .= "\n";
        } elseif ($member->type === 'global_variable') {
            $out .= '$' . $member->name;
            if ($member->dataType !== null) {
                $out .= ':' . $member->dataType;
            }
            if ($member->value !== null) {
                $out .= '=' . $member->value;
            }
            $out .= "\n";
        } else {
            $out .= $symbol;
            if (!$isFile) {
                $out .= $this->visSigil($member->visibility);
            }
            $out .= $member->name;
            if (!empty($member->attributes)) {
                $out .= ' ' . implode(' ', $member->attributes);
            }
            if ($member->dataType !== null) {
                $out .= ':' . $member->dataType;
            }
            if ($member->value !== null) {
                $out .= '=' . $member->value;
            }
            $out .= "\n";
        }

        return $out;
    }

    private function serializeParameter(ParameterNode $param): string
    {
        $out = '';
        if ($param->byRef) {
            $out .= '&';
        }
        $out .= '$' . $param->name;
        if ($param->type !== null) {
            $out .= ':' . $param->type;
        }
        if ($param->value !== null) {
            $out .= '=' . $param->value;
        }
        return $out;
    }

    private function serializeCall(CallNode $call): string
    {
        $marker = $call->marker === 'strong' ? '*' : '?';
        return match ($call->type) {
            'static' => $marker . $call->targetFQCN . '::' . $call->targetMethod,
            'dynamic' => $marker . $call->targetFQCN . '->' . $call->targetMethod,
            'global' => $marker . $call->targetMethod,
        };
    }

    private function symbolForType(string $type): string
    {
        return match ($type) {
            'property', 'global_variable' => '$',
            'constant' => '!',
            'method', 'function' => '.',
            'enum_case' => '~',
        };
    }

    private function visSigil(?string $visibility): string
    {
        return match ($visibility) {
            'public' => '+',
            'private' => '-',
            'protected' => '#',
            default => '',
        };
    }

    private function assertEntityMatches(EntityNode $expected, EntityNode $actual): void
    {
        $this->assertSame($expected->name, $actual->name, "entity name mismatch");
        $this->assertSame($expected->type, $actual->type, "entity type mismatch");
        $this->assertSame($this->sorted($expected->attributes), $this->sorted($actual->attributes), "entity attributes mismatch");
        $this->assertSame($expected->extends, $actual->extends, "entity extends mismatch");
        $this->assertSame($expected->implements, $actual->implements, "entity implements mismatch");
        $this->assertSame($expected->traits, $actual->traits, "entity traits mismatch");
        $this->assertCount(count($expected->members), $actual->members, "member count mismatch");

        foreach ($expected->members as $i => $expMember) {
            $actMember = $actual->members[$i];
            $this->assertSame($expMember->name, $actMember->name, "member {$i} name");
            $this->assertSame($expMember->type, $actMember->type, "member {$i} type");
            $this->assertSame($expMember->visibility, $actMember->visibility, "member {$i} visibility");
            $this->assertSame($expMember->dataType, $actMember->dataType, "member {$i} dataType");
            $this->assertSame($expMember->returnType, $actMember->returnType, "member {$i} returnType");
            $this->assertSame($expMember->value, $actMember->value, "member {$i} value");
            $this->assertSame(
                $this->sorted($expMember->attributes),
                $this->sorted($actMember->attributes),
                "member {$i} attributes",
            );

            if (in_array($expMember->type, ['method', 'function'], true)) {
                $this->assertCount(
                    count($expMember->parameters),
                    $actMember->parameters,
                    "member {$i} param count",
                );
                foreach ($expMember->parameters as $j => $expParam) {
                    $actParam = $actMember->parameters[$j];
                    $this->assertSame($expParam->name, $actParam->name, "member {$i} param {$j} name");
                    $this->assertSame($expParam->type, $actParam->type, "member {$i} param {$j} type");
                    $this->assertSame($expParam->byRef, $actParam->byRef, "member {$i} param {$j} byRef");
                    $this->assertSame($expParam->value, $actParam->value, "member {$i} param {$j} value");
                }

                $this->assertCount(
                    count($expMember->creates),
                    $actMember->creates,
                    "member {$i} creates count",
                );
                foreach ($expMember->creates as $j => $create) {
                    $this->assertSame($create, $actMember->creates[$j], "member {$i} create {$j}");
                }

                $this->assertCount(
                    count($expMember->calls),
                    $actMember->calls,
                    "member {$i} calls count",
                );
                foreach ($expMember->calls as $j => $expCall) {
                    $actCall = $actMember->calls[$j];
                    $this->assertSame($expCall->type, $actCall->type, "member {$i} call {$j} type");
                    $this->assertSame($expCall->targetFQCN, $actCall->targetFQCN, "member {$i} call {$j} fqcn");
                    $this->assertSame($expCall->targetMethod, $actCall->targetMethod, "member {$i} call {$j} method");
                    $this->assertSame($expCall->marker, $actCall->marker, "member {$i} call {$j} marker");
                }
            }
        }
    }

    /** @param string[] $items */
    private function sorted(array $items): array
    {
        sort($items);
        return $items;
    }

    private function roundTripEntity(EntityNode $entity): void
    {
        $doc = new Document();
        $doc->entities = [$entity];

        $msv1 = $this->serializeDocument($doc);
        $parsed = (new Parser())->parse($msv1);

        $this->assertCount(1, $parsed->entities);
        $this->assertEntityMatches($entity, $parsed->entities[0]);
    }

    // ── Tests ────────────────────────────────────────────────────────

    public function testRoundTripSimpleEntity(): void
    {
        $this->forAll(
            Generators::tuple(
                Generators::elements(['class', 'interface', 'trait', 'enum', 'file']),
                Generators::oneOf(
                    Generators::elements(self::FQCNS),
                    Generators::elements(self::FILE_PATHS),
                ),
            ),
        )->then(function (array $data): void {
            $this->roundTripEntity(new EntityNode($data[0], $data[1]));
        });
    }

    public function testRoundTripEntityWithAttributes(): void
    {
        $this->forAll(
            Generators::tuple(
                Generators::elements(['class', 'interface', 'trait', 'enum']),
                Generators::elements(self::FQCNS),
                Generators::subset(['final', 'abstract']),
            ),
        )->then(function (array $data): void {
            [$type, $name, $attrs] = $data;
            $entity = new EntityNode($type, $name);
            $entity->attributes = $attrs;
            $this->roundTripEntity($entity);
        });
    }

    public function testRoundTripEntityWithRelations(): void
    {
        $this->forAll(
            Generators::tuple(
                Generators::elements(['class', 'interface', 'trait']),
                Generators::elements(self::FQCNS),
                Generators::subset(self::FQCNS),
                Generators::subset(self::FQCNS),
                Generators::elements([['App\LoggableTrait'], []]),
            ),
        )->then(function (array $data): void {
            [$type, $name, $extends, $implements, $traits] = $data;
            $entity = new EntityNode($type, $name);
            $entity->extends = $extends;
            $entity->implements = $implements;
            if ($type === 'trait') {
                $entity->traits = $traits;
            }
            $this->roundTripEntity($entity);
        });
    }

    public function testRoundTripProperty(): void
    {
        $this->forAll(
            Generators::tuple(
                Generators::elements(['class', 'interface', 'trait']),
                Generators::elements(self::FQCNS),
                Generators::elements(self::VISIBILITIES),
                Generators::elements(['prop', 'value', 'counter', 'name', 'isActive', 'items']),
                Generators::subset(['static', 'readonly']),
                Generators::oneOf(
                    Generators::constant(null),
                    Generators::elements(array_merge(self::TYPES, self::OBJECT_TYPES)),
                ),
                Generators::oneOf(
                    Generators::constant(null),
                    Generators::elements(self::VALUES),
                ),
            ),
        )->then(function (array $data): void {
            [$type, $name, $vis, $propName, $attrs, $dataType, $value] = $data;
            $entity = new EntityNode($type, $name);
            $member = new MemberNode($propName, 'property', $entity);
            $member->visibility = $vis;
            $member->attributes = $attrs;
            $member->dataType = $dataType;
            $member->returnType = $dataType;
            $member->value = $value;
            $entity->members[] = $member;
            $this->roundTripEntity($entity);
        });
    }

    public function testRoundTripConstant(): void
    {
        $this->forAll(
            Generators::tuple(
                Generators::elements(['class', 'interface', 'trait', 'enum']),
                Generators::elements(self::FQCNS),
                Generators::elements(self::VISIBILITIES),
                Generators::elements(['MAX', 'COUNT', 'DEFAULT', 'VERSION', 'LIMIT', 'NAME']),
                Generators::oneOf(
                    Generators::constant(null),
                    Generators::elements(array_merge(self::TYPES, self::OBJECT_TYPES)),
                ),
                Generators::oneOf(
                    Generators::constant(null),
                    Generators::elements(self::VALUES),
                ),
            ),
        )->then(function (array $data): void {
            [$type, $name, $vis, $constName, $dataType, $value] = $data;
            $entity = new EntityNode($type, $name);
            $member = new MemberNode($constName, 'constant', $entity);
            $member->visibility = $vis;
            $member->dataType = $dataType;
            $member->returnType = $dataType;
            $member->value = $value;
            $entity->members[] = $member;
            $this->roundTripEntity($entity);
        });
    }

    public function testRoundTripMethod(): void
    {
        $this->forAll(
            Generators::tuple(
                Generators::elements(['class', 'interface', 'trait']),
                Generators::elements(self::FQCNS),
                Generators::elements(self::VISIBILITIES),
                Generators::elements(['execute', 'run', 'handle', 'process', 'find', 'save']),
                Generators::subset(['final', 'abstract', 'static']),
                Generators::oneOf(
                    Generators::constant(null),
                    Generators::elements(array_merge(self::TYPES, self::OBJECT_TYPES, ['void'])),
                ),
                Generators::elements([[], [
                    ['input', 'int', null, false],
                ], [
                    ['input', 'int', null, true],
                    ['options', 'array', '[]', false],
                ]]),
                Generators::elements([
                    [],
                    ['App\Result'],
                    ['App\User', 'App\Dto'],
                ]),
            ),
        )->then(function (array $data): void {
            [$type, $name, $vis, $methodName, $attrs, $returnType, $params, $creates] = $data;
            $entity = new EntityNode($type, $name);
            $member = new MemberNode($methodName, 'method', $entity);
            $member->visibility = $vis;
            $member->attributes = $attrs;
            $member->returnType = $returnType;
            $member->creates = $creates;

            foreach ($params as [$paramName, $paramType, $paramValue, $paramByRef]) {
                $param = new ParameterNode($paramName);
                $param->type = $paramType;
                $param->value = $paramValue;
                $param->byRef = $paramByRef;
                $member->parameters[] = $param;
            }

            $entity->members[] = $member;
            $this->roundTripEntity($entity);
        });
    }

    public function testRoundTripEnum(): void
    {
        $this->forAll(
            Generators::tuple(
                Generators::elements(['enum']),
                Generators::elements(self::FQCNS),
                Generators::elements([['Active'], ['Active', 'Inactive'], ['Pending', 'Approved', 'Rejected']]),
                Generators::elements([
                    [],
                    [['Active', '1', null]],
                    [['Active', '1', 'int'], ['Inactive', '2', 'int']],
                    [['Pending', null, null], ['Active', '1', 'string']],
                ]),
            ),
        )->then(function (array $data): void {
            [$type, $name, $cases, $caseData] = $data;
            $entity = new EntityNode($type, $name);
            foreach ($caseData as [$caseName, $caseValue, $caseType]) {
                $member = new MemberNode($caseName, 'enum_case', $entity);
                $member->value = $caseValue;
                $member->dataType = $caseType;
                $member->returnType = $caseType;
                $entity->members[] = $member;
            }
            $this->roundTripEntity($entity);
        });
    }

    public function testRoundTripFileEntity(): void
    {
        $this->forAll(
            Generators::tuple(
                Generators::elements(['file']),
                Generators::elements(self::FILE_PATHS),
            ),
        )->then(function (array $data): void {
            [$type, $name] = $data;
            $entity = new EntityNode($type, $name);

            $func = new MemberNode('doSomething', 'function', $entity);
            $func->returnType = 'void';
            $entity->members[] = $func;

            if (random_int(0, 1) === 0) {
                $globalVar = new MemberNode('debug', 'global_variable', $entity);
                $globalVar->dataType = 'bool';
                $globalVar->returnType = 'bool';
                $globalVar->value = 'false';
                $entity->members[] = $globalVar;
            }

            if (random_int(0, 1) === 0) {
                $const = new MemberNode('MAX_RETRIES', 'constant', $entity);
                $const->dataType = 'int';
                $const->returnType = 'int';
                $const->value = '3';
                $entity->members[] = $const;
            }

            $this->roundTripEntity($entity);
        });
    }

    public function testRoundTripCallGraph(): void
    {
        $this->forAll(
            Generators::tuple(
                Generators::elements(self::FQCNS),
                Generators::elements(['process', 'handle', 'execute']),
                Generators::elements([
                    ['type' => 'static', 'method' => 'find'],
                    ['type' => 'dynamic', 'method' => 'send'],
                    ['type' => 'global', 'method' => 'App\Util\formatDate'],
                ]),
                Generators::elements(['strong', 'weak']),
            ),
        )->then(function (array $data): void {
            [$entityName, $methodName, $callInfo, $marker] = $data;

            $entity = new EntityNode('class', $entityName);
            $member = new MemberNode($methodName, 'method', $entity);
            $member->visibility = 'public';
            $member->returnType = 'void';

            $fqcn = match ($callInfo['type']) {
                'static' => 'App\Repository\SearchRepository',
                'dynamic' => 'App\Service\Handler',
                'global' => '',
            };
            $call = new CallNode($callInfo['type'], $fqcn, $callInfo['method'], $marker);
            $member->calls[] = $call;
            $entity->members[] = $member;

            $this->roundTripEntity($entity);
        });
    }
}
