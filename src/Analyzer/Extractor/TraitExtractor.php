<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Extractor;

use PhpParser\Node;
use PhpParser\Node\Stmt\Trait_;

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

        return [
            'fqn' => $this->astHelper->resolveFqn($this->namespace, $name),
            'type' => 'trait',
            'modifiers' => [],
            'parentClass' => null,
            'interfaces' => [],
            'constants' => $this->astHelper->extractConstants($node),
            'properties' => $this->astHelper->extractProperties($node),
            'methods' => $this->astHelper->extractMethods($node),
        ];
    }
}
