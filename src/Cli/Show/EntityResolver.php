<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Show;

use SineFine\Mnemosyne\Cli\Error\ExitCode;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;

final class EntityResolver
{
    private string $resolvedFqn = '';

    public function resolve(string $name, GraphQuery $query): int
    {
        if (str_contains($name, '\\')) {
            $id = $query->findEntityId($name);
            if ($id !== null) {
                $this->resolvedFqn = $name;
                return $id;
            }

            fwrite(STDERR, "Error: Entity not found: $name\n");
            exit(ExitCode::DATA_ERROR);
        }

        $id = $query->findEntityId($name);
        if ($id !== null) {
            $this->resolvedFqn = $name;
            return $id;
        }

        $matches = $query->findEntitiesByShortName($name);

        if (count($matches) === 0) {
            fwrite(STDERR, "Error: Entity not found: $name\n");
            exit(ExitCode::DATA_ERROR);
        }

        if (count($matches) === 1) {
            $this->resolvedFqn = (string) $matches[0]['fqn'];
            return (int) $matches[0]['id'];
        }

        fwrite(STDERR, "Error: Ambiguous entity name '$name'. Matches:\n");
        foreach ($matches as $match) {
            fwrite(STDERR, "  " . $match['fqn'] . "\n");
        }
        exit(ExitCode::WRONG_USAGE);
    }

    public function lastResolvedFqn(): string
    {
        return $this->resolvedFqn;
    }
}
