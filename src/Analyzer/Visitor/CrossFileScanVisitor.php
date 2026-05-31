<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\NodeVisitorAbstract;

final class CrossFileScanVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<string, list<string>> 
     */
    private array $interfacesImplemented = [];

    /**
     * @var array<string, list<string>> 
     */
    private array $traitsUsed = [];

    private ?string $currentClass = null;

    public function enterNode(Node $node)
    {
        if ($node instanceof Class_ && !$node->isAnonymous() && $node->namespacedName !== null) {
            $this->currentClass = $node->namespacedName->toString();

            foreach ($node->implements as $interface) {
                $interfaceFqn = ltrim($interface->toCodeString(), '\\');
                $this->interfacesImplemented[$interfaceFqn][] = $this->currentClass;
            }
        }

        if ($node instanceof TraitUse && $this->currentClass !== null) {
            foreach ($node->traits as $trait) {
                $traitFqn = ltrim($trait->toCodeString(), '\\');
                $this->traitsUsed[$traitFqn][] = $this->currentClass;
            }
        }

        return null;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getInterfacesImplemented(): array
    {
        foreach ($this->interfacesImplemented as $interface => $classes) {
            $classes = array_values(array_unique($classes));
            sort($classes);
            $this->interfacesImplemented[$interface] = $classes;
        }
        ksort($this->interfacesImplemented);
        return $this->interfacesImplemented;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getTraitsUsed(): array
    {
        foreach ($this->traitsUsed as $trait => $classes) {
            $classes = array_values(array_unique($classes));
            sort($classes);
            $this->traitsUsed[$trait] = $classes;
        }
        ksort($this->traitsUsed);
        return $this->traitsUsed;
    }
}
