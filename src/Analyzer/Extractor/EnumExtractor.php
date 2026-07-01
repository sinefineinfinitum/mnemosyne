<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer\Extractor;

use PhpParser\Node;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;

final class EnumExtractor implements EntityExtractorInterface
{
    public function __construct(
        private string $namespace,
        private AstHelper $astHelper
    ) {
    }

    public function supports(Node $node): bool
    {
        return $node instanceof Enum_;
    }

    /**
     * @phpstan-param Enum_ $node
     * @return        array<string, mixed>
     */
    public function extract(Node $node): array
    {
        $name = $node->name !== null ? $node->name->toString() : '';

        return [
            'fqn' => $this->astHelper->resolveFqn($this->namespace, $name),
            'type' => 'enum',
            'scalarType' => $node->scalarType?->toString(),
            'cases' => $this->enumCases($node),
            'modifiers' => [],
            'parentClass' => null,
            'interfaces' => $this->extractInterfaces($node),
            'constants' => $this->astHelper->extractConstants($node),
            'properties' => $this->astHelper->extractProperties($node),
            'methods' => $this->astHelper->extractMethods($node),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function enumCases(Enum_ $node): array
    {
        $cases = [];
        foreach ($node->stmts as $stmt) {
            if ($stmt instanceof EnumCase) {
                $cases[] = [
                    'name' => $stmt->name->toString(),
                    'value' => $stmt->expr !== null ? $this->astHelper->resolveDefault($stmt->expr) : null,
                ];
            }
        }
        return $cases;
    }

    /**
     * @return string[]
     */
    private function extractInterfaces(Enum_ $node): array
    {
        $interfaces = [];
        foreach ($node->implements as $interface) {
            $interfaces[] = ltrim($interface->toCodeString(), '\\');
        }
        sort($interfaces);
        return $interfaces;
    }
}
