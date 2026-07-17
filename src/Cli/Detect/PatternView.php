<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Detect;

use SineFine\Mnemosyne\Detector\Pattern\Model\PatternResult;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;

final class PatternView
{
    /** @var list<list<list<string>>> */
    public array $blocks;

    public function __construct(PatternResult $result, GraphQuery $query)
    {
        $blocks = [];

        foreach ($result->matches as $match) {
            $block = [];
            $hasNaming = false;

            foreach ($match->participants as $i => $participant) {
                $entity = $query->findEntityById($participant->entityId);
                $fqn = $entity !== null ? $entity['fqn'] : '#' . $participant->entityId;

                if (!$hasNaming) {
                    $hasNaming = $this->hasNaming($match->pattern->name(), $fqn, $match->pattern->roles());
                }

                $block[] = [
                    $i === 0 ? $match->pattern->name() : '',
                    $participant->role,
                    $fqn,
                ];
            }

            if ($hasNaming && $block !== []) {
                array_splice($block, 1, 0, [['+naming', '', '']]);
            }

            $blocks[] = $block;
        }

        $this->blocks = $blocks;
    }

    /**
     * @param  string   $patternName
     * @param  string   $fqn
     * @param  string[] $roles
     * @return bool
     */
    private function hasNaming(string $patternName, string $fqn, array $roles): bool
    {
        $parts = explode('\\', $fqn);
        $shortName = strtolower(end($parts));

        if (str_contains($shortName, strtolower($patternName))) {
            return true;
        }

        foreach ($roles as $role) {
            if (str_contains($shortName, strtolower($role))) {
                return true;
            }
        }

        return false;
    }
}
