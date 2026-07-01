<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Cli\Show;

use SineFine\Mnemosyne\Cli\Error\ExitCode;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;

final class EntityView
{
    /**
     * @param array<string, mixed>       $entity
     * @param string[]                   $modifiers
     * @param string|null                $filePath
     * @param list<array<string, mixed>> $outgoingStructural
     * @param list<array<string, mixed>> $structuralIncoming
     * @param list<array<string, mixed>> $members
     * @param list<array<string, mixed>> $outgoingCalls
     * @param list<array<string, mixed>> $callIncoming
     * @param string[]                   $external
     */
    public function __construct(
        public array $entity,
        public array $modifiers,
        public ?string $filePath,
        public array $outgoingStructural,
        public array $structuralIncoming,
        public array $members,
        public array $outgoingCalls,
        public array $callIncoming,
        public array $external,
        public GraphQuery $query,
    ) {
    }

    public static function load(string $fqn, GraphQuery $query): self
    {
        $entity = $query->findEntity($fqn);
        if ($entity === null) {
            fwrite(STDERR, "Error: Entity not found\n");
            exit(ExitCode::DATA_ERROR);
        }

        $entityId = (int) $entity['id'];

        $filePath = null;
        if ($entity['file_id'] !== null) {
            $file = $query->findFileById((int) $entity['file_id']);
            if ($file !== null) {
                $filePath = $file['relative_path'] ?? $file['path'];
            }
        }

        $modifiers = [];
        if ((int) $entity['is_abstract'] === 1) {
            $modifiers[] = 'abstract';
        }
        if ((int) $entity['is_final'] === 1) {
            $modifiers[] = 'final';
        }
        if ((int) $entity['is_readonly'] === 1) {
            $modifiers[] = 'readonly';
        }

        $outgoing = $query->findRelationshipsBySource($entityId);
        $incoming = $query->findRelationshipsByTarget($entityId);
        $members = $query->findMembersByEntity($entityId);

        $structuralTypes = ['extends', 'implements', 'uses_trait'];

        $outgoingStructural = [];
        $outgoingCalls = [];
        $external = [];
        foreach ($outgoing as $rel) {
            if ($rel['target_id'] === null) {
                $fqn = $rel['target_fqn'] ?? '';
                if ($fqn !== '') {
                    $external[] = $fqn;
                }
            }
            if (in_array($rel['type'], $structuralTypes, true)) {
                $outgoingStructural[] = $rel;
            } else {
                $outgoingCalls[] = $rel;
            }
        }
        $external = array_values(array_unique($external));

        $structuralIncoming = [];
        $callIncoming = [];
        foreach ($incoming as $rel) {
            if (in_array($rel['type'], $structuralTypes, true)) {
                $structuralIncoming[] = $rel;
            } else {
                $callIncoming[] = $rel;
            }
        }

        return new self(
            entity: $entity,
            modifiers: $modifiers,
            filePath: $filePath,
            outgoingStructural: $outgoingStructural,
            structuralIncoming: $structuralIncoming,
            members: $members,
            outgoingCalls: $outgoingCalls,
            callIncoming: $callIncoming,
            external: $external,
            query: $query,
        );
    }
}
