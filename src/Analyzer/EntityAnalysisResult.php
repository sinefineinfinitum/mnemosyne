<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer;

final class EntityAnalysisResult
{
    /**
     * @param array<int, array<string, mixed>>             $entities
     * @param string[]                                     $dependencies
     * @param array<string, array<string, list<string>>>   $creations
     * @param array<string, array<string, list<CallInfo>>> $calls        classFqcn => methodName => list<CallInfo>
     * @param array<string, list<CallInfo>>                $fileCalls    functionName => list<CallInfo>
     */
    public function __construct(
        private array $entities,
        private array $dependencies,
        private array $creations,
        private array $calls = [],
        private array $fileCalls = [],
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

    /**
     * @return array<string, array<string, list<CallInfo>>>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * @return array<string, list<CallInfo>>
     */
    public function getFileCalls(): array
    {
        return $this->fileCalls;
    }
}
