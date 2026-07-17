<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Pipeline;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Detector\Pattern\Engine\MatchPersister;
use SineFine\Mnemosyne\Detector\Pattern\Model\PatternMatch;
use SineFine\Mnemosyne\Detector\Pattern\Model\PatternParticipant;
use SineFine\Mnemosyne\Graph\Experimental\GraphCommand;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;
use SineFine\Mnemosyne\Graph\Experimental\Schema;
use SineFine\Mnemosyne\Tests\Unit\Detector\Pattern\Stub\PatternInterfaceStub;

final class MatchPersisterTest extends TestCase
{
    private PDO $pdo;
    private GraphQuery $query;
    private GraphCommand $command;

    protected function setUp(): void
    {
        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create($this->pdo);
        $this->command = new GraphCommand($this->pdo);
        $this->query = new GraphQuery($this->pdo);
    }

    public function testRecordStoresMatches(): void
    {
        $entityId = $this->command->insertEntity(
            'App\\TargetInterface', 'TargetInterface', 'interface', null, null, null, [],
        );

        $pattern = new PatternInterfaceStub('adapter', ['target']);
        $match = new PatternMatch(
            pattern: $pattern,
            participants: [
                new PatternParticipant(role: 'target', entityId: $entityId),
            ],
        );

        $persister = new MatchPersister($this->pdo);
        $persister->record([$match]);

        $matches = $this->pdo->query('SELECT * FROM pattern_matches')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $matches);
        $this->assertSame('adapter', $matches[0]['pattern_name']);

        $participants = $this->pdo->query('SELECT * FROM pattern_participants')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $participants);
        $this->assertSame('target', $participants[0]['role']);
        $this->assertSame($entityId, (int) $participants[0]['entity_id']);
    }

    public function testRecordReplacesPreviousMatches(): void
    {
        $oldId = $this->command->insertEntity('App\\Old', 'Old', 'interface', null, null, null, []);
        $newId = $this->command->insertEntity('App\\New', 'New', 'interface', null, null, null, []);

        $pattern = new PatternInterfaceStub('adapter', ['target']);

        $persister = new MatchPersister($this->pdo);

        $persister->record([
            new PatternMatch(
                pattern: $pattern,
                participants: [new PatternParticipant(role: 'target', entityId: $oldId)],
            ),
        ]);

        $persister->record([
            new PatternMatch(
                pattern: $pattern,
                participants: [new PatternParticipant(role: 'target', entityId: $newId)],
            ),
        ]);

        $matches = $this->pdo->query('SELECT * FROM pattern_matches')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(1, $matches);
    }

    public function testRecordEmptyArrayClearsMatches(): void
    {
        $eid = $this->command->insertEntity('App\\Target', 'Target', 'interface', null, null, null, []);
        $pattern = new PatternInterfaceStub('adapter', ['target']);
        $persister = new MatchPersister($this->pdo);

        $persister->record([
            new PatternMatch(
                pattern: $pattern,
                participants: [new PatternParticipant(role: 'target', entityId: $eid)],
            ),
        ]);

        $persister->record([]);

        $matches = $this->pdo->query('SELECT * FROM pattern_matches')->fetchAll(PDO::FETCH_ASSOC);
        $this->assertCount(0, $matches);
    }

}
