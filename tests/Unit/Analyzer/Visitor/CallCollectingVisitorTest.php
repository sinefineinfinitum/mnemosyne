<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Analyzer\Visitor;

use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\CallInfo;
use SineFine\Ponymator\Analyzer\Visitor\CallCollectingVisitor;

final class CallCollectingVisitorTest extends TestCase
{
    private CallCollectingVisitor $visitor;

    private NodeTraverser $traverser;

    protected function setUp(): void
    {
        $this->visitor = new CallCollectingVisitor();
        $this->traverser = new NodeTraverser();
        $this->traverser->addVisitor(new NameResolver());
        $this->traverser->addVisitor($this->visitor);
    }

    /**
     * @param  string $code
     * @return void
     */
    private function parseAndTraverse(string $code): void
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php ' . $code);
        $this->traverser->traverse($ast);
    }

    /**
     * @return list<CallInfo>
     */
    private function callsOf(string $fqcn, string $method): array
    {
        $calls = $this->visitor->getCalls();
        return $calls[$fqcn][$method] ?? [];
    }

    public function testCollectsStaticMethodCall(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    Foo::bar();
                }
            }
        '
        );
        $calls = $this->callsOf('App\Service\UserService', 'run');
        $this->assertCount(1, $calls);
        $this->assertSame(CallInfo::KIND_STATIC, $calls[0]->kind);
        $this->assertSame('bar', $calls[0]->targetName);
    }

    public function testCollectsStaticPropertyFetch(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    $x = Foo::$prop;
                }
            }
        '
        );
        $calls = $this->callsOf('App\Service\UserService', 'run');
        $this->assertCount(1, $calls);
        $this->assertSame(CallInfo::KIND_STATIC, $calls[0]->kind);
        $this->assertSame('prop', $calls[0]->targetName);
    }

    public function testCollectsDynamicMethodCall(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    $obj->doIt();
                }
            }
        '
        );
        $calls = $this->callsOf('App\Service\UserService', 'run');
        $this->assertCount(1, $calls);
        $this->assertSame(CallInfo::KIND_DYNAMIC, $calls[0]->kind);
        $this->assertSame('doIt', $calls[0]->targetName);
    }

    public function testCollectsDynamicMethodCallOnThis(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    $this->doIt();
                }
            }
        '
        );
        $calls = $this->callsOf('App\Service\UserService', 'run');
        $this->assertCount(1, $calls);
        $this->assertSame(CallInfo::KIND_DYNAMIC, $calls[0]->kind);
        $this->assertSame('doIt', $calls[0]->targetName);
    }

    public function testCollectsNullsafeMethodCall(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    $obj?->doIt();
                }
            }
        '
        );
        $calls = $this->callsOf('App\Service\UserService', 'run');
        $this->assertCount(1, $calls);
        $this->assertSame(CallInfo::KIND_DYNAMIC, $calls[0]->kind);
        $this->assertSame('doIt', $calls[0]->targetName);
    }

    public function testCollectsGlobalFunctionCall(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    strlen("hello");
                }
            }
        '
        );
        $calls = $this->callsOf('App\Service\UserService', 'run');
        $this->assertCount(1, $calls);
        $this->assertSame(CallInfo::KIND_GLOBAL, $calls[0]->kind);
        $this->assertSame('strlen', $calls[0]->targetName);
    }

    public function testCollectsNewExpression(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    $obj = new \App\Entity\User();
                }
            }
        '
        );
        $calls = $this->callsOf('App\Service\UserService', 'run');
        $createCalls = array_values(array_filter($calls, fn(CallInfo $c) => $c->kind === CallInfo::KIND_CREATE));
        $this->assertCount(1, $createCalls);
        $this->assertSame('App\Entity\User', $createCalls[0]->targetName);
    }

    public function testSkipsAnonymousClass(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    $obj = new class {};
                }
            }
        '
        );
        $this->assertSame([], $this->callsOf('App\Service\UserService', 'run'));
    }

    public function testHandlesTraitContext(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Util;
            trait CacheTrait {
                public function init(): void {
                    strlen("a");
                }
            }
        '
        );
        $calls = $this->callsOf('App\Util\CacheTrait', 'init');
        $this->assertCount(1, $calls);
        $this->assertSame(CallInfo::KIND_GLOBAL, $calls[0]->kind);
    }

    public function testDeduplicatesCallsWithinMethod(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    Foo::bar();
                    Foo::bar();
                }
            }
        '
        );
        $calls = $this->callsOf('App\Service\UserService', 'run');
        $this->assertCount(1, $calls);
    }

    public function testResolvesUseStatement(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            use App\Entity\User;
            class UserService {
                public function run(): void {
                    new User();
                }
            }
        '
        );
        $calls = $this->callsOf('App\Service\UserService', 'run');
        $createCalls = array_values(array_filter($calls, fn(CallInfo $c) => $c->kind === CallInfo::KIND_CREATE));
        $this->assertCount(1, $createCalls);
        $this->assertSame('App\Entity\User', $createCalls[0]->targetName);
    }

    public function testIgnoresCallsOutsideClass(): void
    {
        $this->parseAndTraverse(
            '
            $x = strlen("a");
        '
        );
        $this->assertSame([], $this->visitor->getCalls());
    }

    public function testCollectsMultipleMethods(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function a(): void {
                    Foo::bar();
                }
                public function b(): void {
                    strlen("x");
                }
            }
        '
        );
        $this->assertCount(1, $this->callsOf('App\Service\UserService', 'a'));
        $this->assertCount(1, $this->callsOf('App\Service\UserService', 'b'));
    }

    public function testCollectsMixedCallKinds(): void
    {
        $this->parseAndTraverse(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    Foo::bar();
                    $obj->doIt();
                    strlen("a");
                    new \App\Entity\User();
                }
            }
        '
        );
        $calls = $this->callsOf('App\Service\UserService', 'run');
        $this->assertCount(4, $calls);
        $kinds = array_map(fn(CallInfo $c) => $c->kind, $calls);
        $this->assertContains(CallInfo::KIND_STATIC, $kinds);
        $this->assertContains(CallInfo::KIND_DYNAMIC, $kinds);
        $this->assertContains(CallInfo::KIND_GLOBAL, $kinds);
        $this->assertContains(CallInfo::KIND_CREATE, $kinds);
    }

    public function testCollectsCallsInFileLevelFunction(): void
    {
        $this->parseAndTraverse(
            '
            namespace App;
            function loadConfig(): array {
                $result = parse_ini_file("a.ini");
                return $result;
            }
        '
        );
        $fileCalls = $this->visitor->getFileCalls();
        $this->assertArrayHasKey('loadConfig', $fileCalls);
        $this->assertCount(1, $fileCalls['loadConfig']);
        $this->assertSame(CallInfo::KIND_GLOBAL, $fileCalls['loadConfig'][0]->kind);
        $this->assertSame('parse_ini_file', $fileCalls['loadConfig'][0]->targetName);
    }

    public function testCollectsNewInFileLevelFunction(): void
    {
        $this->parseAndTraverse(
            '
            namespace App;
            function makeThing(): object {
                return new \App\Entity\Thing();
            }
        '
        );
        $fileCalls = $this->visitor->getFileCalls();
        $this->assertArrayHasKey('makeThing', $fileCalls);
        $creates = array_values(
            array_filter(
                $fileCalls['makeThing'],
                fn(CallInfo $c) => $c->kind === CallInfo::KIND_CREATE
            )
        );
        $this->assertCount(1, $creates);
        $this->assertSame('App\Entity\Thing', $creates[0]->targetName);
    }

    public function testCollectsCallsInMultipleFileLevelFunctions(): void
    {
        $this->parseAndTraverse(
            '
            namespace App;
            function a(): void {
                strlen("a");
            }
            function b(): void {
                new \App\X();
            }
        '
        );
        $fileCalls = $this->visitor->getFileCalls();
        $this->assertArrayHasKey('a', $fileCalls);
        $this->assertArrayHasKey('b', $fileCalls);
        $this->assertSame(CallInfo::KIND_GLOBAL, $fileCalls['a'][0]->kind);
        $this->assertSame(CallInfo::KIND_CREATE, $fileCalls['b'][0]->kind);
    }

    public function testFileLevelFunctionDedupesCalls(): void
    {
        $this->parseAndTraverse(
            '
            function helper(): void {
                strlen("a");
                strlen("a");
            }
        '
        );
        $fileCalls = $this->visitor->getFileCalls();
        $this->assertCount(1, $fileCalls['helper']);
    }

    public function testClassMethodInsideSameFileDoesNotPopulateFileCalls(): void
    {
        $this->parseAndTraverse(
            '
            namespace App;
            function freeFn(): void {
                strlen("a");
            }
            class Svc {
                public function run(): void {
                    strlen("b");
                }
            }
        '
        );
        $fileCalls = $this->visitor->getFileCalls();
        $this->assertArrayHasKey('freeFn', $fileCalls);
        $this->assertArrayNotHasKey('Svc::run', $fileCalls);
        $this->assertArrayNotHasKey('run', $fileCalls);
    }

    public function testFileLevelFunctionStaticCallAlsoRecorded(): void
    {
        $this->parseAndTraverse(
            '
            function helper(): void {
                Foo::bar();
            }
        '
        );
        $fileCalls = $this->visitor->getFileCalls();
        $this->assertArrayHasKey('helper', $fileCalls);
        $this->assertCount(1, $fileCalls['helper']);
        $this->assertSame(CallInfo::KIND_STATIC, $fileCalls['helper'][0]->kind);
        $this->assertSame('bar', $fileCalls['helper'][0]->targetName);
    }
}
