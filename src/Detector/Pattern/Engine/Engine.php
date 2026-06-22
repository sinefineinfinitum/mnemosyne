<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Engine;

use PDO;
use SineFine\Mnemosyne\Detector\Pattern\Model\PatternResult;
use Throwable;

final class Engine
{
    public function __construct(
        private PatternRegistry $registry,
        private PDO $pdo,
        private ?PDO $readOnlyPdo = null,
    ) {
    }

    /**
     * @throws Throwable
     */
    public function run(): PatternResult
    {
        $allMatches = [];
        $errors = [];
        $detectionPdo = $this->readOnlyPdo ?? $this->pdo;

        foreach ($this->registry->all() as $pattern) {
            try {
                $collector = new CandidateCollector($detectionPdo, $pattern);
                $matches = $collector->collect();
                $allMatches = [...$allMatches, ...$matches];
            } catch (\PDOException $e) {
                $errors[] = "Pattern '{$pattern->name()}': " . $e->getMessage();
            }
        }

        $persister = new MatchPersister($this->pdo);
        $persister->record($allMatches);

        return new PatternResult(
            matches: $allMatches,
            errors: $errors,
        );
    }
}
