<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\UnionType;
use PhpParser\NodeVisitorAbstract;

final class DependencyCollectingVisitor extends NodeVisitorAbstract
{
    private const BUILTIN_TYPES = [
        'string',
        'int',
        'float',
        'bool',
        'array',
        'void',
        'null',
        'object',
        'mixed',
        'never',
        'true',
        'false',
    ];

    /**
     * @var string[]
     */
    private array $deps = [];

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Param && $node->type !== null) {
            $this->processTypeNode($node->type);
        }

        if ($node instanceof Node\Stmt\Class_ && $node->extends !== null) {
            $this->addDep($node->extends->toCodeString());
        }

        if ($node instanceof Node\Stmt\Class_) {
            foreach ($node->implements as $iface) {
                $this->addDep($iface->toCodeString());
            }
        }

        if ($node instanceof FunctionLike) {
            $returnType = $node->getReturnType();
            if ($returnType !== null) {
                $this->processTypeNode($returnType);
            }
        }

        if ($node instanceof Property && $node->type !== null) {
            $this->processTypeNode($node->type);
        }

        return null;
    }

    private function processTypeNode(Node $typeNode): void
    {
        if ($typeNode instanceof Name) {
            $fqn = $typeNode->toCodeString();

            if (!in_array(strtolower($fqn), self::BUILTIN_TYPES, true)) {
                $this->addDep($fqn);
            }

            return;
        }

        if ($typeNode instanceof UnionType || $typeNode instanceof IntersectionType) {
            foreach ($typeNode->types as $innerType) {
                $this->processTypeNode($innerType);
            }

            return;
        }

        if ($typeNode instanceof NullableType) {
            $this->processTypeNode($typeNode->type);

            return;
        }
    }

    /**
     * @return string[]
     */
    public function dependencies(): array
    {
        $deps = $this->deps;
        sort($deps);

        return $deps;
    }

    private function addDep(string $fqn): void
    {
        if (!in_array($fqn, $this->deps, true)) {
            $this->deps[] = $fqn;
        }
    }
}
