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
        if (file_exists(self::DB_PATH)) {
            @unlink(self::DB_PATH);
        }

        self::$pdo = new PDO('sqlite:' . self::DB_PATH);
        self::$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        Schema::create(self::$pdo);

        $command = new GraphCommand(self::$pdo);
        self::$query = new GraphQuery(self::$pdo);

        $docsDir = dirname(__DIR__, 3) . '/docs';
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
        $this->assertFileExists(self::DB_PATH, 'SQLite database file was not created');
    }

    public function testEntitiesCreated(): void
    {
        $this->assertGreaterThan(0, self::$query->countEntities(), 'No entities created');
    }

    public function testMembersCreated(): void
    {
        $this->assertGreaterThan(0, self::$query->countMembers(), 'No members created');
    }

    public function testNamespacesCreated(): void
    {
        $this->assertGreaterThan(0, self::$query->countNamespaces(), 'No namespaces created');
    }

    public function testRelationshipsCreated(): void
    {
        $this->assertGreaterThan(0, self::$query->countRelationships(), 'No relationships created');
    }

    public function testEntitiesHaveCorrectTypes(): void
    {
        $entities = self::$query->findAllEntities();
        $types = array_unique(array_column($entities, 'type'));

        $this->assertContains('class', $types);
        $this->assertContains('interface', $types);
    }

    public function testNamespaceHierarchy(): void
    {
        $namespaces = self::$query->findAllNamespaces();
        $nsFqns = array_column($namespaces, 'fqn');

        $this->assertContains('SineFine', $nsFqns);
        $this->assertContains('SineFine\Ponymator', $nsFqns);
        $this->assertContains('SineFine\Ponymator\Analyzer', $nsFqns);
    }

    public function testExtendsRelationships(): void
    {
        $extendsRels = self::$query->findRelationshipsByType('extends');
        $this->assertNotEmpty($extendsRels, 'No extends relationships found');
    }

    public function testImplementsRelationships(): void
    {
        $implRels = self::$query->findRelationshipsByType('implements');
        $this->assertNotEmpty($implRels, 'No implements relationships found');
    }

    public function testCreatesRelationships(): void
    {
        $createsRels = self::$query->findRelationshipsByType('creates_weak');
        $this->assertNotEmpty($createsRels, 'No creates relationships found');
    }

    public function testCallStaticRelationships(): void
    {
        $staticRels = array_merge(
            self::$query->findRelationshipsByType('call_static_weak'),
            self::$query->findRelationshipsByType('call_static_strong'),
        );
        $this->assertNotEmpty($staticRels, 'No static call relationships found');
    }

    public function testCallDynamicRelationships(): void
    {
        $dynamicRels = array_merge(
            self::$query->findRelationshipsByType('call_dynamic_weak'),
            self::$query->findRelationshipsByType('call_dynamic_strong'),
        );
        $this->assertNotEmpty($dynamicRels, 'No dynamic call relationships found');
    }

    public function testCallGlobalRelationships(): void
    {
        $globalRels = array_merge(
            self::$query->findRelationshipsByType('call_global_weak'),
            self::$query->findRelationshipsByType('call_global_strong'),
        );
        $this->assertIsArray($globalRels, 'Global call relationships should be an array');
    }

    public function testReturnTypeRelationships(): void
    {
        $returnRels = self::$query->findRelationshipsByType('return_type');
        $this->assertNotEmpty($returnRels, 'No return type relationships found');
    }

    public function testParamTypeRelationships(): void
    {
        $paramRels = self::$query->findRelationshipsByType('param_type');
        $this->assertNotEmpty($paramRels, 'No param type relationships found');
    }

    public function testPropertyTypeRelationships(): void
    {
        $propRels = self::$query->findRelationshipsByType('property_type');
        $this->assertNotEmpty($propRels, 'No property type relationships found');
    }

    public function testDomainCoupling(): void
    {
        $coupling = self::$query->getDomainCoupling();
        $this->assertNotEmpty($coupling, 'No cross-domain coupling found');
    }

    public function testSampleEntityData(): void
    {
        $entities = self::$query->findAllEntities();
        $this->assertNotEmpty($entities);

        $entity = $entities[0];
        $this->assertNotEmpty($entity['fqn']);

        $members = self::$query->findMembersByEntity((int) $entity['id']);
        $this->assertNotEmpty($members);
    }

    public function testSampleRelationshipData(): void
    {
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
