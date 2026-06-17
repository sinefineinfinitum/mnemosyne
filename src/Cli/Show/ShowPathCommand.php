<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli\Show;

use SineFine\Ponymator\Cli\Command;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;

final class ShowPathCommand
{
    public function execute(Command $cmd, GraphQuery $query): void
    {
        $fromName = $cmd->namedArgs['from'];
        $toName = $cmd->namedArgs['to'];
        $maxDepth = $cmd->depth ?? PHP_INT_MAX;

        $resolver = new EntityResolver();
        $sourceId = $resolver->resolve($fromName, $query);
        $sourceFqn = $resolver->lastResolvedFqn();

        $targetId = $resolver->resolve($toName, $query);
        $targetFqn = $resolver->lastResolvedFqn();

        if ($sourceId === $targetId) {
            echo "Path from " . $sourceFqn . " to " . $targetFqn . ":\n\n";
            echo "  " . $sourceFqn . "\n\n";
            echo "(0 hops)\n";
            return;
        }

        $path = $this->bfs($sourceId, $targetId, $maxDepth, $query);

        if ($path === null) {
            echo "No path found from " . $sourceFqn . " to " . $targetFqn . ".\n";
            return;
        }

        echo "Path from " . $sourceFqn . " to " . $targetFqn . ":\n\n  ";

        $hops = count($path) - 1;
        for ($i = 0; $i < count($path); $i++) {
            $node = $path[$i];
            echo $node['fqn'];

            if ($i < count($path) - 1) {
                $relType = $node['rel_type'];
                $direction = $node['direction'];

                if ($direction === 'forward') {
                    echo " --[" . $relType . "]--> ";
                } else {
                    echo " <--[" . $relType . "]-- ";
                }
            }
        }

        echo "\n\n(" . $hops . " hops)\n";
    }

    /**
     * @return list<array{fqn: string, rel_type: string, direction: string}>|null
     */
    private function bfs(int $sourceId, int $targetId, int $maxDepth, GraphQuery $query): ?array
    {
        $visited = [$sourceId => ['parent' => null, 'rel_type' => '', 'direction' => '']];
        $queue = [[$sourceId, 0]];

        while (!empty($queue)) {
            [$currentId, $currentDepth] = array_shift($queue);

            if ($currentDepth >= $maxDepth) {
                continue;
            }

            $outgoing = $query->findRelationshipsBySource($currentId);
            foreach ($outgoing as $rel) {
                if ($rel['target_id'] === null) {
                    continue;
                }
                $nextId = (int) $rel['target_id'];

                if (!isset($visited[$nextId])) {
                    $visited[$nextId] = [
                        'parent' => $currentId,
                        'rel_type' => $rel['type'],
                        'direction' => 'forward',
                    ];

                    if ($nextId === $targetId) {
                        return $this->reconstructPath($visited, $nextId, $query);
                    }

                    $queue[] = [$nextId, $currentDepth + 1];
                }
            }

            $incoming = $query->findRelationshipsByTarget($currentId);
            foreach ($incoming as $rel) {
                $nextId = (int) $rel['source_id'];

                if (!isset($visited[$nextId])) {
                    $visited[$nextId] = [
                        'parent' => $currentId,
                        'rel_type' => $rel['type'],
                        'direction' => 'reverse',
                    ];

                    if ($nextId === $targetId) {
                        return $this->reconstructPath($visited, $nextId, $query);
                    }

                    $queue[] = [$nextId, $currentDepth + 1];
                }
            }
        }

        return null;
    }

    /**
     * @param array<int, array{parent: ?int, rel_type: string, direction: string}> $visited
     *
     * @return list<array{fqn: string, rel_type: string, direction: string}>
     */
    private function reconstructPath(array $visited, int $targetId, GraphQuery $query): array
    {
        $ids = array_keys($visited);
        $entities = $query->findEntitiesByIds($ids);
        $fqnById = [];
        foreach ($entities as $entity) {
            $fqnById[(int) $entity['id']] = (string) $entity['fqn'];
        }

        $path = [];
        $current = $targetId;

        while ($current !== null) {
            $info = $visited[$current];
            $fqn = $fqnById[$current] ?? (string) $current;

            $hop = [
                'fqn' => $fqn,
                'rel_type' => $info['rel_type'],
                'direction' => $info['direction'],
            ];
            array_unshift($path, $hop);

            $current = $info['parent'];
        }

        return $path;
    }
}
