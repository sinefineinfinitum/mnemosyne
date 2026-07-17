<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Graph\Experimental;

use PDO;

/**
 * @experimental This API is experimental and may change without notice.
 */
final class GraphQuery
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    public function getPdo(): PDO
    {
        return $this->pdo;
    }

    /** @var array<string, ?int> */
    private array $entityIdCache = [];

    public function clearEntityCache(string $fqn, ?int $id): void
    {
        $this->entityIdCache[$fqn] = $id;
        unset($this->entityCache[$fqn]);
    }

    public function findNamespaceId(string $fqn): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM namespaces WHERE fqn = :fqn');
        $stmt->execute(['fqn' => $fqn]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !is_array($row) || !isset($row['id'])) {
            return null;
        }
        return (int) $row['id'];
    }

    public function findEntityId(string $fqn): ?int
    {
        if (array_key_exists($fqn, $this->entityIdCache)) {
            return $this->entityIdCache[$fqn];
        }
        $stmt = $this->pdo->prepare('SELECT id FROM entities WHERE fqn = :fqn');
        $stmt->execute(['fqn' => $fqn]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $id = ($row === false || !is_array($row) || !isset($row['id'])) ? null : (int) $row['id'];
        $this->entityIdCache[$fqn] = $id;
        return $id;
    }

    /** @var array<string, array<string, mixed>|null> */
    private array $entityCache = [];

    /**
     * @return array<string, mixed>|null
     */
    public function findEntity(string $fqn): ?array
    {
        if (array_key_exists($fqn, $this->entityCache)) {
            return $this->entityCache[$fqn];
        }
        $stmt = $this->pdo->prepare('SELECT * FROM entities WHERE fqn = :fqn');
        $stmt->execute(['fqn' => $fqn]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $result = ($row === false || !is_array($row)) ? null : $row;
        $this->entityCache[$fqn] = $result;
        return $result;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllEntities(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM entities ORDER BY fqn');
        if ($stmt === false) {
            return [];
        }
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param  int $id
     * @return array<string, mixed>
     */
    public function findEntityById(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM entities WHERE id = :id');
        $stmt->execute(['id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !is_array($row)) {
            return null;
        }
        return $row;
    }

    /**
     * @param  int[] $ids
     * @return list<array<string, mixed>>
     */
    public function findEntitiesByIds(array $ids): array
    {
        if (empty($ids)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $this->pdo->prepare("SELECT * FROM entities WHERE id IN ($placeholders) ORDER BY fqn");
        $stmt->execute(array_map('intval', $ids));
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compatibility shim: returns members in the old unified column format.
     *
     * @return list<array<string, mixed>>
     */
    public function findMembersByEntity(int $entityId): array
    {
        $members = [];

        $stmt = $this->pdo->prepare(
            "SELECT id, entity_id, name, 'method' AS member_type, visibility,
                    is_static, is_abstract, is_final, 0 AS is_readonly,
                    NULL AS declared_type, NULL AS default_value, return_type_name AS return_type
             FROM methods WHERE entity_id = :entity_id"
        );
        $stmt->execute(['entity_id' => $entityId]);
        $members = array_merge($members, $stmt->fetchAll(PDO::FETCH_ASSOC));

        $stmt = $this->pdo->prepare(
            "SELECT id, entity_id, name, member_type, visibility,
                    is_static, 0 AS is_abstract, 0 AS is_final, is_readonly,
                    declared_type_name AS declared_type, default_value, NULL AS return_type
             FROM properties WHERE entity_id = :entity_id"
        );
        $stmt->execute(['entity_id' => $entityId]);
        $members = array_merge($members, $stmt->fetchAll(PDO::FETCH_ASSOC));

        usort($members, fn(array $a, array $b): int => [$a['member_type'], $a['name']] <=> [$b['member_type'], $b['name']]);

        return $members;
    }

    /**
     * Compatibility shim: finds member id from either methods or properties.
     */
    public function findMemberId(int $entityId, string $name, string $memberType): ?int
    {
        if ($memberType === 'method') {
            $stmt = $this->pdo->prepare('SELECT id FROM methods WHERE entity_id = :entity_id AND name = :name');
            $stmt->execute(
                [
                'entity_id' => $entityId,
                'name' => $name,
                ]
            );
        } else {
            $stmt = $this->pdo->prepare('SELECT id FROM properties WHERE entity_id = :entity_id AND name = :name AND member_type = :member_type');
            $stmt->execute(
                [
                'entity_id' => $entityId,
                'name' => $name,
                'member_type' => $memberType,
                ]
            );
        }
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !is_array($row) || !isset($row['id'])) {
            return null;
        }
        return (int) $row['id'];
    }

    /**
     * Compatibility shim: returns parameters with member_id column.
     *
     * @param  int[] $methodIds
     * @return list<array<string, mixed>>
     */
    public function findParametersByMembers(array $methodIds): array
    {
        if (empty($methodIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($methodIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT id, method_id AS member_id, name, declared_type_name AS declared_type, default_value, is_variadic, is_passed_by_reference, position
             FROM parameters WHERE method_id IN ($placeholders) ORDER BY method_id, position"
        );
        $stmt->execute(array_map('intval', $methodIds));
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compatibility shim: returns parameters with member_id column.
     *
     * @return list<array<string, mixed>>
     */
    public function findParametersByMember(int $methodId): array
    {
        $stmt = $this->pdo->prepare(
            "SELECT id, method_id AS member_id, name, declared_type_name AS declared_type, default_value, is_variadic, is_passed_by_reference, position
             FROM parameters WHERE method_id = :method_id ORDER BY position"
        );
        $stmt->execute(['method_id' => $methodId]);
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Compatibility shim: returns typed records in old types format.
     *
     * @return list<array{name: string, entity_fqn?: string|null}>
     */
    /**
     * @return list<array{name: string, entity_fqn?: string|null}>
     */
    public function findTypesByOwner(string $ownerType, ?int $ownerId = null): array
    {
        if ($ownerType === 'return') {
            if ($ownerId !== null) {
                $stmt = $this->pdo->prepare(
                    "SELECT m.return_type_name AS name, e.fqn AS entity_fqn
                     FROM methods m
                     LEFT JOIN entities e ON e.id = m.return_type_entity_id
                     WHERE m.id = :owner_id AND m.return_type_name IS NOT NULL
                     ORDER BY m.name"
                );
                $stmt->execute(['owner_id' => $ownerId]);
            } else {
                $stmt = $this->pdo->query(
                    "SELECT m.return_type_name AS name, e.fqn AS entity_fqn
                     FROM methods m
                     LEFT JOIN entities e ON e.id = m.return_type_entity_id
                     WHERE m.return_type_name IS NOT NULL
                     ORDER BY m.name"
                );
            }
        } elseif ($ownerType === 'property') {
            if ($ownerId !== null) {
                $stmt = $this->pdo->prepare(
                    "SELECT p.declared_type_name AS name, e.fqn AS entity_fqn
                     FROM properties p
                     LEFT JOIN entities e ON e.id = p.declared_type_entity_id
                     WHERE p.id = :owner_id AND p.declared_type_name IS NOT NULL
                     ORDER BY p.name"
                );
                $stmt->execute(['owner_id' => $ownerId]);
            } else {
                $stmt = $this->pdo->query(
                    "SELECT p.declared_type_name AS name, e.fqn AS entity_fqn
                     FROM properties p
                     LEFT JOIN entities e ON e.id = p.declared_type_entity_id
                     WHERE p.declared_type_name IS NOT NULL
                     ORDER BY p.name"
                );
            }
        } else {
            if ($ownerId !== null) {
                $stmt = $this->pdo->prepare(
                    "SELECT p.declared_type_name AS name, e.fqn AS entity_fqn
                     FROM parameters p
                     LEFT JOIN entities e ON e.id = p.declared_type_entity_id
                     WHERE p.id = :owner_id AND p.declared_type_name IS NOT NULL
                     ORDER BY p.name"
                );
                $stmt->execute(['owner_id' => $ownerId]);
            } else {
                $stmt = $this->pdo->query(
                    "SELECT p.declared_type_name AS name, e.fqn AS entity_fqn
                     FROM parameters p
                     LEFT JOIN entities e ON e.id = p.declared_type_entity_id
                     WHERE p.declared_type_name IS NOT NULL
                     ORDER BY p.name"
                );
            }
        }
        if ($stmt === false) {
            return [];
        }
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findMethodsByEntity(int $entityId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM methods WHERE entity_id = :entity_id ORDER BY name'
        );
        $stmt->execute(['entity_id' => $entityId]);
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findPropertiesByEntity(int $entityId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM properties WHERE entity_id = :entity_id ORDER BY member_type, name'
        );
        $stmt->execute(['entity_id' => $entityId]);
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @param  int[] $methodIds
     * @return list<array<string, mixed>>
     */
    public function findParametersByMethods(array $methodIds): array
    {
        if (empty($methodIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($methodIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM parameters WHERE method_id IN ($placeholders) ORDER BY method_id, position"
        );
        $stmt->execute(array_map('intval', $methodIds));
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findParametersByMethod(int $methodId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM parameters WHERE method_id = :method_id ORDER BY position'
        );
        $stmt->execute(['method_id' => $methodId]);
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRelationshipsBySource(int $sourceId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*,
                    m.name AS source_method_name,
                    e.fqn  AS source_fqn,
                    t.fqn  AS target_fqn_resolved
             FROM relationships r
             JOIN entities e ON r.source_id = e.id
             LEFT JOIN methods m ON m.id = r.source_method_id
             LEFT JOIN entities t ON r.target_id = t.id
             WHERE r.source_id = :source_id
             ORDER BY r.type, COALESCE(t.fqn, r.target_fqn), r.target_member_name'
        );
        $stmt->execute(['source_id' => $sourceId]);
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRelationshipsByTarget(int $targetId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*,
                    m.name AS source_method_name,
                    e.fqn  AS source_fqn,
                    t.fqn  AS target_fqn_resolved
             FROM relationships r
             JOIN entities e ON r.source_id = e.id
             JOIN entities t ON r.target_id = t.id
             LEFT JOIN methods m ON m.id = r.source_method_id
             WHERE r.target_id = :target_id
             ORDER BY r.type, e.fqn'
        );
        $stmt->execute(['target_id' => $targetId]);
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{name: string, declared_type_name: string|null, default_value: string|null}>
     */
    public function findParameterSignatures(int $methodId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT name, declared_type_name, default_value
             FROM parameters
             WHERE method_id = :method_id
             ORDER BY position'
        );
        $stmt->execute(['method_id' => $methodId]);
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRelationshipsByType(string $type): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, e.fqn AS source_fqn, t.fqn AS target_fqn_resolved
             FROM relationships r
             JOIN entities e ON r.source_id = e.id
             LEFT JOIN entities t ON r.target_id = t.id
             WHERE r.type = :type
             ORDER BY e.fqn, COALESCE(t.fqn, r.target_fqn)'
        );
        $stmt->execute(['type' => $type]);
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRelationshipId(int $sourceId, ?int $targetId, ?string $targetFqn, string $type, ?int $sourceMethodId, ?string $targetMemberName = null): ?int
    {
        $sql = 'SELECT id FROM relationships WHERE source_id = :source_id AND type = :type';
        $params = ['source_id' => $sourceId, 'type' => $type];

        if ($targetId !== null) {
            $sql .= ' AND target_id = :target_id';
            $params['target_id'] = $targetId;
        } else {
            $sql .= ' AND target_id IS NULL';
        }

        if ($targetFqn !== null) {
            $sql .= ' AND target_fqn = :target_fqn';
            $params['target_fqn'] = $targetFqn;
        } else {
            $sql .= ' AND target_fqn IS NULL';
        }

        if ($targetMemberName !== null) {
            $sql .= ' AND target_member_name = :target_member_name';
            $params['target_member_name'] = $targetMemberName;
        } else {
            $sql .= ' AND target_member_name IS NULL';
        }

        if ($sourceMethodId !== null) {
            $sql .= ' AND source_method_id = :source_method_id';
            $params['source_method_id'] = $sourceMethodId;
        } else {
            $sql .= ' AND source_method_id IS NULL';
        }

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !is_array($row) || !isset($row['id'])) {
            return null;
        }
        return (int) $row['id'];
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllRelationships(): array
    {
        $stmt = $this->pdo->query(
            'SELECT r.*, e.fqn AS source_fqn, t.fqn AS target_fqn_resolved
             FROM relationships r
             JOIN entities e ON r.source_id = e.id
             LEFT JOIN entities t ON r.target_id = t.id
             ORDER BY e.fqn, r.type, COALESCE(t.fqn, r.target_fqn)'
        );
        if ($stmt === false) {
            return [];
        }
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findAllNamespaces(): array
    {
        $stmt = $this->pdo->query('SELECT * FROM namespaces ORDER BY fqn');
        if ($stmt === false) {
            return [];
        }
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findEntitiesByShortName(string $shortName): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM entities WHERE short_name = :name ORDER BY fqn'
        );
        $stmt->execute(['name' => $shortName]);
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findFileById(int $fileId): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM files WHERE id = :id');
        $stmt->execute(['id' => $fileId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !is_array($row)) {
            return null;
        }
        return $row;
    }

    public function findFileId(string $path): ?int
    {
        $stmt = $this->pdo->prepare('SELECT id FROM files WHERE path = :path');
        $stmt->execute(['path' => $path]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !is_array($row) || !isset($row['id'])) {
            return null;
        }
        return (int) $row['id'];
    }

    public function countEntities(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM entities');
        if ($stmt === false) {
            return 0;
        }
        $value = $stmt->fetchColumn();
        return is_numeric($value) ? (int) $value : 0;
    }

    public function countRelationships(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM relationships');
        if ($stmt === false) {
            return 0;
        }
        $value = $stmt->fetchColumn();
        return is_numeric($value) ? (int) $value : 0;
    }

    public function countMembers(): int
    {
        $stmt = $this->pdo->query('SELECT (SELECT COUNT(*) FROM methods) + (SELECT COUNT(*) FROM properties)');
        if ($stmt === false) {
            return 0;
        }
        $value = $stmt->fetchColumn();
        return is_numeric($value) ? (int) $value : 0;
    }

    public function countTypes(): int
    {
        $stmt = $this->pdo->query(
            'SELECT (SELECT COUNT(*) FROM methods WHERE return_type_name IS NOT NULL)
                   + (SELECT COUNT(*) FROM properties WHERE declared_type_name IS NOT NULL)
                   + (SELECT COUNT(*) FROM parameters WHERE declared_type_name IS NOT NULL)'
        );
        if ($stmt === false) {
            return 0;
        }
        $value = $stmt->fetchColumn();
        return is_numeric($value) ? (int) $value : 0;
    }

    public function countMethods(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM methods');
        if ($stmt === false) {
            return 0;
        }
        $value = $stmt->fetchColumn();
        return is_numeric($value) ? (int) $value : 0;
    }

    public function countProperties(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM properties');
        if ($stmt === false) {
            return 0;
        }
        $value = $stmt->fetchColumn();
        return is_numeric($value) ? (int) $value : 0;
    }

    public function countNamespaces(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM namespaces');
        if ($stmt === false) {
            return 0;
        }
        $value = $stmt->fetchColumn();
        return is_numeric($value) ? (int) $value : 0;
    }
}
