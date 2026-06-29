<?php declare(strict_types=1);

namespace SineFine\Ponymator\Detector\Pattern\Catalog;

final class Proxy implements PatternInterface
{
    public function name(): string
    {
        return 'proxy';
    }

    /**
     * @return string[]
     */
    public function roles(): array
    {
        return ['Subject', 'Proxy'];
    }

    private const CTE_BASE = <<<'SQL'
            WITH subject_proxies AS (
                SELECT DISTINCT
                    subj.id AS subject_id,
                    proxy.id AS proxy_id,
                    real_subject.id AS real_subject_id
                FROM entities subj
                JOIN relationships r_subj
                  ON r_subj.target_id = subj.id AND r_subj.type IN ('implements', 'extends')
                JOIN entities proxy ON r_subj.source_id = proxy.id
                JOIN relationships r_dep
                  ON r_dep.source_id = proxy.id
                  AND r_dep.type IN ('dependency', 'creates', 'creates_strong')
                  AND r_dep.target_id != subj.id
                JOIN entities real_subject ON r_dep.target_id = real_subject.id
                JOIN relationships r_subj2
                  ON r_subj2.target_id = subj.id AND r_subj2.type IN ('implements', 'extends')
                  AND r_subj2.source_id = real_subject.id
                WHERE proxy.type = 'class'
                  AND real_subject.type = 'class'
                  AND proxy.id != real_subject.id
            ),
            proxy_ctor AS (
                SELECT sp.proxy_id, sp.real_subject_id
                FROM subject_proxies sp
                JOIN members m ON m.entity_id = sp.proxy_id
                  AND m.member_type = 'method'
                  AND m.name = '__construct'
                JOIN types t ON t.owner_id = m.id AND t.owner_type = 'param'
                  AND t.entity_id = sp.real_subject_id
            ),
            delegation_coverage AS (
                SELECT sp.proxy_id, sp.real_subject_id,
                       COUNT(DISTINCT r_call.source_member_id) AS call_count
                FROM subject_proxies sp
                JOIN relationships r_call
                  ON r_call.source_id = sp.proxy_id
                  AND r_call.target_id = sp.real_subject_id
                  AND r_call.type LIKE 'call_%'
                GROUP BY sp.proxy_id, sp.real_subject_id
                HAVING call_count >= 2
            ),
            filtered AS (
                SELECT sp.subject_id, sp.proxy_id
                FROM subject_proxies sp
                JOIN proxy_ctor pc ON pc.proxy_id = sp.proxy_id AND pc.real_subject_id = sp.real_subject_id
                JOIN delegation_coverage dc ON dc.proxy_id = sp.proxy_id AND dc.real_subject_id = sp.real_subject_id
            )
            SQL;

    private const SELECT_SUBJECT = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY subject_id, proxy_id) AS match_id,
                   subject_id AS entity_id, 'Subject' AS role
            FROM filtered
            SQL;

    private const SELECT_PROXY = <<<'SQL'
            SELECT DENSE_RANK() OVER (ORDER BY subject_id, proxy_id) AS match_id,
                   proxy_id AS entity_id, 'Proxy' AS role
            FROM filtered
            SQL;

    public function candidateSql(): string
    {
        return
            self::CTE_BASE . "\n" .
            self::SELECT_SUBJECT . "\n" .
            "UNION ALL\n" .
            self::SELECT_PROXY;
    }
}