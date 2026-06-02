<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Extractor;

use PhpParser\Node;
use PhpParser\Node\Stmt\Interface_;

final class InterfaceExtractor implements EntityExtractorInterface
{
    public function __construct(
        private string $namespace,
        private AstHelper $astHelper
    ) {
    }

    public function supports(Node $node): bool
    {
        return $node instanceof Interface_;
    }

    /**
     * @phpstan-param Interface_ $node
     * @return        array<string, mixed>
     */
    public function extract(Node $node): array
    {
        $name = $node->name !== null ? $node->name->toString() : '';

        $interfaces = [];
        foreach ($node->extends as $interface) {
            $interfaces[] = ltrim($interface->toCodeString(), '\\');
        }
        sort($interfaces);

        return [
            'fqn' => $this->astHelper->resolveFqn($this->namespace, $name),
            'type' => 'interface',
            'modifiers' => [],
            'parentClass' => null,
            'interfaces' => $interfaces,
            'constants' => $this->astHelper->extractConstants($node),
            'properties' => [],
            'methods' => $this->astHelper->extractMethods($node),
        ];
    }
}
