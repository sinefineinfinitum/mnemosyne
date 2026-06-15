<?php declare(strict_types=1);

namespace SineFine\Ponymator\Cli\Show;

use SineFine\Ponymator\Cli\Command;
use SineFine\Ponymator\Cli\Error\ExitCode;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;

final class ShowEntityCommand
{
    public function execute(Command $cmd, GraphQuery $query): void
    {
        if (count($cmd->positionalArgs) < 1) {
            fwrite(STDERR, "Error: show entity requires a entity name\n");
            exit(ExitCode::WRONG_USAGE);
        }

        $name = $cmd->positionalArgs[0];

        $resolver = new EntityResolver();
        $entityId = $resolver->resolve($name, $query);

        $entity = $query->findEntity($resolver->lastResolvedFqn());
        if ($entity === null) {
            fwrite(STDERR, "Error: Entity not found after resolution\n");
            exit(ExitCode::DATA_ERROR);
        }

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

        $external = [];
        $outgoingRows = [];
        foreach ($outgoing as $rel) {
            if ($rel['target_id'] === null) {
                $fqn = $rel['target_fqn'] ?? '';
                if ($fqn !== '') {
                    $external[] = $fqn;
                }
            } else {
                $outgoingRows[] = $rel;
            }
        }

        $incomingRows = $incoming;

        $structuralTypes = ['extends', 'implements', 'uses_trait'];

        $outgoingStructural = [];
        $outgoingCalls = [];
        foreach ($outgoingRows as $rel) {
            if (in_array($rel['type'], $structuralTypes, true)) {
                $outgoingStructural[] = $rel;
            } else {
                $outgoingCalls[] = $rel;
            }
        }

        $structuralIncoming = [];
        $callIncoming = [];
        foreach ($incomingRows as $rel) {
            if (in_array($rel['type'], $structuralTypes, true)) {
                $structuralIncoming[] = $rel;
            } else {
                $callIncoming[] = $rel;
            }
        }

        $external = array_values(array_unique($external));
        $inCount = count($callIncoming);

        $this->printEntityType($entity, $modifiers, $filePath);

        $this->printOutgoingStructural($outgoingStructural);

        $this->printStructuralIncoming($structuralIncoming);

        $this->printMembers($members, $outgoingCalls, $query);

        $this->printUsedBy($inCount, $callIncoming);

        $this->printExternal($external);
    }

    /**
     * @param string[] $external
     */
    private function printExternal(array $external): void
    {
        echo "\n  External (" . count($external) . "):\n";
        foreach ($external as $fqn) {
            echo "    $fqn\n";
        }
    }

