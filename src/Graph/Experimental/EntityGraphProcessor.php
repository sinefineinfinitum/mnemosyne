<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Graph\Experimental;

use SineFine\Mnemosyne\Msv1Parser\Ast\CallNode;
use SineFine\Mnemosyne\Msv1Parser\Ast\EntityNode;
use SineFine\Mnemosyne\Msv1Parser\Ast\MemberNode;
use SineFine\Mnemosyne\Msv1Parser\Ast\ParameterNode;

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

    /**
     * @var array<string, int> fqn => id
     */
    private array $entityIds = [];

    public function __construct(
        private GraphCommand $command,
        private NamespaceResolver $namespaceResolver,
    ) {
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

        if ($memberType === 'method') {
            $this->processMethodMember($entityId, $member);
        } else {
            $this->processPropertyMember($entityId, $member);
        }
    }

    private function processMethodMember(int $entityId, MemberNode $member): void
    {
        $returnTypeName = $member->returnType;

        $methodId = $this->command->insertMethod(
            entityId: $entityId,
            name: $member->name,
            visibility: $member->visibility,
            isStatic: in_array('static', $member->attributes, true),
            isAbstract: in_array('abstract', $member->attributes, true),
            isFinal: in_array('final', $member->attributes, true),
            returnTypeEntityId: $this->resolveEntityId($returnTypeName),
            returnTypeName: $returnTypeName,
        );

        foreach ($member->parameters as $position => $param) {
            $this->processParameter($methodId, $param, $position);
        }

        foreach ($member->creates as $createdClass) {
            $this->addRelationship($entityId, $methodId, $createdClass, self::REL_CREATES);
        }

        foreach ($member->calls as $call) {
            $this->processCall($entityId, $methodId, $call);
        }
    }

    private function processPropertyMember(int $entityId, MemberNode $member): void
    {
        $declaredType = self::stripNullablePrefix($member->dataType);

        $this->command->insertProperty(
            entityId: $entityId,
            name: $member->name,
            memberType: $this->mapPropertyMemberType($member->type),
            visibility: $member->visibility,
            isStatic: in_array('static', $member->attributes, true),
            isReadonly: in_array('readonly', $member->attributes, true),
            declaredTypeEntityId: $this->resolveEntityId($declaredType),
            declaredTypeName: $declaredType,
            defaultValue: $member->value,
        );
    }

    private function processCall(int $entityId, int $methodId, CallNode $call): void
    {
        $relType = $this->callToRelType($call);
        $targetFqn = $call->targetFQCN !== '' ? $call->targetFQCN : null;

        $this->addRelationship($entityId, $methodId, $targetFqn, $relType);
    }

    private function processParameter(int $methodId, ParameterNode $param, int $position): void
    {
        $declaredType = self::stripNullablePrefix($param->type);

        $this->command->insertParameter(
            methodId: $methodId,
            name: $param->name,
            declaredTypeEntityId: $this->resolveEntityId($declaredType),
            declaredTypeName: $declaredType,
            defaultValue: $param->value,
            isVariadic: $param->variadic,
            isPassedByReference: $param->byRef,
            position: $position,
        );
    }

    private function resolveEntityId(?string $typeName): ?int
    {
        if ($typeName === null || $typeName === '') {
            return null;
        }
        $normalized = ltrim($typeName, '\\');
        return $this->entityIds[$normalized] ?? null;
    }

    private function mapEntityType(string $msv1Type): string
    {
        return match ($msv1Type) {
            'interface', 'trait', 'enum' => $msv1Type,
            default => 'class',
        };
    }

    private function mapMemberType(string $msv1Type): string
    {
        return match ($msv1Type) {
            'function' => 'method',
            'method' => 'method',
            default => 'property',
        };
    }

    private function mapPropertyMemberType(string $msv1Type): string
    {
        return match ($msv1Type) {
            'constant' => 'constant',
            'case', 'enum_case' => 'case',
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

    private function addRelationship(int $sourceId, ?int $methodId, ?string $targetFqn, string $type): void
    {
        $this->command->insertRelationship(
            sourceId: $sourceId,
            targetId: null,
            targetFqn: $targetFqn,
            type: $type,
            sourceMethodId: $methodId,
        );
    }

    private static function stripNullablePrefix(?string $type): ?string
    {
        if ($type !== null && str_starts_with($type, '?')) {
            return substr($type, 1);
        }

        return $type;
    }
}
