<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer\Linker;

final class CrossReferenceContext
{
    /**
     * @param CrossReferenceIndex     $index
     * @param array<string, string>   $fqnToDocPath     fqn => relative doc path
     * @param array<string, TypeInfo> $typeIndex        fqcn => TypeInfo
     * @param string[]                $projectFunctions list of project-defined function FQCNs
     */
    public function __construct(
        private CrossReferenceIndex $index,
        private array $fqnToDocPath,
        private array $typeIndex = [],
        private array $projectFunctions = [],
    ) {
    }

    public function getIndex(): CrossReferenceIndex
    {
        return $this->index;
    }

    /**
     * @return string[]
     */
    public function getProjectFunctions(): array
    {
        return $this->projectFunctions;
    }

    /**
     * @return array<string, string>
     */
    public function getFqnToDocPath(): array
    {
        return $this->fqnToDocPath;
    }

    /**
     * @return array<string, TypeInfo>
     */
    public function getTypeIndex(): array
    {
        return $this->typeIndex;
    }

    public function getTypeInfo(string $fqn): ?TypeInfo
    {
        $normalized = ltrim($fqn, '\\');
        return $this->typeIndex[$normalized] ?? null;
    }
}
