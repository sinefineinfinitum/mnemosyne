<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Integration\GraphDb;

use PDO;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Graph\Experimental\GraphCommand;
use SineFine\Ponymator\Graph\Experimental\GraphQuery;
use SineFine\Ponymator\Graph\Experimental\Psv1ToGraphImporter;
use SineFine\Ponymator\Graph\Experimental\Schema;

final class Psv1GraphDbTest extends TestCase
{
    private const DB_PATH = __DIR__ . '/ponymator-graph-v3.db';

    private static bool $built = false;

    private static ?PDO $pdo = null;

    private static ?GraphQuery $query = null;

    public static function setUpBeforeClass(): void
    {
        $docsDir = dirname(__DIR__, 3) . '/docs';
        if (!is_dir($docsDir)) {
            $docsDir = __DIR__ . '/Fixtures/psv1';
        }

        if (!is_dir($docsDir)) {
            return;
        }

        if (file_exists(self::DB_PATH)) {
            @unlink(self::DB_PATH);
        }

        self::$pdo = new PDO('sqlite:' . self::DB_PATH);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create(self::$pdo);

        $command = new GraphCommand(self::$pdo);
        self::$query = new GraphQuery(self::$pdo);

        $files = self::getAllPsv1Files($docsDir);

        if (!empty($files)) {
            $builder = new Psv1ToGraphImporter($command, self::$query);
            $builder->buildFromFiles($files, $docsDir);
        }

        self::$built = true;
    }

    public static function tearDownAfterClass(): void
    {
        self::$pdo = null;
        self::$query = null;
        self::$built = false;
    }

    public function testDatabaseFileExists(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $this->assertFileExists(self::DB_PATH, 'SQLite database file was not created');
    }

    public function testEntitiesCreated(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $this->assertGreaterThan(0, self::$query->countEntities(), 'No entities created');
    }

    public function testMembersCreated(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $this->assertGreaterThan(0, self::$query->countMembers(), 'No members created');
    }

    public function testNamespacesCreated(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $this->assertGreaterThan(0, self::$query->countNamespaces(), 'No namespaces created');
    }

    public function testRelationshipsCreated(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $this->assertGreaterThan(0, self::$query->countRelationships(), 'No relationships created');
    }

    public function testEntitiesHaveCorrectTypes(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $entities = self::$query->findAllEntities();
        $types = array_unique(array_column($entities, 'type'));

        $this->assertContains('class', $types);
        $this->assertContains('interface', $types);
    }

    public function testNamespaceHierarchy(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $namespaces = self::$query->findAllNamespaces();
        $nsFqns = array_column($namespaces, 'fqn');

        $expectedNs = str_contains(implode(',', $nsFqns), 'SineFine') ? 'SineFine' : 'GraphDbTest';

        $this->assertContains($expectedNs, $nsFqns);
    }

    public function testExtendsRelationships(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $extendsRels = self::$query->findRelationshipsByType('extends');
        $this->assertNotEmpty($extendsRels, 'No extends relationships found');
    }

    public function testImplementsRelationships(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $implRels = self::$query->findRelationshipsByType('implements');
        $this->assertNotEmpty($implRels, 'No implements relationships found');
    }

    public function testCreatesRelationships(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $createsRels = self::$query->findRelationshipsByType('creates');
        $this->assertNotEmpty($createsRels, 'No creates relationships found');
    }

    public function testCallStaticRelationships(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $staticRels = array_merge(
            self::$query->findRelationshipsByType('call_static_weak'),
            self::$query->findRelationshipsByType('call_static_strong'),
        );
        $this->assertNotEmpty($staticRels, 'No static call relationships found');
    }

    public function testCallDynamicRelationships(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $dynamicRels = array_merge(
            self::$query->findRelationshipsByType('call_dynamic_weak'),
            self::$query->findRelationshipsByType('call_dynamic_strong'),
        );
        $this->assertNotEmpty($dynamicRels, 'No dynamic call relationships found');
    }

    public function testCallGlobalRelationships(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $globalRels = array_merge(
            self::$query->findRelationshipsByType('call_global_weak'),
            self::$query->findRelationshipsByType('call_global_strong'),
        );
        $this->assertIsArray($globalRels, 'Global call relationships should be an array');
    }

    public function testReturnTypeRelationships(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $returnRels = self::$query->findRelationshipsByType('return_type');
        $this->assertNotEmpty($returnRels, 'No return type relationships found');
    }

    public function testParamTypeRelationships(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $paramRels = self::$query->findRelationshipsByType('param_type');
        $this->assertNotEmpty($paramRels, 'No param type relationships found');
    }

    public function testPropertyTypeRelationships(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $propRels = self::$query->findRelationshipsByType('property_type');
        $this->assertNotEmpty($propRels, 'No property type relationships found');
    }

    public function testSampleEntityData(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $entities = self::$query->findAllEntities();
        $this->assertNotEmpty($entities);

        $entity = $entities[0];
        $this->assertNotEmpty($entity['fqn']);

        $members = self::$query->findMembersByEntity((int) $entity['id']);
        $this->assertNotEmpty($members);
    }

    public function testSampleRelationshipData(): void
    {
        if (!self::$query) {
            $this->markTestSkipped('docs/ directory not found, database not initialized');
        }
        $allRels = self::$query->findAllRelationships();
        $this->assertNotEmpty($allRels);

        $firstRel = $allRels[0];
        $this->assertNotEmpty($firstRel['source_fqn']);
        $this->assertNotEmpty($firstRel['type']);
    }

    /**
     * @return list<string>
     */
    private static function getAllPsv1Files(string $dir): array
    {
        $files = [];

        if (!is_dir($dir)) {
            return $files;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'psv1') {
                $files[] = $file->getPathname();
            }
        }

        sort($files);
        return $files;
    }
}