    /**
     * @param int                              $inCount
     * @param array<int, array<string, mixed>> $callIncoming
     */
    private function printUsedBy(int $inCount, array $callIncoming): void
    {
        echo "\n  Used by (" . $inCount . "):\n";
        foreach ($callIncoming as $rel) {
            $type = $rel['type'];
            $sourceFqn = $rel['source_fqn'] ?? '';
            $memberName = $rel['source_member_name'];

            if (str_starts_with($type, 'call_dynamic_') && $memberName !== null) {
                echo "    $sourceFqn->$memberName()\n";
            } elseif (str_starts_with($type, 'call_static_') && $memberName !== null) {
                echo "    [$type] $sourceFqn::$memberName\n";
            } else {
                $line = "    [$type] $sourceFqn";
                if ($memberName !== null) {
                    $line .= " +$memberName";
                }
                echo $line . "\n";
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $structuralIncoming
     */
    private function printStructuralIncoming(array $structuralIncoming): void
    {
        if (!empty($structuralIncoming)) {
            $inheritors = array_filter($structuralIncoming, fn($r) => $r['type'] === 'extends');
            $implementers = array_filter($structuralIncoming, fn($r) => $r['type'] === 'implements');
            $traitUsers = array_filter($structuralIncoming, fn($r) => $r['type'] === 'uses_trait');

            if ($inheritors) {
                echo "Inheritors (" . count($inheritors) . "):\n";
                foreach ($inheritors as $rel) {
                    echo "    " . $rel['source_fqn'] . "\n";
                }
            }

            if ($implementers) {
                echo "Implementers (" . count($implementers) . "):\n";
                foreach ($implementers as $rel) {
                    echo "    " . $rel['source_fqn'] . "\n";
                }
            }

            if ($traitUsers) {
                echo "Used by traits (" . count($traitUsers) . "):\n";
                foreach ($traitUsers as $rel) {
                    echo "    " . $rel['source_fqn'] . "\n";
                }
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $outgoingStructural
     */
    private function printOutgoingStructural(array $outgoingStructural): void
    {
        if (!empty($outgoingStructural)) {
            $parent = array_filter($outgoingStructural, fn($r) => $r['type'] === 'extends');
            $interfaces = array_filter($outgoingStructural, fn($r) => $r['type'] === 'implements');
            $traits = array_filter($outgoingStructural, fn($r) => $r['type'] === 'uses_trait');

            if ($parent) {
                echo "Extends:\n";
                foreach ($parent as $rel) {
                    echo "    " . $rel['target_fqn_resolved'] . "\n";
                }
            }

            if ($interfaces) {
                echo "Implements:\n";
                foreach ($interfaces as $rel) {
                    echo "    " . $rel['target_fqn_resolved'] . "\n";
                }
            }

            if ($traits) {
                echo "Uses traits:\n";
                foreach ($traits as $rel) {
                    echo "    " . $rel['target_fqn_resolved'] . "\n";
                }
            }
        }
    }

    /**
     * @param array<int, array<string, mixed>> $members
     * @param array<int, array<string, mixed>> $outgoingCalls
     */
    private function printMembers(array $members, array $outgoingCalls, GraphQuery $query): void
    {
        $relationshipsByMember = [];
        foreach ($outgoingCalls as $rel) {
            $memberId = $rel['source_member_id'];
            if ($memberId === null) {
                continue;
            }
            $relationshipsByMember[$memberId][] = $rel;
        }

        $memberSections = [];
        foreach ($members as $member) {
            $type = $member['member_type'];
            if (!isset($memberSections[$type])) {
                $memberSections[$type] = [];
            }
            $member['relationships'] = $relationshipsByMember[$member['id']] ?? [];
            $memberSections[$type][] = $member;
        }

        $memberSectionOrder = ['property', 'case', 'constant', 'method'];

        foreach ($memberSectionOrder as $sectionType) {
            if (empty($memberSections[$sectionType])) {
                continue;
            }
            $sectionLabel = match ($sectionType) {
                'property' => 'Properties',
                'case' => 'Cases',
                'constant' => 'Constants',
                'method' => 'Methods',
            };
            $count = count($memberSections[$sectionType]);
            echo "\n" . $sectionLabel . " (" . $count . "):\n";
            foreach ($memberSections[$sectionType] as $member) {
                $name = $member['name'];
                $visibility = $member['visibility'];
                $isStatic = (int) $member['is_static'] === 1;
                $isAbstract = (int) $member['is_abstract'] === 1;
                $isFinal = (int) $member['is_final'] === 1;

                $mods = [];
                if ($isAbstract) {
                    $mods[] = 'abstract';
                }
                if ($isFinal) {
                    $mods[] = 'final';
                }
                if ($visibility !== null) {
                    $mods[] = $visibility;
                }
                if ($isStatic) {
                    $mods[] = 'static';
                }

                $prefix = !empty($mods) ? implode(' ', $mods) . ' ' : '';

                if ($sectionType === 'method') {
                    $params = $query->findParametersByMember((int) $member['id']);
                    $paramStrings = [];
                    foreach ($params as $param) {
                        $pStr = '';
                        if ($param['declared_type'] !== null) {
                            $pStr .= ($param['type_nullable'] ? '?' : '') . $param['declared_type'] . ' ';
                        }
                        if ($param['is_passed_by_reference']) {
                            $pStr .= '&';
                        }
                        if ($param['is_variadic']) {
                            $pStr .= '...';
                        }
                        $pStr .= '$' . $param['name'];
                        if ($param['default_value'] !== null) {
                            $pStr .= ' = ' . $param['default_value'];
                        }
                        $paramStrings[] = $pStr;
                    }
                    $signature = $prefix . 'function ' . $name . '(' . implode(', ', $paramStrings) . ')';
                    if ($member['return_type'] !== null) {
                        $signature .= ': ' . ($member['return_type_nullable'] ? '?' : '') . $member['return_type'];
                    }
                    echo "    $signature\n";
                } elseif ($sectionType === 'property') {
                    $typeStr = '';
                    if ($member['declared_type'] !== null) {
                        $typeStr = ($member['type_nullable'] ? '?' : '') . $member['declared_type'] . ' ';
                    }
                    echo "    $prefix$typeStr\$$name\n";
                } elseif ($sectionType === 'constant') {
                    echo "    $prefix" . "const $name\n";
                } elseif ($sectionType === 'case') {
                    echo "    case $name\n";
                }

                foreach ($member['relationships'] as $rel) {
                    $type = $rel['type'];
                    $target = $rel['target_fqn_resolved'] ?? '';

                    if (str_starts_with($type, 'call_dynamic_')) {
                        $strength = str_ends_with($type, '_strong') ? 'strong' : 'weak';
                        echo "      $strength $target\n";
                    } elseif (str_starts_with($type, 'call_static_')) {
                        $strength = str_ends_with($type, '_strong') ? 'strong' : 'weak';
                        echo "      $strength $target\n";
                    } elseif (str_starts_with($type, 'call_global_')) {
                        $strength = str_ends_with($type, '_strong') ? 'strong' : 'weak';
                        echo "      $strength $target\n";
                    } elseif ($type === 'creates') {
                        echo "      create $target\n";
                    } else {
                        echo "      [$type] $target\n";
                    }
                }
            }
        }
    }

    /**
     * @param array<string, mixed> $entity
     * @param string[]             $modifiers
     * @param string|null          $filePath
     */
    private function printEntityType(array $entity, array $modifiers, mixed $filePath): void
    {
        echo "Entity: " . $entity['fqn'] . "\n";

        $typeLine = $entity['type'];
        if (!empty($modifiers)) {
            $typeLine .= ' [' . implode(', ', $modifiers) . ']';
        }
        echo "Type: " . $typeLine . "\n";

        if ($filePath !== null) {
            echo "File: " . $filePath . "\n";
        }
    }
}
