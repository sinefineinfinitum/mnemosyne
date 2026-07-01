<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer;

use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Const_;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeTraverser;
use SineFine\Mnemosyne\Analyzer\Visitor\FileExtractingVisitor;

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
                    'type' => $param->type !== null ? $this->resolveType($param->type) : 'mixed',
                    'defaultValue' => $param->default !== null ? $this->resolveDefault($param->default) : null,
                    'isVariadic' => $param->variadic,
                    'isPassedByReference' => $param->byRef,
                ];
            }

            $functions[] = [
                'name' => $node->name->toString(),
                'parameters' => $params,
                'returnType' => $node->returnType !== null ? $this->resolveType($node->returnType) : null,
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
        $visitor = new FileExtractingVisitor();

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        $globals = $visitor->globals();
        sort($globals);

        return $globals;
    }

    /**
     * Extracts PHP constants from file-level code.
     * Note: Only handles simple const and define() calls.
     * Does NOT handle dynamic defines or conditional constants.
     *
     * @param  array<int, Node> $ast
     * @return array<int, array<string, mixed>>
     */
    public function extractConstants(array $ast): array
    {
        $constants = [];

        foreach ($ast as $node) {
            if ($node instanceof Const_) {
                foreach ($node->consts as $const) {
                    $constants[] = [
                        'name' => $const->name->toString(),
                        'value' => $this->resolveDefault($const->value),
                    ];
                }
                continue;
            }

            $funcCall = null;
            if ($node instanceof Expression && $node->expr instanceof FuncCall) {
                $funcCall = $node->expr;
            } elseif ($node instanceof FuncCall) {
                $funcCall = $node;
            }

            if ($funcCall !== null && $funcCall->name instanceof Name && $funcCall->name->toString() === 'define') {
                if (isset($funcCall->args[0]) && $funcCall->args[0] instanceof Arg
                    && isset($funcCall->args[1]) && $funcCall->args[1] instanceof Arg
                ) {
                    $nameArg = $funcCall->args[0]->value;
                    $valueArg = $funcCall->args[1]->value;
                    $name = $nameArg instanceof String_
                        ? $nameArg->value
                        : $this->resolveDefault($nameArg);
                    $constants[] = [
                        'name' => $name,
                        'value' => $this->resolveDefault($valueArg),
                    ];
                }
            }
        }

        usort($constants, fn($a, $b) => strcmp($a['name'], $b['name']));
        return $constants;
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
        if ($typeNode instanceof Name) {
            return $typeNode->toCodeString();
        }
        if ($typeNode instanceof Node\Identifier) {
            return $typeNode->toString();
        }

        // TODO: unknown type node — may produce garbage for future PHP syntax
        return $typeNode->getType();
    }

    private function resolveDefault(Expr $default): string
    {
        if ($default instanceof Node\Expr\ConstFetch) {
            return $default->name->toCodeString();
        }
        if ($default instanceof String_) {
            return "'" . $default->value . "'";
        }
        if ($default instanceof Node\Scalar\Int_) {
            return (string)$default->value;
        }
        if ($default instanceof Node\Scalar\Float_) {
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
}
