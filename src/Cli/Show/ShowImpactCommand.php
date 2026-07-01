<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Show;

use SineFine\Mnemosyne\Cli\Command;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;

final class ShowImpactCommand
{
    public const DEFAULT_DEPTH = 3;
    public function execute(Command $cmd, GraphQuery $query): void
    {
        $name = $cmd->namedArgs['entity'];
        $maxDepth = $cmd->depth ?? self::DEFAULT_DEPTH;

        $resolver = new EntityResolver();
        $entityId = $resolver->resolve($name, $query);
        $targetFqn = $resolver->lastResolvedFqn();

        $visited = [$entityId => true];
        $queue = [[$entityId, 0]];
        $discovered = [];

        while (!empty($queue)) {
            [$currentId, $currentDepth] = array_shift($queue);

            if ($currentDepth >= $maxDepth) {
                continue;
            }

            $incoming = $query->findRelationshipsByTarget($currentId);

            foreach ($incoming as $rel) {
                $sourceId = (int) $rel['source_id'];

                if (isset($visited[$sourceId])) {
                    continue;
                }

                $visited[$sourceId] = true;
                $distance = $currentDepth + 1;
                $discovered[] = [
                    'id' => $sourceId,
                    'fqn' => $rel['source_fqn'],
                    'distance' => $distance,
                ];

                $queue[] = [$sourceId, $distance];
            }
        }

        $ids = array_column($discovered, 'id');
        $entities = $query->findEntitiesByIds($ids);
        $typeById = [];
        foreach ($entities as $entity) {
            $typeById[(int) $entity['id']] = $entity['type'];
        }

        $byType = [];
        foreach ($discovered as $item) {
            $type = $typeById[$item['id']] ?? null;
            if ($type === null) {
                continue;
            }

            $distance = $item['distance'];

            if (!isset($byType[$type])) {
                $byType[$type] = [];
            }
            if (!isset($byType[$type][$distance])) {
                $byType[$type][$distance] = [];
            }
            $byType[$type][$distance][] = $item['fqn'];
        }

        $typeOrder = ['class', 'interface', 'trait', 'enum'];
        $ordered = [];
        foreach ($typeOrder as $type) {
            if (isset($byType[$type])) {
                $ordered[$type] = $byType[$type];
            }
        }
        foreach ($byType as $type => $data) {
            if (!isset($ordered[$type])) {
                $ordered[$type] = $data;
            }
        }

        foreach ($ordered as $type => &$distances) {
            ksort($distances);
            foreach ($distances as &$fqns) {
                sort($fqns);
            }
            unset($fqns);
        }
        unset($distances);

        $totalCount = 0;
        foreach ($ordered as $distances) {
            foreach ($distances as $fqns) {
                $totalCount += count($fqns);
            }
        }

        echo "Impact of changing: " . $targetFqn . "\n";
        echo "Total affected: " . $totalCount . "\n";

        if ($totalCount === 0) {
            echo "\nNo dependent entitys found.\n";
            return;
        }

        $typeLabels = [
            'class' => 'Classes',
            'interface' => 'Interfaces',
            'trait' => 'Traits',
            'enum' => 'Enums',
        ];

        foreach ($ordered as $type => $distances) {
            $count = 0;
            foreach ($distances as $fqns) {
                $count += count($fqns);
            }

            $label = $typeLabels[$type] ?? ucfirst($type) . 's';
            echo "\n" . $label . " (" . $count . "):\n";

            foreach ($distances as $distance => $fqns) {
                foreach ($fqns as $fqn) {
                    echo "  [distance " . $distance . "] " . $fqn . "\n";
                }
            }
        }
    }
}
