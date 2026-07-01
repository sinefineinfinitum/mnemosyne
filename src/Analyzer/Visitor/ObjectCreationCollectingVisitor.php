<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;

final class ObjectCreationCollectingVisitor extends NodeVisitorAbstract
{
    private ?string $currentClass = null;

    private ?string $currentMethod = null;

    /**
     * @var array<string, array<string, list<string>>>
     */
    private array $creates = [];

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Class_ || $node instanceof Trait_) {
            if ($node instanceof Class_ && $node->isAnonymous()) {
                return NodeVisitor::DONT_TRAVERSE_CHILDREN;
            }

            if ($node->namespacedName === null) {
                return null;
            }

            $this->currentClass = $node->namespacedName->toString();
            $this->currentMethod = null;
            return null;
        }

        if ($node instanceof ClassMethod && $this->currentClass !== null) {
            $this->currentMethod = $node->name->toString();
            return null;
        }

        if ($node instanceof New_ && $this->currentClass !== null && $this->currentMethod !== null) {
            if ($node->class instanceof Class_) {
                return null;
            }

            if ($node->class instanceof Node\Name) {
                $fqcn = $node->class->toString();
                if (!in_array($fqcn, $this->creates[$this->currentClass][$this->currentMethod] ?? [], true)) {
                    $this->creates[$this->currentClass][$this->currentMethod][] = $fqcn;
                }
            }

            return null;
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof ClassMethod && $this->currentClass !== null) {
            $this->currentMethod = null;
        }

        if ($node instanceof Class_ || $node instanceof Trait_) {
            if ($node instanceof Class_ && $node->isAnonymous()) {
                return null;
            }

            $this->currentClass = null;
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getCreates(string $fqcn): array
    {
        return $this->creates[$fqcn] ?? [];
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    public function getAllCreates(): array
    {
        return $this->creates;
    }
}
