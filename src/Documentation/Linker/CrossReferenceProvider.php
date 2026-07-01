<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Documentation\Linker;

use SineFine\Mnemosyne\Analyzer\EntityAnalysisResult;
use SineFine\Mnemosyne\Analyzer\Linker\CrossReferenceContext;

final class CrossReferenceProvider
{
    /**
     * @var array<string, string>
     */
    private array $dependencies;

    /**
     * @var callable(string): ?string
     */
    private $typeLinkResolver;

    public function __construct(
        private EntityAnalysisResult   $analysis,
        private ?CrossReferenceContext $context,
        private ?DocLinker             $linker,
        private string                 $currentDocPath
    ) {
        $this->dependencies = $this->linker?->mapToLinks(
            $this->analysis->getDependencies(),
            $this->currentDocPath
        ) ?? [];

        $this->typeLinkResolver = $this->linker?->getResolver($this->currentDocPath)
            ?? fn(string $fqn): ?string => null;
    }

    public function getCrossReference(string $fqn): CrossReference
    {
        $normalizedFqn = ltrim($fqn, '\\');
        $usedBy = $this->context?->getIndex()->getUsedBy($normalizedFqn) ?? [];
        $usedByLinks = $this->linker?->mapToLinks($usedBy, $this->currentDocPath) ?? [];

        $creations = $this->analysis->getCreations()[$fqn] ?? [];
        $calls = $this->analysis->getCalls()[$fqn] ?? [];

        return new CrossReference(
            $this->dependencies,
            $usedByLinks,
            $this->typeLinkResolver,
            $creations,
            $calls,
        );
    }
}
