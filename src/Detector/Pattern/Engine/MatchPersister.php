<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Engine;

use PDO;
use SineFine\Mnemosyne\Detector\Pattern\Model\PatternMatch;
use Throwable;

final class MatchPersister
{
    public function __construct(
        private PDO $pdo,
    ) {
    }

    /**
     * @param  PatternMatch[] $matches
     * @throws Throwable
     */
    public function record(array $matches): void
    {
        $this->pdo->beginTransaction();
        try {
            $this->pdo->exec('DELETE FROM pattern_participants');
            $this->pdo->exec('DELETE FROM pattern_matches');

            $insertMatch = $this->pdo->prepare(
                'INSERT INTO pattern_matches (pattern_name) VALUES (:name)'
            );
            $insertPart = $this->pdo->prepare(
                'INSERT INTO pattern_participants (match_id, entity_id, role) VALUES (:match_id, :entity_id, :role)'
            );

            foreach ($matches as $match) {
                $insertMatch->execute(
                    [
                    'name' => $match->pattern->name(),
                    ]
                );

                $matchId = (int) $this->pdo->lastInsertId();

                foreach ($match->participants as $participant) {
                    $insertPart->execute(
                        [
                        'match_id' => $matchId,
                        'entity_id' => $participant->entityId,
                        'role' => $participant->role,
                        ]
                    );
                }
            }

            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
    }
}
