<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Linker;

use RuntimeException;

final class CrossReferenceIndex
{
    /**
     * @var array<string, list<string>>
     */
    private array $references = [];

    /**
     * @var array<string, bool>
     */
    private array $projectFqns = [];

    private bool $frozen = false;

    /**
     * @throws RuntimeException
     */
    public function addReference(string $referencedFqn, string $referencingFqn): void
    {
        if ($this->frozen) {
            throw new RuntimeException('CrossReferenceIndex is frozen after build');
        }
        if ($referencedFqn === $referencingFqn) {
            return;
        }
        $this->references[$referencedFqn][] = $referencingFqn;
    }

    /**
     * @return list<string>
     */
    public function getUsedBy(string $fqn): array
    {
        $refs = array_values(
            array_unique(
                array_filter(
                    $this->references[$fqn] ?? [],
                    fn(string $r) => isset($this->projectFqns[$r]),
                )
            )
        );
        sort($refs);
        return $refs;
    }

    /**
     * @param list<string> $projectFqns List of project entity FQNs for vendor filtering
     */
    public function freeze(array $projectFqns = []): void
    {
        foreach ($this->references as $fqn => $refs) {
            $refs = array_values(array_unique($refs));
            sort($refs);
            $this->references[$fqn] = $refs;
        }
        $this->projectFqns = array_combine(
            $projectFqns,
            array_fill(0, count($projectFqns), true),
        );
        $this->frozen = true;
    }
}
