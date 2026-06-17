<?php declare(strict_types=1);

namespace SineFine\Ponymator\Graph\Experimental;

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
        $stmt = $this->pdo->prepare('SELECT id FROM entities WHERE fqn = :fqn');
        $stmt->execute(['fqn' => $fqn]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !is_array($row) || !isset($row['id'])) {
            return null;
        }
        return (int) $row['id'];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findEntity(string $fqn): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM entities WHERE fqn = :fqn');
        $stmt->execute(['fqn' => $fqn]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !is_array($row)) {
            return null;
        }
        return $row;
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
     * @return list<array<string, mixed>>
     */
    public function findMembersByEntity(int $entityId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM members WHERE entity_id = :entity_id ORDER BY member_type, name'
        );
        $stmt->execute(['entity_id' => $entityId]);
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findMemberId(int $entityId, string $name, string $memberType): ?int
    {
        $stmt = $this->pdo->prepare(
            'SELECT id FROM members WHERE entity_id = :entity_id AND name = :name AND member_type = :member_type'
        );
        $stmt->execute(
            [
            'entity_id' => $entityId,
            'name' => $name,
            'member_type' => $memberType,
            ]
        );
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row === false || !is_array($row) || !isset($row['id'])) {
            return null;
        }
        return (int) $row['id'];
    }

    /**
     * @param  int[] $memberIds
     * @return list<array<string, mixed>>
     */
    public function findParametersByMembers(array $memberIds): array
    {
        if (empty($memberIds)) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($memberIds), '?'));
        $stmt = $this->pdo->prepare(
            "SELECT * FROM parameters WHERE member_id IN ($placeholders) ORDER BY member_id, position"
        );
        $stmt->execute(array_map('intval', $memberIds));
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findParametersByMember(int $memberId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT * FROM parameters WHERE member_id = :member_id ORDER BY position'
        );
        $stmt->execute(['member_id' => $memberId]);
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
                    m.name AS source_member_name,
                    m.member_type AS source_member_type,
                    m.visibility AS source_member_visibility,
                    m.is_static AS source_member_static,
                    s.fqn AS source_fqn,
                    t.fqn AS target_fqn_resolved
             FROM relationships r
             JOIN entities s ON r.source_id = s.id
             LEFT JOIN members m ON m.id = r.source_member_id
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
                    m.name AS source_member_name,
                    m.member_type AS source_member_type,
                    m.visibility AS source_member_visibility,
                    m.is_static AS source_member_static,
                    m.declared_type AS source_member_declared_type,
                    m.return_type AS source_member_return_type,
                    s.fqn AS source_fqn,
                    t.fqn AS target_fqn_resolved
             FROM relationships r
             JOIN entities s ON r.source_id = s.id
             JOIN entities t ON r.target_id = t.id
             LEFT JOIN members m ON m.id = r.source_member_id
             WHERE r.target_id = :target_id
             ORDER BY r.type, s.fqn'
        );
        $stmt->execute(['target_id' => $targetId]);
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array{name: string, declared_type: string|null, default_value: string|null}>
     */
    public function findParameterSignatures(int $memberId): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT name, declared_type, default_value
             FROM parameters
             WHERE member_id = :member_id
             ORDER BY position'
        );
        $stmt->execute(['member_id' => $memberId]);
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findRelationshipsByType(string $type): array
    {
        $stmt = $this->pdo->prepare(
            'SELECT r.*, s.fqn AS source_fqn, t.fqn AS target_fqn_resolved
             FROM relationships r
             JOIN entities s ON r.source_id = s.id
             LEFT JOIN entities t ON r.target_id = t.id
             WHERE r.type = :type
             ORDER BY s.fqn, COALESCE(t.fqn, r.target_fqn)'
        );
        $stmt->execute(['type' => $type]);
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function findTypesByOwner(string $ownerType, ?int $ownerId = null): array
    {
        if ($ownerId !== null) {
            $stmt = $this->pdo->prepare(
                'SELECT t.*, e.fqn AS entity_fqn
                 FROM types t
                 LEFT JOIN entities e ON e.id = t.entity_id
                 WHERE t.owner_type = :owner_type AND t.owner_id = :owner_id
                 ORDER BY t.position'
            );
            $stmt->execute(['owner_type' => $ownerType, 'owner_id' => $ownerId]);
        } else {
            $stmt = $this->pdo->prepare(
                'SELECT t.*, e.fqn AS entity_fqn
                 FROM types t
                 LEFT JOIN entities e ON e.id = t.entity_id
                 WHERE t.owner_type = :owner_type
                 ORDER BY t.position'
            );
            $stmt->execute(['owner_type' => $ownerType]);
        }
        /** @phpstan-ignore return.type */
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findRelationshipId(int $sourceId, ?int $targetId, ?string $targetFqn, string $type, ?int $sourceMemberId, ?string $targetMemberName = null): ?int
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

        if ($sourceMemberId !== null) {
            $sql .= ' AND source_member_id = :source_member_id';
            $params['source_member_id'] = $sourceMemberId;
        } else {
            $sql .= ' AND source_member_id IS NULL';
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
            'SELECT r.*, s.fqn AS source_fqn, t.fqn AS target_fqn_resolved
             FROM relationships r
             JOIN entities s ON r.source_id = s.id
             LEFT JOIN entities t ON r.target_id = t.id
             ORDER BY s.fqn, r.type, COALESCE(t.fqn, r.target_fqn)'
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
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM members');
        if ($stmt === false) {
            return 0;
        }
        $value = $stmt->fetchColumn();
        return is_numeric($value) ? (int) $value : 0;
    }

    public function countTypes(): int
    {
        $stmt = $this->pdo->query('SELECT COUNT(*) FROM types');
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
