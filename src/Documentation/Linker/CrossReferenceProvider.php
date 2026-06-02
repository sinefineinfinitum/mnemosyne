<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Linker;

use SineFine\Ponymator\Analyzer\CombinedAnalysisResult;
use SineFine\Ponymator\Analyzer\Linker\CrossReferenceContext;

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
        private CombinedAnalysisResult $analysis,
        private ?CrossReferenceContext $context,
        private ?DocLinker $linker,
        private string $currentDocPath
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

        return new CrossReference(
            $this->dependencies,
            $usedByLinks,
            $this->typeLinkResolver,
            $creations
        );
    }
}
