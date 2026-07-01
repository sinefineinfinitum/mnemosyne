<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\Visitor\ObjectCreationCollectingVisitor;

final class ObjectCreationCollectingVisitorTest extends TestCase
{
    private ObjectCreationCollectingVisitor $visitor;

    private NodeTraverser $traverser;

    protected function setUp(): void
    {
        $this->visitor = new ObjectCreationCollectingVisitor();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor(new NameResolver());
        $this->traverser->addVisitor($this->visitor);
    }

    private function parseAndTraverse(string $code): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php ' . $code);
        $this->traverser->traverse($ast);
    }

    private function getCreates(string $fqcn): array
    {
        return $this->visitor->getCreates($fqcn);
    }

    public function testDetectsSingleNewExpression(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function build(): void {
                    $obj = new \App\Entity\User();
                }
            }
        '
        );
        $creates = $this->getCreates('App\Service\UserService');
        $this->assertArrayHasKey('build', $creates);
        $this->assertSame(['App\Entity\User'], $creates['build']);
    }

    public function testDetectsNewInMultipleMethods(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function create(): void {
                    $obj = new \App\Entity\User();
                }
                public function setup(): void {
                    $cfg = new \App\Config\AppConfig();
                }
            }
        '
        );
        $creates = $this->getCreates('App\Service\UserService');
        $this->assertCount(2, $creates);
        $this->assertArrayHasKey('create', $creates);
        $this->assertArrayHasKey('setup', $creates);
        $this->assertSame(['App\Entity\User'], $creates['create']);
        $this->assertSame(['App\Config\AppConfig'], $creates['setup']);
    }

    public function testEmitsEmptyForNoNewExpressions(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function process(): void {
                    echo "hello";
                }
            }
        '
        );
        $creates = $this->getCreates('App\Service\UserService');
        $this->assertSame([], $creates);
    }

    public function testSkipsAnonymousClass(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function build(): void {
                    $obj = new class {};
                }
            }
        '
        );
        $creates = $this->getCreates('App\Service\UserService');
        $this->assertSame([], $creates);
    }

    public function testSkipsAnonymousClassWithMethods(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function build(): void {
                    $obj = new class implements \Countable {
                        public function count(): int { return 0; }
                    };
                }
            }
        '
        );
        $creates = $this->getCreates('App\Service\UserService');
        $this->assertSame([], $creates);
    }

    public function testDeduplicatesSameClassWithinMethod(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function build(): void {
                    $a = new \App\Entity\User();
                    $b = new \App\Entity\User();
                }
            }
        '
        );
        $creates = $this->getCreates('App\Service\UserService');
        $this->assertCount(1, $creates['build']);
        $this->assertSame(['App\Entity\User'], $creates['build']);
    }

    public function testCollectsMultipleDistinctClassesInSameMethod(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function build(): void {
                    $a = new \App\Entity\User();
                    $b = new \App\ValueObject\Email();
                    $c = new \App\Entity\Address();
                }
            }
        '
        );
        $creates = $this->getCreates('App\Service\UserService');
        $this->assertCount(3, $creates['build']);
        $this->assertContains('App\Entity\User', $creates['build']);
        $this->assertContains('App\ValueObject\Email', $creates['build']);
        $this->assertContains('App\Entity\Address', $creates['build']);
    }

    public function testHandlesTraitContext(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Util;
            trait CacheTrait {
                public function init(): void {
                    $cache = new \App\Cache\RedisCache();
                }
            }
        '
        );
        $creates = $this->getCreates('App\Util\CacheTrait');
        $this->assertArrayHasKey('init', $creates);
        $this->assertSame(['App\Cache\RedisCache'], $creates['init']);
    }

    public function testHandlesFullyQualifiedName(): void
    {
        $this->parseAndTraverse(
            '
            class GlobalService {
                public function run(): void {
                    $obj = new \Some\Vendor\Package();
                }
            }
        '
        );
        $creates = $this->getCreates('GlobalService');
        $this->assertSame(['Some\Vendor\Package'], $creates['run']);
    }

    public function testResolvesRelativeNameViaUseStatement(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            use App\Entity\User;
            class UserService {
                public function build(): void {
                    $obj = new User();
                }
            }
        '
        );
        $creates = $this->getCreates('App\Service\UserService');
        $this->assertArrayHasKey('build', $creates);
        $this->assertSame(['App\Entity\User'], $creates['build']);
    }

    public function testIgnoresNewOutsideClass(): void
    {
        $this->parseAndTraverse(
            '
            $x = new \stdClass();
        '
        );
        $this->assertSame([], $this->visitor->getAllCreates());
    }
}
