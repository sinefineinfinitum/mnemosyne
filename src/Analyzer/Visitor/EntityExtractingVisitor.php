<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Visitor;

use PhpParser\Node;
use PhpParser\NodeVisitorAbstract;
use SineFine\Ponymator\Analyzer\Extractor\EntityExtractorInterface;

class EntityExtractingVisitor extends NodeVisitorAbstract
{
    /**
     * @var array<int, array<string, mixed>>
     */
    private array $entities = [];

    /**
     * @param EntityExtractorInterface[] $extractors
     */
    public function __construct(
        private array $extractors,
    ) {
    }

    public function enterNode(Node $node)
    {
        foreach ($this->extractors as $extractor) {
            if ($extractor->supports($node)) {
                $this->entities[] = $extractor->extract($node);
                break;
            }
        }
        return null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function entities(): array
    {
        $entities = $this->entities;

        usort($entities, fn($a, $b) => strcmp($a['fqn'], $b['fqn']));

        return $entities;
    }
}
