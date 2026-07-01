<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Analyzer\Visitor;

use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\CallInfo;
use SineFine\Mnemosyne\Analyzer\Linker\TypeInfo;
use SineFine\Mnemosyne\Analyzer\Visitor\CallAssociationVisitor;
use SineFine\Mnemosyne\Analyzer\Visitor\CallCollectingVisitor;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

final class CallAssociationVisitorTest extends TestCase
{
    /**
     * @param  string[] $projectFunctions
     * @return array<string, array<string, list<CallInfo>>>
     */
    private function collectAndResolve(string $code, array $projectFunctions = []): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse('<?php ' . $code);

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $collector = new CallCollectingVisitor();
        $traverser->addVisitor($collector);
        $traverser->traverse($ast);

        $calls = $collector->getCalls();
        $fileCalls = $collector->getFileCalls();

        $resolver = new CallAssociationVisitor($projectFunctions);
        $result = $resolver->resolve($ast, $calls, $fileCalls);
        return $result['calls'];
    }

    public function testSingleCandidateYieldsStrong(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(\App\Entity\User $user): void {
                    $user->save();
                }
            }
        '
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $dynamic = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_DYNAMIC));
        $this->assertCount(1, $dynamic);
        $this->assertSame(CallInfo::STRONG, $dynamic[0]->association);
        $this->assertSame(['App\Entity\User'], $dynamic[0]->candidateTypes);
        $this->assertSame('App\Entity\User->save', $dynamic[0]->resolvedTargetFqcn);
    }

    public function testMultipleCandidatesYieldWeak(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(\App\Entity\User|\App\Entity\Guest $user): void {
                    $user->save();
                }
            }
        '
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $dynamic = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_DYNAMIC));
        $this->assertCount(1, $dynamic);
        $this->assertSame(CallInfo::WEAK, $dynamic[0]->association);
        $this->assertCount(2, $dynamic[0]->candidateTypes);
        $this->assertContains('App\Entity\User', $dynamic[0]->candidateTypes);
        $this->assertContains('App\Entity\Guest', $dynamic[0]->candidateTypes);
    }

    public function testZeroCandidatesYieldsWeakUnknown(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    $unknown->doIt();
                }
            }
        '
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $dynamic = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_DYNAMIC));
        $this->assertCount(1, $dynamic);
        $this->assertSame(CallInfo::WEAK, $dynamic[0]->association);
        $this->assertSame([], $dynamic[0]->candidateTypes);
        $this->assertNull($dynamic[0]->resolvedTargetFqcn);
    }

    public function testParameterTypeBeatsAssignment(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(\App\Entity\User $user): void {
                    $user = $this->somehowGet();
                    $user->save();
                }
            }
        '
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $dynamic = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_DYNAMIC));
        $saveCall = array_values(array_filter($dynamic, fn(CallInfo $c) => $c->targetName === 'save'));
        $this->assertCount(1, $saveCall);
        $this->assertSame(CallInfo::STRONG, $saveCall[0]->association);
        $this->assertSame(['App\Entity\User'], $saveCall[0]->candidateTypes);
    }

    public function testAssignmentFromNewOverrides(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    $user = new \App\Entity\Guest();
                    $user->save();
                }
            }
        '
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $dynamic = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_DYNAMIC));
        $this->assertCount(1, $dynamic);
        $this->assertSame(CallInfo::STRONG, $dynamic[0]->association);
        $this->assertSame(['App\Entity\Guest'], $dynamic[0]->candidateTypes);
    }

    public function testNewYieldsStrong(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    new \App\Entity\User();
                }
            }
        '
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $creates = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_CREATE));
        $this->assertCount(1, $creates);
        $this->assertSame(CallInfo::STRONG, $creates[0]->association);
        $this->assertSame('App\Entity\User', $creates[0]->resolvedTargetFqcn);
    }

    public function testStaticCallYieldsStrongWhenTypeKnown(): void
    {
        $typeIndex = [
            'App\Registry' => new TypeInfo(
                fqcn: 'App\Registry',
                kind: 'class',
                methods: ['lookup'],
            ),
        ];

        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    \App\Registry::lookup();
                }
            }
        ',
            $typeIndex
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $statics = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_STATIC));
        $this->assertCount(1, $statics);
        $this->assertSame(CallInfo::STRONG, $statics[0]->association);
    }

    public function testStaticCallYieldsStrongWhenTypeUnknown(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    \Some\Unknown::foo();
                }
            }
        '
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $statics = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_STATIC));
        $this->assertCount(1, $statics);
        $this->assertSame(CallInfo::STRONG, $statics[0]->association);
    }

    public function testGlobalCallYieldsStrongWhenProjectFunction(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    strlen("a");
                }
            }
        ',
            ['strlen']
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $globals = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_GLOBAL));
        $this->assertCount(1, $globals);
        $this->assertSame(CallInfo::STRONG, $globals[0]->association);
        $this->assertSame('strlen', $globals[0]->targetName);
    }

    public function testGlobalCallFilteredWhenNotProjectFunction(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    strlen("a");
                }
            }
        ',
            ['otherFunction']
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $globals = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_GLOBAL));
        $this->assertCount(0, $globals);
    }

    public function testSelfResolvesToCurrentClass(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    self::helper();
                }
                private static function helper(): void {}
            }
        '
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $statics = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_STATIC));
        $this->assertCount(1, $statics);
        $this->assertSame(CallInfo::STRONG, $statics[0]->association);
        $this->assertSame('App\Service\UserService::helper', $statics[0]->resolvedTargetFqcn);
    }

    public function testParentResolvesToParentClass(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class BaseService {
                public static function helper(): void {}
            }
            class UserService extends BaseService {
                public function run(): void {
                    parent::helper();
                }
            }
        '
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $statics = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_STATIC));
        $this->assertCount(1, $statics);
        $this->assertSame(CallInfo::STRONG, $statics[0]->association);
        $this->assertSame('App\Service\BaseService::helper', $statics[0]->resolvedTargetFqcn);
    }

    public function testStaticInFinalClassResolvesToCurrentClass(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            final class UserService {
                public function run(): void {
                    static::helper();
                }
                private static function helper(): void {}
            }
        '
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $statics = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_STATIC));
        $this->assertCount(1, $statics);
        $this->assertSame(CallInfo::STRONG, $statics[0]->association);
        $this->assertSame('App\Service\UserService::helper', $statics[0]->resolvedTargetFqcn);
    }

    public function testStaticInNonFinalClassYieldsWeak(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    static::helper();
                }
                private static function helper(): void {}
            }
        '
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $statics = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_STATIC));
        $this->assertCount(1, $statics);
        $this->assertSame(CallInfo::WEAK, $statics[0]->association);
        $this->assertSame('App\Service\UserService::helper', $statics[0]->resolvedTargetFqcn);
    }

    public function testThisResolvesToCurrentClass(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    $this->helper();
                }
                private function helper(): void {}
            }
        '
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $dynamic = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_DYNAMIC));
        $this->assertCount(1, $dynamic);
        $this->assertSame(CallInfo::STRONG, $dynamic[0]->association);
        $this->assertSame('App\Service\UserService->helper', $dynamic[0]->resolvedTargetFqcn);
    }

    public function testGlobalCallWithLeadingBackslash(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    \strlen("a");
                }
            }
        ',
            ['strlen']
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $globals = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_GLOBAL));
        $this->assertCount(1, $globals);
        $this->assertSame(CallInfo::STRONG, $globals[0]->association);
    }

    public function testNewWithAnonymousClassSkipped(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    $obj = new class { public function test() {} };
                }
            }
        '
        );

        $bucket = $calls['App\Service\UserService']['run'] ?? [];
        $creates = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_CREATE));
        $this->assertCount(0, $creates);
    }

    public function testDynamicCallWithPropertyFetchVariableType(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                private \App\Entity\User $user;
                public function run(): void {
                    $this->user->save();
                }
            }
        '
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $dynamic = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_DYNAMIC));
        $this->assertCount(1, $dynamic);
        $this->assertSame(CallInfo::WEAK, $dynamic[0]->association);
    }

    public function testGlobalCallOnlyMatchesExactFunctionName(): void
    {
        $calls = $this->collectAndResolve(
            '
            namespace App\Service;
            class UserService {
                public function run(): void {
                    strlen("a");
                    substr("b", 0, 1);
                }
            }
        ',
            ['strlen']
        );

        $bucket = $calls['App\Service\UserService']['run'];
        $globals = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_GLOBAL));
        $this->assertCount(1, $globals);
        $this->assertSame('strlen', $globals[0]->targetName);
        $this->assertSame(CallInfo::STRONG, $globals[0]->association);
    }
}
