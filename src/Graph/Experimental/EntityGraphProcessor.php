<?php declare(strict_types=1);

namespace SineFine\Ponymator\Graph\Experimental;

use Ponymator\Parser\Ast\CallNode;
use Ponymator\Parser\Ast\EntityNode;
use Ponymator\Parser\Ast\MemberNode;
use Ponymator\Parser\Ast\ParameterNode;

/**
 * @experimental This API is experimental and may change without notice.\
 * @since        4.0.0
 */
final class EntityGraphProcessor
{
    public const REL_EXTENDS = 'extends';
    public const REL_IMPLEMENTS = 'implements';
    public const REL_USES_TRAIT = 'uses_trait';
    public const REL_CREATES = 'creates';

    private PhpTypeParser $typeParser;

    /**
     * @var array<string, int> fqn => id
     */
    private array $entityIds = [];

    public function __construct(
        private GraphCommand $command,
        private NamespaceResolver $namespaceResolver,
    ) {
        $this->typeParser = new PhpTypeParser();
    }

    public function processEntity(EntityNode $entity, int $fileId): void
    {
        $fqn = $entity->name;
        $type = $this->mapEntityType($entity->type);

        $namespaceFqn = NamespaceResolver::extractFromFqn($fqn);
        $namespaceId = $namespaceFqn !== null ? $this->namespaceResolver->ensure($namespaceFqn) : null;

        $shortName = NamespaceResolver::extractShortName($fqn);

        $entityId = $this->command->insertEntity(
            fqn: $fqn,
            shortName: $shortName,
            type: $type,
            namespaceId: $namespaceId,
            fileId: $fileId,
            parentClass: !empty($entity->extends) ? $entity->extends[0] : null,
            modifiers: $entity->attributes,
            scalarType: null,
        );
        $this->entityIds[$fqn] = $entityId;

        $this->processStructuralRelationships($entityId, $entity);

        foreach ($entity->members as $member) {
            $this->processMember($entityId, $member);
        }
    }

    /**
     * @return array<string, int>
     */
    public function getEntityIds(): array
    {
        return $this->entityIds;
    }

    private function processStructuralRelationships(int $entityId, EntityNode $entity): void
    {
        foreach ($entity->extends as $parent) {
            $this->addRelationship($entityId, null, $parent, self::REL_EXTENDS);
        }

        foreach ($entity->implements as $interface) {
            $this->addRelationship($entityId, null, $interface, self::REL_IMPLEMENTS);
        }

        foreach ($entity->traits as $trait) {
            $this->addRelationship($entityId, null, $trait, self::REL_USES_TRAIT);
        }
    }

    private function processMember(int $entityId, MemberNode $member): void
    {
        $memberType = $this->mapMemberType($member->type);

        $declaredType = self::stripNullablePrefix($member->dataType);
        $returnType = $member->returnType;

        $memberId = $this->command->insertMember(
            entityId: $entityId,
            name: $member->name,
            memberType: $memberType,
            visibility: $member->visibility,
            isStatic: in_array('static', $member->attributes, true),
            isAbstract: in_array('abstract', $member->attributes, true),
            isFinal: in_array('final', $member->attributes, true),
            isReadonly: in_array('readonly', $member->attributes, true),
            declaredType: $declaredType,
            defaultValue: $member->value,
            returnType: $returnType,
        );

        if ($declaredType !== null && $memberType === 'property') {
            $this->insertTypes('property', $memberId, $declaredType);
        }
        if ($returnType !== null && ($memberType === 'method' || $memberType === 'function')) {
            $this->insertTypes('return', $memberId, $returnType);
        }

        foreach ($member->parameters as $position => $param) {
            $this->processParameter($memberId, $param, $position);
        }

        foreach ($member->creates as $createdClass) {
            $this->addRelationship($entityId, $memberId, $createdClass, self::REL_CREATES);
        }

        foreach ($member->calls as $call) {
            $this->processCall($entityId, $memberId, $call);
        }
    }

    private function processCall(int $entityId, int $memberId, CallNode $call): void
    {
        $relType = $this->callToRelType($call);
        $targetFqn = $call->targetFQCN !== '' ? $call->targetFQCN : null;

        $this->addRelationship($entityId, $memberId, $targetFqn, $relType);
    }

    private function processParameter(int $memberId, ParameterNode $param, int $position): void
    {
        $declaredType = self::stripNullablePrefix($param->type);

        $paramId = $this->command->insertParameter(
            memberId: $memberId,
            name: $param->name,
            declaredType: $declaredType,
            defaultValue: $param->value,
            /**
             * @phpstan-ignore nullCoalesce.property
             */
            isVariadic: (bool) ($param->isVariadic ?? false),
            /**
             * @phpstan-ignore nullCoalesce.property
             */
            isPassedByReference: (bool) ($param->byRef ?? false),
            position: $position,
        );

        if ($declaredType !== null) {
            $this->insertTypes('param', $paramId, $declaredType);
        }
    }

    private function mapEntityType(string $psv1Type): string
    {
        return match ($psv1Type) {
            'interface', 'trait', 'enum' => $psv1Type,
            default => 'class',
        };
    }

    private function mapMemberType(string $psv1Type): string
    {
        return match ($psv1Type) {
            'global_variable' => 'property',
            'function' => 'method',
            'enum_case' => 'case',
            'property', 'constant', 'method' => $psv1Type,
            default => 'property',
        };
    }

    private function callToRelType(CallNode $call): string
    {
        $suffix = $call->marker === 'strong' ? '_strong' : '_weak';
        $type = match ($call->type) {
            CallNode::TYPE_STATIC => 'call_static',
            CallNode::TYPE_GLOBAL => 'call_global',
            default => 'call_dynamic',
        };

        return $type . $suffix;
    }

    private function addRelationship(int $sourceId, ?int $memberId, ?string $targetFqn, string $type): void
    {
        $this->command->insertRelationship(
            sourceId: $sourceId,
            targetId: null,
            targetFqn: $targetFqn,
            type: $type,
            sourceMemberId: $memberId,
        );
    }

    private static function stripNullablePrefix(?string $type): ?string
    {
        if ($type !== null && str_starts_with($type, '?')) {
            return substr($type, 1);
        }

        return $type;
    }

    private function insertTypes(string $ownerType, int $ownerId, string $type): void
    {
        $components = $this->typeParser->parseAtomicTypes($type);
        foreach ($components as $component) {
            $name = $component['name'];
            $entityId = null;

            if ($name !== '' && !in_array(strtolower($name), PhpTypeParser::BUILTIN_TYPES, true)) {
                $normalized = ltrim($name, '\\');
                $entityId = $this->entityIds[$normalized] ?? null;
            }

            $this->command->insertType(
                ownerType: $ownerType,
                ownerId: $ownerId,
                name: $name,
                entityId: $entityId,
                isUnion: $component['is_union'],
                isIntersection: $component['is_intersection'],
                position: $component['position'],
            );
        }
    }
}
