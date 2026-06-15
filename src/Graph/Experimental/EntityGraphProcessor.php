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
    public const REL_PROPERTY_TYPE = 'property_type';
    public const REL_RETURN_TYPE = 'return_type';
    public const REL_PARAM_TYPE = 'param_type';
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

        $memberId = $this->command->insertMember(
            entityId: $entityId,
            name: $member->name,
            memberType: $memberType,
            visibility: $member->visibility,
            isStatic: in_array('static', $member->attributes, true),
            isAbstract: in_array('abstract', $member->attributes, true),
            isFinal: in_array('final', $member->attributes, true),
            isReadonly: in_array('readonly', $member->attributes, true),
            declaredType: $member->dataType,
            typeNullable: $member->dataType !== null && $this->typeParser->isNullable($member->dataType),
            defaultValue: $member->value,
            returnType: $member->returnType,
            returnTypeNullable: $member->returnType !== null && $this->typeParser->isNullable($member->returnType),
        );

        $this->processMemberTypeRelationships($entityId, $memberId, $member, $memberType);

        foreach ($member->parameters as $position => $param) {
            $this->processParameter($memberId, $param, $position);
            if ($param->type !== null) {
                $this->addTypeRelationships($entityId, $param->type, self::REL_PARAM_TYPE, $memberId);
            }
        }

        foreach ($member->creates as $createdClass) {
            $this->addRelationship($entityId, $memberId, $createdClass, self::REL_CREATES);
        }

        foreach ($member->calls as $call) {
            $this->processCall($entityId, $memberId, $call);
        }
    }

    private function processMemberTypeRelationships(int $entityId, int $memberId, MemberNode $member, string $memberType): void
    {
        if ($member->dataType !== null && $memberType === 'property') {
            $this->addTypeRelationships($entityId, $member->dataType, self::REL_PROPERTY_TYPE, $memberId);
        }

        if ($member->returnType !== null && ($memberType === 'method' || $memberType === 'function')) {
            $this->addTypeRelationships($entityId, $member->returnType, self::REL_RETURN_TYPE, $memberId);
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
        $this->command->insertParameter(
            memberId: $memberId,
            name: $param->name,
            declaredType: $param->type,
            typeNullable: $param->type !== null && $this->typeParser->isNullable($param->type),
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

    private function addTypeRelationships(int $entityId, string $type, string $relType, ?int $memberId): void
    {
        $types = $this->typeParser->extractClassTypes($type);
        foreach ($types as $classType) {
            $this->addRelationship($entityId, $memberId, $classType, $relType);
        }
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
}
