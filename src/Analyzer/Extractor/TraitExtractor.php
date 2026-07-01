<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer\Extractor;

use PhpParser\Node;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\Stmt\TraitUse;

final class TraitExtractor implements EntityExtractorInterface
{
    public function __construct(
        private string $namespace,
        private AstHelper $astHelper
    ) {
    }

    public function supports(Node $node): bool
    {
        return $node instanceof Trait_;
    }

    /**
     * @phpstan-param Trait_ $node
     * @return        array<string, mixed>
     */
    public function extract(Node $node): array
    {
        $name = $node->name !== null ? $node->name->toString() : '';

        $traits = [];
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof TraitUse) {
                foreach ($stmt->traits as $trait) {
                    $traits[] = ltrim($trait->toCodeString(), '\\');
                }
            }
        }
        sort($traits);

        return [
            'fqn' => $this->astHelper->resolveFqn($this->namespace, $name),
            'type' => 'trait',
            'modifiers' => [],
            'parentClass' => null,
            'interfaces' => [],
            'traits' => $traits,
            'constants' => $this->astHelper->extractConstants($node),
            'properties' => $this->astHelper->extractProperties($node),
            'methods' => $this->astHelper->extractMethods($node),
        ];
    }
}
