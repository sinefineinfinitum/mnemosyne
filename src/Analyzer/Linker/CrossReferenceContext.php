<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Linker;

final class CrossReferenceContext
{
    /**
     * @param CrossReferenceIndex   $index
     * @param array<string, string> $fqnToDocPath fqn => relative doc path
     */
    public function __construct(
        private CrossReferenceIndex $index,
        private array $fqnToDocPath,
    ) {
    }

    public function getIndex(): CrossReferenceIndex
    {
        return $this->index;
    }

    /**
     * @return array<string, string>
     */
    public function getFqnToDocPath(): array
    {
        return $this->fqnToDocPath;
    }
}
