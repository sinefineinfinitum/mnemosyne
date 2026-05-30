<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer;

use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\UnionType;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;

class FileExtractor
{
    /**
     * @param  array<int, Node> $ast
     * @return array<int, array<string, mixed>>
     */
    public function extractFunctions(array $ast): array
    {
        $functions = [];

        foreach ($ast as $node) {
            if (!$node instanceof Function_) {
                continue;
            }

            $params = [];
            foreach ($node->getParams() as $param) {
                $params[] = [
                    'name' => $param->var->name ?? '',
                    'type' => $param->type !== null ? $this->resolveType($param->type) : null,
                    'typeNullable' => $param->type !== null && $param->type instanceof NullableType,
                    'defaultValue' => $param->default !== null ? $this->resolveDefault($param->default) : null,
                    'isVariadic' => $param->variadic,
                    'isPassedByReference' => $param->byRef,
                ];
            }

            $functions[] = [
                'name' => $node->name->toString(),
                'parameters' => $params,
                'returnType' => $node->returnType !== null ? $this->resolveType($node->returnType) : null,
                'returnTypeNullable' => $node->returnType !== null && $node->returnType instanceof NullableType,
            ];
        }

        return $functions;
    }

    /**
     * @param  array<int, Node> $ast
     * @return string[]
     */
    public function extractGlobals(array $ast): array
    {
        $visitor = new class extends NodeVisitorAbstract {
            /**
             * @var array<string, string> 
             */
            public array $globals = [];

            public function leaveNode(Node $node)
            {
                if ($node instanceof Variable
                    && is_string($node->name)
                    && !in_array($node->name, ['this', '_GET', '_POST', '_REQUEST', '_SERVER', '_SESSION', '_COOKIE', '_FILES', '_ENV', 'GLOBALS'], true)
                ) {
                    $this->globals[$node->name] = $node->name;
                }
                return null;
            }
        };

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $globals = array_values($visitor->globals);
        sort($globals);

        return $globals;
    }

    private function resolveType(Node $typeNode): string
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

        return $typeNode->getType();
    }

    private function resolveDefault(Expr $default): string
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
