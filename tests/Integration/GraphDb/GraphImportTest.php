<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Integration\GraphDb;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Graph\Experimental\GraphCommand;
use SineFine\Mnemosyne\Graph\Experimental\GraphQuery;
use SineFine\Mnemosyne\Graph\Experimental\Msv1ToGraphImporter;
use SineFine\Mnemosyne\Graph\Experimental\Schema;

final class GraphImportTest extends TestCase
{
    private string $dbPath;
    private ?PDO $pdo = null;

    protected function setUp(): void
    {
        $this->dbPath = sys_get_temp_dir() . '/mnemosyne_graph_import_test_' . uniqid() . '.db';
        $this->pdo = new PDO('sqlite:' . $this->dbPath);
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create($this->pdo);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->dbPath)) {
            @unlink($this->dbPath);
        }
        foreach (['-wal', '-shm'] as $suffix) {
            $f = $this->dbPath . $suffix;
            if (file_exists($f)) {
                @unlink($f);
            }
        }
    }

    public function testImportFromDocsDirectory(): void
    {
        $docsDir = dirname(__DIR__, 3) . '/docs';
        if (!is_dir($docsDir)) {
            $docsDir = __DIR__ . '/Fixtures/msv1';
        }

        if (!is_dir($docsDir)) {
            $this->markTestSkipped('Neither docs/ nor fixtures found');
        }

        $files = $this->getAllMsv1Files($docsDir);

        $this->assertNotEmpty($files, 'No .msv1 files found');

        $command = new GraphCommand($this->pdo);
        $query = new GraphQuery($this->pdo);
        $builder = new Msv1ToGraphImporter($command, $query);
        $builder->buildFromFiles($files, $docsDir);

        $entityCount = $query->countEntities();
        $relCount = $query->countRelationships();
        $memberCount = $query->countMembers();

        $this->assertGreaterThan(0, $entityCount, 'No entities were imported');
        $this->assertGreaterThan(0, $relCount, 'No relationships were imported');
        $this->assertGreaterThan(0, $memberCount, 'No members were imported');
    }

    public function testImportIsIdempotent(): void
    {
        $docsDir = dirname(__DIR__, 3) . '/docs';
        if (!is_dir($docsDir)) {
            $docsDir = __DIR__ . '/Fixtures/msv1';
        }

        if (!is_dir($docsDir)) {
            $this->markTestSkipped('Neither docs/ nor fixtures found');
        }

        $files = $this->getAllMsv1Files($docsDir);

        $command = new GraphCommand($this->pdo);
        $query = new GraphQuery($this->pdo);
        $builder = new Msv1ToGraphImporter($command, $query);
        $builder->buildFromFiles($files, $docsDir);

        $firstCount = $query->countEntities();

        $command2 = new GraphCommand($this->pdo);
        $query2 = new GraphQuery($this->pdo);
        $builder2 = new Msv1ToGraphImporter($command2, $query2);
        $builder2->buildFromFiles($files, $docsDir);

        $secondCount = $query->countEntities();

        $this->assertSame($firstCount, $secondCount, 'Import should be idempotent');
    }

    public function testImportCreatesRelationshipsWithResolvedTargets(): void
    {
        $docsDir = dirname(__DIR__, 3) . '/docs';
        if (!is_dir($docsDir)) {
            $docsDir = __DIR__ . '/Fixtures/msv1';
        }

        if (!is_dir($docsDir)) {
            $this->markTestSkipped('Neither docs/ nor fixtures found');
        }

        $files = $this->getAllMsv1Files($docsDir);

        $command = new GraphCommand($this->pdo);
        $query = new GraphQuery($this->pdo);
        $builder = new Msv1ToGraphImporter($command, $query);
        $builder->buildFromFiles($files, $docsDir);

        $rels = $query->findAllRelationships();

        $resolvedCount = 0;
        foreach ($rels as $rel) {
            if ($rel['target_id'] !== null) {
                $resolvedCount++;
            }
        }

        $this->assertGreaterThan(0, $resolvedCount, 'No relationships with resolved target IDs');
    }

    /**
     * @return list<string>
     */
    private function getAllMsv1Files(string $dir): array
    {
        $files = [];

        if (!is_dir($dir)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'msv1') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);
        return $files;
    }
}
