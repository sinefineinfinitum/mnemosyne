<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitorAbstract;

class EntityExtractingVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $entities = [];

    public function __construct(
        private string $namespace
    ) {
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Class_ && !$node->isAnonymous()) {
            $this->entities[] = $this->extractClass($node);
        } elseif ($node instanceof Interface_) {
            $this->entities[] = $this->extractInterface($node);
        } elseif ($node instanceof Trait_) {
            $this->entities[] = $this->extractTrait($node);
        } elseif ($node instanceof Enum_) {
            $this->entities[] = $this->extractEnum($node);
        }
        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function entities(): array
    {
        $entities = $this->entities;

        usort($entities, fn($a, $b) => strcmp($a['fqn'], $b['fqn']));

        return $entities;
    }

    /**
     * @return string[]
     */
    private function modifiers(Class_ $node): array
    {
        $mods = [];
        if ($node->isAbstract()) {
            $mods[] = 'abstract';
        }
        if ($node->isFinal()) {
            $mods[] = 'final';
        }
        if ($node->isReadonly()) {
            $mods[] = 'readonly';
        }
        return $mods;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function methods(Node\Stmt\ClassLike $node): array
    {
        $methods = [];
        foreach ($node->getMethods() as $method) {
            if (!$method->isPublic()) {
                continue;
            }
            $params = [];
            foreach ($method->getParams() as $param) {
                $p = [
                    'name' => $param->var->name ?? '',
                    'type' => $param->type !== null ? $this->resolveType($param->type) : null,
                    'typeNullable' => $param->type !== null && $param->type instanceof Node\NullableType,
                    'defaultValue' => $param->default !== null ? $this->resolveDefault($param->default) : null,
                    'isVariadic' => $param->variadic,
                    'isPassedByReference' => $param->byRef,
                ];
                $params[] = $p;
            }

            $methods[] = [
                'name' => $method->name->toString(),
                'visibility' => 'public',
                'isStatic' => $method->isStatic(),
                'isAbstract' => $method->isAbstract(),
                'parameters' => $params,
                'returnType' => $method->returnType !== null ? $this->resolveType($method->returnType) : null,
                'returnTypeNullable' => $method->returnType !== null && $method->returnType instanceof Node\NullableType,
            ];
        }
        usort($methods, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $methods;
    }

    private function resolveType(Node $typeNode): string
    {
        if ($typeNode instanceof Node\NullableType) {
            return '?' . $this->resolveType($typeNode->type);
        }
        if ($typeNode instanceof Node\UnionType) {
            return implode('|', array_map([$this, 'resolveType'], $typeNode->types));
        }
        if ($typeNode instanceof Node\IntersectionType) {
            return implode('&', array_map([$this, 'resolveType'], $typeNode->types));
        }
        if ($typeNode instanceof Node\Name) {
            return $typeNode->toCodeString();
        }
        if ($typeNode instanceof Node\Identifier) {
            return $typeNode->toString();
        }
        return $typeNode->getType();
    }

    private function resolveDefault(Node\Expr $default): string
    {
        if ($default instanceof Node\Expr\ConstFetch) {
            return $default->name->toCodeString();
        }
        if ($default instanceof Node\Scalar\String_) {
            return "'" . $default->value . "'";
        }
        if ($default instanceof Int_) {
            return (string)$default->value;
        }
        if ($default instanceof Float_) {
            return (string)$default->value;
        }
        if ($default instanceof Node\Expr\Array_) {
            return '[]';
        }
        if ($default instanceof Node\Expr\UnaryMinus) {
            return '-' . $this->resolveDefault($default->expr);
        }
        return 'null';
    }

    /**
     * @return array<string, mixed>
     */
    private function extractClass(Class_ $node): array
    {
        $name = $node->name !== null ? $node->name->toString() : '';
        $fqn = $this->namespace !== '' ? $this->namespace . '\\' . $name : $name;
        $interfaces = [];
        foreach ($node->implements as $iface) {
            $interfaces[] = ltrim($iface->toCodeString(), '\\');
        }
        sort($interfaces);
        return [
            'fqn' => $fqn,
            'type' => 'class',
            'modifiers' => $this->modifiers($node),
            'parentClass' => $node->extends !== null ? ltrim($node->extends->toCodeString(), '\\') : null,
            'interfaces' => $interfaces,
            'methods' => $this->methods($node),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractInterface(Interface_ $node): array
    {
        $name = $node->name !== null ? $node->name->toString() : '';
        $fqn = $this->namespace !== '' ? $this->namespace . '\\' . $name : $name;
        return [
            'fqn' => $fqn,
            'type' => 'interface',
            'modifiers' => [],
            'parentClass' => null,
            'interfaces' => [],
            'methods' => $this->methods($node),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractTrait(Trait_ $node): array
    {
        $name = $node->name !== null ? $node->name->toString() : '';
        $fqn = $this->namespace !== '' ? $this->namespace . '\\' . $name : $name;
        return [
            'fqn' => $fqn,
            'type' => 'trait',
            'modifiers' => [],
            'parentClass' => null,
            'interfaces' => [],
            'methods' => $this->methods($node),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function extractEnum(Enum_ $node): array
    {
        $name = $node->name !== null ? $node->name->toString() : '';
        $fqn = $this->namespace !== '' ? $this->namespace . '\\' . $name : $name;
        return [
            'fqn' => $fqn,
            'type' => 'enum',
            'modifiers' => [],
            'parentClass' => null,
            'interfaces' => [],
            'methods' => $this->methods($node),
        ];
    }
}
