<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Extractor;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;

final class ClassExtractor implements EntityExtractorInterface
{
    public function __construct(
        private string $namespace,
        private AstHelper $astHelper
    ) {
    }

    public function supports(Node $node): bool
    {
        return $node instanceof Class_ && !$node->isAnonymous();
    }

    /**
     * @phpstan-param Class_ $node
     * @return        array<string, mixed>
     */
    public function extract(Node $node): array
    {
        $name = $node->name !== null ? $node->name->toString() : '';
        $interfaces = [];
        foreach ($node->implements as $iface) {
            $interfaces[] = ltrim($iface->toCodeString(), '\\');
        }
        sort($interfaces);

        return [
            'fqn' => $this->astHelper->resolveFqn($this->namespace, $name),
            'type' => 'class',
            'modifiers' => $this->modifiers($node),
            'parentClass' => $node->extends !== null ? ltrim($node->extends->toCodeString(), '\\') : null,
            'interfaces' => $interfaces,
            'constants' => $this->astHelper->extractConstants($node),
            'properties' => $this->astHelper->extractProperties($node),
            'methods' => $this->astHelper->extractMethods($node),
        ];
    }

    /**
     * @return string[]
     */
    private function modifiers(Class_ $node): array
    {
        $mods = [];
        if ($node->isAbstract()) {
            $mods[] = 'abstract';
        }
        if ($node->isFinal()) {
            $mods[] = 'final';
        }
        if ($node->isReadonly()) {
            $mods[] = 'readonly';
        }
        return $mods;
    }
}
