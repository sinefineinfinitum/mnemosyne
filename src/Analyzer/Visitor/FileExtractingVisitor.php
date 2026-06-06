<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

class FileExtractingVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<string, string>
     */
    private array $globals = [];

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Function_ || $node instanceof ClassLike) {
            return NodeVisitor::DONT_TRAVERSE_CHILDREN;
        }
        if ($node instanceof Node\Expr\Variable
            && is_string($node->name)
            && !in_array($node->name, ['this', '_GET', '_POST', '_REQUEST', '_SERVER', '_SESSION', '_COOKIE', '_FILES', '_ENV', 'GLOBALS'], true)
        ) {
            $this->globals[$node->name] = $node->name;
        }
        return null;
    }

    /**
     * @return list<string>
     */
    public function globals(): array
    {
        $globals = array_values($this->globals);
        sort($globals);

        return $globals;
    }
}
