<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Visitor;

use PhpParser\Node;
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
        if ($node instanceof Node\Stmt\Use_) {
            foreach ($node->uses as $use) {
                $this->addDep($use->name->toCodeString());
            }
        }

        if ($node instanceof Node\Stmt\GroupUse) {
            $prefix = $node->prefix->toCodeString();

            foreach ($node->uses as $use) {
                $this->addDep($prefix . '\\' . $use->name->toCodeString());
            }
        }

        if ($node instanceof Node\Param && $node->type instanceof Node\Name) {
            $fqn = $node->type->toCodeString();

            if (!in_array(strtolower($fqn), self::BUILTIN_TYPES, true)) {
                $this->addDep($fqn);
            }
        }

        if ($node instanceof Node\Stmt\Class_ && $node->extends !== null) {
            $this->addDep($node->extends->toCodeString());
        }

        if ($node instanceof Node\Stmt\Class_) {
            foreach ($node->implements as $iface) {
                $this->addDep($iface->toCodeString());
            }
        }

        return null;
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
