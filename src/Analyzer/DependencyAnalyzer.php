<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use SineFine\Ponymator\Analyzer\Visitor\DependencyCollectingVisitor;

class DependencyAnalyzer
{
    /**
     * @param  array<int, Node> $ast
     * @return string[]
     */
    public function extractDependencies(array $ast): array
    {
        $visitor = new DependencyCollectingVisitor();

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->dependencies();
    }
}
