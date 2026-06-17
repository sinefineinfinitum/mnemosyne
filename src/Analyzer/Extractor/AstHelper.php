<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Extractor;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\UnionType;

final class AstHelper
{
    public function resolveFqn(string $namespace, string $name): string
    {
        return $namespace !== ''
            ? $namespace . '\\' . $name
            : $name;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function extractConstants(ClassLike $node): array
    {
        $constants = [];
        foreach ($node->getConstants() as $const) {
            $visibility = $this->resolveVisibility($const);

            foreach ($const->consts as $c) {
                $constants[] = [
                    'name' => $c->name->toString(),
                    'visibility' => $visibility,
                    'value' => $this->resolveDefault($c->value),
                    'type' => $const->type !== null ? $this->resolveType($const->type) : null,
                ];
            }
        }
        return $constants;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function extractMethods(ClassLike $node): array
    {
        $methods = [];
        foreach ($node->getMethods() as $method) {
            $visibility = $this->resolveVisibility($method);

            $params = [];
            foreach ($method->getParams() as $param) {
                $params[] = [
                    'name' => $param->var->name ?? '',
                    'type' => $param->type !== null ? $this->resolveType($param->type) : 'mixed',
                    'defaultValue' => $param->default !== null ? $this->resolveDefault($param->default) : null,
                    'isVariadic' => $param->variadic,
                    'isPassedByReference' => $param->byRef,
                ];
            }

            $methods[] = [
                'name' => $method->name->toString(),
                'visibility' => $visibility,
                'isStatic' => $method->isStatic(),
                'isAbstract' => $method->isAbstract(),
                'isFinal' => $method->isFinal(),
                'parameters' => $params,
                'returnType' => $method->returnType !== null ? $this->resolveType($method->returnType) : null,
            ];
        }
        return $methods;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function extractProperties(ClassLike $node): array
    {
        $properties = [];
        foreach ($node->getProperties() as $prop) {
            $visibility = $this->resolveVisibility($prop);

            foreach ($prop->props as $p) {
                $properties[] = [
                    'name' => $p->name->toString(),
                    'visibility' => $visibility,
                    'type' => $prop->type !== null ? $this->resolveType($prop->type) : 'mixed',
                    'defaultValue' => $p->default !== null ? $this->resolveDefault($p->default) : null,
                    'isStatic' => $prop->isStatic(),
                    'isReadonly' => $prop->isReadonly(),
                ];
            }
        }

        foreach ($node->getMethods() as $method) {
            if (strtolower($method->name->toString()) !== '__construct') {
                continue;
            }

            foreach ($method->getParams() as $param) {
                if (!$param->isPromoted()) {
                    continue;
                }

                $properties[] = [
                    'name' => $param->var->name ?? '',
                    'visibility' => $this->resolveParamVisibility($param),
                    'type' => $param->type !== null ? $this->resolveType($param->type) : null,
                    'defaultValue' => $param->default !== null ? $this->resolveDefault($param->default) : null,
                    'isStatic' => false,
                    'isReadonly' => $param->isReadonly(),
                ];
            }
        }

        return $properties;
    }

    private function resolveVisibility(Node\Stmt\ClassConst|Node\Stmt\ClassMethod|Node\Stmt\Property $node): string
    {
        if ($node->isPrivate()) {
            return 'private';
        }
        if ($node->isProtected()) {
            return 'protected';
        }
        return 'public';
    }

    private function resolveParamVisibility(Node\Param $param): string
    {
        if ($param->isPrivate()) {
            return 'private';
        }
        if ($param->isProtected()) {
            return 'protected';
        }
        return 'public';
    }

    public function resolveType(Node $typeNode): string
    {
        if ($typeNode instanceof NullableType) {
            return '?' . $this->resolveType($typeNode->type);
        }
        if ($typeNode instanceof UnionType) {
            return implode('|', array_map([$this, 'resolveType'], $typeNode->types));
        }
        if ($typeNode instanceof IntersectionType) {
            return implode('&', array_map([$this, 'resolveType'], $typeNode->types));
        }
        if ($typeNode instanceof Name) {
            return $typeNode->toCodeString();
        }
        if ($typeNode instanceof Identifier) {
            return $typeNode->toString();
        }
        // TODO: unknown type node — may produce garbage for future PHP syntax
        return $typeNode->getType();
    }

    public function resolveDefault(Expr $default): string
    {
        if ($default instanceof ConstFetch) {
            return $default->name->toCodeString();
        }
        if ($default instanceof String_) {
            return "'" . $default->value . "'";
        }
        if ($default instanceof Int_) {
            return (string)$default->value;
        }
        if ($default instanceof Float_) {
            return (string)$default->value;
        }
        if ($default instanceof Array_) {
            return '[]';
        }
        if ($default instanceof UnaryMinus) {
            return '-' . $this->resolveDefault($default->expr);
        }
        return 'null';
    }
}
