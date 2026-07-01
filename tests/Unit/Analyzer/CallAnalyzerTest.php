<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\Analyzer;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\CallInfo;
use SineFine\Mnemosyne\Analyzer\CallAnalyzer;

final class CallAnalyzerTest extends TestCase
{
    public function testRunsPass1AndPass2(): void
    {
        $analyzer = new CallAnalyzer();

        $code = '
            namespace App\Service;
            class UserService {
                public function run(\App\Entity\User $user): void {
                    $user->save();
                    Foo::bar();
                    strlen("a");
                    new \App\Entity\User();
                }
            }
        ';

        $result = $analyzer->analyze($code, ['strlen']);

        $calls = $result->getCalls();
        $this->assertArrayHasKey('App\Service\UserService', $calls);
        $this->assertArrayHasKey('run', $calls['App\Service\UserService']);

        $bucket = $calls['App\Service\UserService']['run'];
        $this->assertCount(4, $bucket);
    }

    public function testSourceOrderPreserved(): void
    {
        $analyzer = new CallAnalyzer();

        $code = '
            namespace App\Bbb;
            class ServiceB {
                public function go(): void {
                    new \App\X\Foo();
                    Foo::bar();
                    strlen("a");
                    $obj->doIt();
                }
            }
        ';

        $result = $analyzer->analyze($code, ['strlen']);
        $bucket = $result->getCalls()['App\Bbb\ServiceB']['go'];

        $kinds = array_map(fn(CallInfo $c) => $c->kind, $bucket);
        $this->assertSame(
            [CallInfo::KIND_CREATE, CallInfo::KIND_STATIC, CallInfo::KIND_GLOBAL, CallInfo::KIND_DYNAMIC],
            $kinds
        );
    }

    public function testEmptyInput(): void
    {
        $analyzer = new CallAnalyzer();
        $result = $analyzer->analyze('');

        $this->assertSame([], $result->getCalls());
    }

    public function testPreservesStrongResolution(): void
    {
        $analyzer = new CallAnalyzer();

        $code = '
            namespace App\Service;
            class UserService {
                public function run(\App\Entity\User $user): void {
                    $user->save();
                }
            }
        ';

        $result = $analyzer->analyze($code);
        $bucket = $result->getCalls()['App\Service\UserService']['run'];
        $dynamic = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_DYNAMIC));
        $this->assertSame(CallInfo::STRONG, $dynamic[0]->association);
    }

    public function testPreservesWeakWhenAmbiguous(): void
    {
        $analyzer = new CallAnalyzer();

        $code = '
            namespace App\Service;
            class UserService {
                public function run(\App\A|\App\B $x): void {
                    $x->do();
                }
            }
        ';

        $result = $analyzer->analyze($code);
        $bucket = $result->getCalls()['App\Service\UserService']['run'];
        $dynamic = array_values(array_filter($bucket, fn(CallInfo $c) => $c->kind === CallInfo::KIND_DYNAMIC));
        $this->assertSame(CallInfo::WEAK, $dynamic[0]->association);
        $this->assertCount(2, $dynamic[0]->candidateTypes);
    }

    public function testReturnsFileCallsForFileLevelFunctions(): void
    {
        $analyzer = new CallAnalyzer();

        $code = '
            namespace App;
            function loadConfig(): array {
                $result = parse_ini_file("a.ini");
                return $result;
            }
        ';

        // Add 'parse_ini_file' to projectFunctions to prevent it from being filtered out
        $result = $analyzer->analyze($code, ['parse_ini_file']);

        $fileCalls = $result->getFileCalls();
        $this->assertArrayHasKey('loadConfig', $fileCalls);
        $this->assertCount(1, $fileCalls['loadConfig']);
        $this->assertSame(CallInfo::KIND_GLOBAL, $fileCalls['loadConfig'][0]->kind);
        $this->assertSame('parse_ini_file', $fileCalls['loadConfig'][0]->targetName);
    }

    public function testFileCallsAreEmptyForClassOnlyInput(): void
    {
        $analyzer = new CallAnalyzer();

        $code = '
            namespace App;
            class Foo {
                public function bar(): void {
                    strlen("a");
                }
            }
        ';

        $result = $analyzer->analyze($code);

        $this->assertSame([], $result->getFileCalls());
    }

    public function testFileCallsPreserveSourceOrder(): void
    {
        $analyzer = new CallAnalyzer();

        $code = '
            function zzz(): void { strlen("z"); }
            function aaa(): void { strlen("a"); }
        ';

        $result = $analyzer->analyze($code);

        $names = array_keys($result->getFileCalls());
        $this->assertSame(['zzz', 'aaa'], $names);
    }
}
