<?php declare(strict_types=1);

namespace SineFine\Ponymator\Graph\Experimental;

use PDO;

/**
 * @experimental This API is experimental and may change without notice.\
 * @since        4.0.0
 */
final class GraphCommand
{
    private GraphQuery $query;
    public function __construct(
        private PDO $pdo,
    ) {
        $this->query = new GraphQuery($this->pdo);
    }

    public function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    public function commit(): void
    {
        $this->pdo->commit();
    }

    public function rollback(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
    }

    public function insertNamespace(string $fqn, string $label, ?int $parentId, int $depth): int
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO namespaces (fqn, label, parent_id, depth) VALUES (:fqn, :label, :parent_id, :depth)'
        );
        $stmt->execute(
            [
            'fqn' => $fqn,
            'label' => $label,
            'parent_id' => $parentId,
            'depth' => $depth,
            ]
        );
        return (int) $this->pdo->lastInsertId();
    }

    public function insertFile(string $path, ?string $relativePath, ?string $hash): int
    {
        $existing = $this->query->findFileId($path);
        if ($existing !== null) {
            return $existing;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO files (path, relative_path, hash) VALUES (:path, :relative_path, :hash)'
        );
        $stmt->execute(
            [
            'path' => $path,
            'relative_path' => $relativePath,
            'hash' => $hash,
            ]
        );
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * @param  string      $fqn
     * @param  string      $shortName
     * @param  string      $type
     * @param  int|null    $namespaceId
     * @param  int|null    $fileId
     * @param  string|null $parentClass
     * @param  string[]    $modifiers
     * @param  string|null $scalarType
     * @return int
     */
    public function insertEntity(
        string $fqn,
        string $shortName,
        string $type,
        ?int $namespaceId,
        ?int $fileId,
        ?string $parentClass,
        array $modifiers,
        ?string $scalarType = null
    ): int {
        $existing = $this->query->findEntityId($fqn);
        if ($existing !== null) {
            return $existing;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO entities (fqn, short_name, type, namespace_id, file_id, parent_class, is_abstract, is_final, is_readonly, scalar_type)
             VALUES (:fqn, :short_name, :type, :namespace_id, :file_id, :parent_class, :is_abstract, :is_final, :is_readonly, :scalar_type)'
        );
        $stmt->execute(
            [
            'fqn' => $fqn,
            'short_name' => $shortName,
            'type' => $type,
            'namespace_id' => $namespaceId,
            'file_id' => $fileId,
            'parent_class' => $parentClass,
            'is_abstract' => in_array('abstract', $modifiers, true) ? 1 : 0,
            'is_final' => in_array('final', $modifiers, true) ? 1 : 0,
            'is_readonly' => in_array('readonly', $modifiers, true) ? 1 : 0,
            'scalar_type' => $scalarType,
            ]
        );
        return (int) $this->pdo->lastInsertId();
    }

    public function insertMember(
        int $entityId,
        string $name,
        string $memberType,
        ?string $visibility,
        bool $isStatic,
        bool $isAbstract,
        bool $isFinal,
        bool $isReadonly,
        ?string $declaredType,
        ?string $defaultValue,
        ?string $returnType,
    ): int {
        $existing = $this->query->findMemberId($entityId, $name, $memberType);
        if ($existing !== null) {
            return $existing;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO members (entity_id, name, member_type, visibility, is_static, is_abstract, is_final, is_readonly, declared_type, default_value, return_type)
             VALUES (:entity_id, :name, :member_type, :visibility, :is_static, :is_abstract, :is_final, :is_readonly, :declared_type, :default_value, :return_type)'
        );
        $stmt->execute(
            [
            'entity_id' => $entityId,
            'name' => $name,
            'member_type' => $memberType,
            'visibility' => $visibility,
            'is_static' => $isStatic ? 1 : 0,
            'is_abstract' => $isAbstract ? 1 : 0,
            'is_final' => $isFinal ? 1 : 0,
            'is_readonly' => $isReadonly ? 1 : 0,
            'declared_type' => $declaredType,
            'default_value' => $defaultValue,
            'return_type' => $returnType,
            ]
        );
        return (int) $this->pdo->lastInsertId();
    }

    public function insertParameter(
        int $memberId,
        string $name,
        ?string $declaredType,
        ?string $defaultValue,
        bool $isVariadic,
        bool $isPassedByReference,
        int $position
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO parameters (member_id, name, declared_type, default_value, is_variadic, is_passed_by_reference, position)
             VALUES (:member_id, :name, :declared_type, :default_value, :is_variadic, :is_passed_by_reference, :position)'
        );
        $stmt->execute(
            [
            'member_id' => $memberId,
            'name' => $name,
            'declared_type' => $declaredType,
            'default_value' => $defaultValue,
            'is_variadic' => $isVariadic ? 1 : 0,
            'is_passed_by_reference' => $isPassedByReference ? 1 : 0,
            'position' => $position,
            ]
        );
        return (int) $this->pdo->lastInsertId();
    }

    public function insertType(
        string $ownerType,
        int $ownerId,
        string $name,
        ?int $entityId,
        bool $isUnion,
        bool $isIntersection,
        int $position
    ): int {
        $stmt = $this->pdo->prepare(
            'INSERT INTO types (owner_type, owner_id, name, entity_id, is_union, is_intersection, position)
             VALUES (:owner_type, :owner_id, :name, :entity_id, :is_union, :is_intersection, :position)'
        );
        $stmt->execute(
            [
            'owner_type' => $ownerType,
            'owner_id' => $ownerId,
            'name' => $name,
            'entity_id' => $entityId,
            'is_union' => $isUnion ? 1 : 0,
            'is_intersection' => $isIntersection ? 1 : 0,
            'position' => $position,
            ]
        );
        return (int) $this->pdo->lastInsertId();
    }

    public function insertRelationship(
        int $sourceId,
        ?int $targetId,
        ?string $targetFqn,
        string $type,
        ?int $sourceMemberId
    ): int {
        $existing = $this->query->findRelationshipId($sourceId, $targetId, $targetFqn, $type, $sourceMemberId);
        if ($existing !== null) {
            return $existing;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO relationships (source_id, target_id, target_fqn, type, source_member_id)
             VALUES (:source_id, :target_id, :target_fqn, :type, :source_member_id)'
        );
        $stmt->execute(
            [
            'source_id' => $sourceId,
            'target_id' => $targetId,
            'target_fqn' => $targetFqn,
            'type' => $type,
            'source_member_id' => $sourceMemberId,
            ]
        );
        return (int) $this->pdo->lastInsertId();
    }

    /**
     * Resolve relationship target_fqn references to actual entity IDs.
     */
    /**
     * @param array<string, int> $entityIdsByFqn
     */
    public function resolvePendingTargets(array $entityIdsByFqn): void
    {
        $selectStmt = $this->pdo->query(
            'SELECT id, target_fqn FROM relationships WHERE target_id IS NULL AND target_fqn IS NOT NULL'
        );
        if ($selectStmt === false) {
            return;
        }

        $updateStmt = $this->pdo->prepare(
            'UPDATE relationships SET target_id = :target_id WHERE id = :id AND target_id IS NULL'
        );

        while ($row = $selectStmt->fetch(PDO::FETCH_ASSOC)) {
            if (!is_array($row) || !isset($row['id'], $row['target_fqn'])) {
                continue;
            }
            $targetFqn = (string) $row['target_fqn'];
            $targetId = $entityIdsByFqn[$targetFqn] ?? null;
            if ($targetId !== null) {
                $updateStmt->execute(['target_id' => $targetId, 'id' => $row['id']]);
            }
        }
    }

    public function clear(): void
    {
        $this->pdo->exec('PRAGMA foreign_keys=OFF');
        foreach (array_reverse(Schema::TABLES) as $table) {
            $this->pdo->exec("DELETE FROM \"$table\"");
        }
        $this->pdo->exec('PRAGMA foreign_keys=ON');
    }
}
