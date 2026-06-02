<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer;

final class CombinedAnalysisResult
{
    /**
     * @param array<int, array<string, mixed>>           $entities
     * @param string[]                                   $dependencies
     * @param array<string, array<string, list<string>>> $creations
     */
    public function __construct(
        private array $entities,
        private array $dependencies,
        private array $creations,
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getEntities(): array
    {
        return $this->entities;
    }

    /**
     * @return string[]
     */
    public function getDependencies(): array
    {
        return $this->dependencies;
    }

    /**
     * @return array<string, array<string, list<string>>>
     */
    public function getCreations(): array
    {
        return $this->creations;
    }
}
