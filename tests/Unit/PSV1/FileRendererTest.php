<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Tests\Unit\PSV1;

use PHPUnit\Framework\TestCase;
use SineFine\Mnemosyne\Analyzer\CallInfo;
use SineFine\Mnemosyne\Documentation\Renderer\PSV1\FileRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\PSV1\Psv1Builder;

final class FileRendererTest extends TestCase
{
    private FileRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new FileRenderer(new Psv1Builder());
    }

    public function testRenderFileHeader(): void
    {
        $result = $this->renderer->renderFile('src/functions.php', [], [], []);
        $this->assertStringContainsString('@file src/functions.php', $result);
    }

    public function testRenderFileWithFunctions(): void
    {
        $functions = [
            [
                'name' => 'getUser',
                'isStatic' => false,
                'parameters' => [
                    ['name' => 'id', 'type' => 'int', 'defaultValue' => null, 'isPassedByReference' => false],
                ],
                'returnType' => '?App\Entity\User',
            ],
        ];
        $result = $this->renderer->renderFile('src/functions.php', $functions, [], []);
        $this->assertStringContainsString('.getUser', $result);
        $this->assertStringContainsString('    $id:int', $result);
        $this->assertStringContainsString('    :App\Entity\User|null', $result);
    }

    public function testRenderFileWithConstants(): void
    {
        $constants = [
            ['name' => 'MAX_RETRIES', 'type' => 'int', 'value' => '3'],
        ];
        $result = $this->renderer->renderFile('src/functions.php', [], [], $constants);
        $this->assertStringContainsString('!MAX_RETRIES:int=3', $result);
    }

    public function testRenderFileWithGlobals(): void
    {
        $globals = ['debugMode'];
        $result = $this->renderer->renderFile('src/functions.php', [], $globals, []);
        $this->assertStringContainsString('$debugMode', $result);
    }

    public function testRenderFileAllElements(): void
    {
        $functions = [
            [
                'name' => 'helper',
                'isStatic' => false,
                'parameters' => [],
                'returnType' => 'void',
            ],
        ];
        $constants = [
            ['name' => 'VERSION', 'type' => 'string', 'value' => "'1.0'"],
        ];
        $globals = ['logger'];
        $result = $this->renderer->renderFile('src/bootstrap.php', $functions, $globals, $constants);
        $this->assertStringContainsString('@file src/bootstrap.php', $result);
        $this->assertStringContainsString('.helper', $result);
        $this->assertStringContainsString("!VERSION:string='1.0'", $result);
        $this->assertStringContainsString('$logger', $result);
    }

    public function testRenderFileMultipleGlobals(): void
    {
        $globals = ['debugMode', 'siteName'];
        $result = $this->renderer->renderFile('src/functions.php', [], $globals, []);
        $this->assertStringContainsString('$debugMode', $result);
        $this->assertStringContainsString('$siteName', $result);
    }

    public function testRenderFileNoFunctions(): void
    {
        $result = $this->renderer->renderFile('empty.php', [], [], []);
        $this->assertStringContainsString('@file empty.php', $result);
        $this->assertStringNotContainsString(PHP_EOL . '.', $result);
    }

    public function testRenderFileNoConstants(): void
    {
        $result = $this->renderer->renderFile('empty.php', [], [], []);
        $this->assertStringNotContainsString('!', $result);
    }

    public function testRenderFileNoGlobals(): void
    {
        $result = $this->renderer->renderFile('empty.php', [], [], []);
        $this->assertStringNotContainsString('$', $result);
    }

    public function testRenderFileDeterministic(): void
    {
        $functions = [
            ['name' => 'b', 'isStatic' => false, 'parameters' => [], 'returnType' => 'void'],
            ['name' => 'a', 'isStatic' => false, 'parameters' => [], 'returnType' => 'void'],
        ];
        $constants = [
            ['name' => 'Z', 'type' => 'int', 'value' => '1'],
            ['name' => 'A', 'type' => 'int', 'value' => '2'],
        ];
        $globals = ['y', 'x'];
        $first = $this->renderer->renderFile('f.php', $functions, $globals, $constants);
        $second = $this->renderer->renderFile('f.php', $functions, $globals, $constants);
        $this->assertSame($first, $second);
    }

    public function testRenderFileFunctionWithByRefParam(): void
    {
        $functions = [
            [
                'name' => 'process',
                'isStatic' => false,
                'parameters' => [
                    ['name' => 'data', 'type' => 'array', 'defaultValue' => null, 'isPassedByReference' => true],
                ],
                'returnType' => 'bool',
            ],
        ];
        $result = $this->renderer->renderFile('f.php', $functions, [], []);
        $this->assertStringContainsString('    &$data:array', $result);
    }

    public function testRenderFileFunctionWithDefaultParam(): void
    {
        $functions = [
            [
                'name' => 'connect',
                'isStatic' => false,
                'parameters' => [
                    ['name' => 'timeout', 'type' => 'int', 'defaultValue' => '30', 'isPassedByReference' => false],
                ],
                'returnType' => 'void',
            ],
        ];
        $result = $this->renderer->renderFile('f.php', $functions, [], []);
        $this->assertStringContainsString('    $timeout:int=30', $result);
    }

    public function testRenderFileWithFileCallsEmitsCallGraph(): void
    {
        $functions = [
            [
                'name' => 'loadConfig',
                'isStatic' => false,
                'parameters' => [],
                'returnType' => 'array',
            ],
        ];
        $calls = [
            'loadConfig' => [
                new CallInfo(CallInfo::KIND_STATIC, 'parseIni', [], 'App\\Config\\Parser::parseIni', CallInfo::STRONG),
            ],
        ];
        $result = $this->renderer->renderFile('config.php', $functions, [], [], $calls);

        $this->assertStringContainsString('    *App\\Config\\Parser::parseIni', $result);
    }

    public function testRenderFileWithoutFileCallsOmitsCallGraph(): void
    {
        $functions = [
            [
                'name' => 'helper',
                'isStatic' => false,
                'parameters' => [],
                'returnType' => 'void',
            ],
        ];
        $result = $this->renderer->renderFile('utils.php', $functions, [], [], []);
        $this->assertStringNotContainsString(' (static)', $result);
        $this->assertStringNotContainsString(' (dynamic)', $result);
    }

    public function testRenderFileFileCallsIgnoreUnknownFunctions(): void
    {
        $functions = [
            [
                'name' => 'real',
                'isStatic' => false,
                'parameters' => [],
                'returnType' => 'void',
            ],
        ];
        $calls = [
            'unknownFunction' => [
                new CallInfo(CallInfo::KIND_GLOBAL, 'doStuff'),
            ],
        ];
        $result = $this->renderer->renderFile('x.php', $functions, [], [], $calls);
        $this->assertStringNotContainsString(' (global)', $result);
    }

    public function testRenderFileWithFileCallsMultipleCallKinds(): void
    {
        $functions = [
            [
                'name' => 'process',
                'isStatic' => false,
                'parameters' => [],
                'returnType' => 'void',
            ],
        ];
        $calls = [
            'process' => [
                new CallInfo(CallInfo::KIND_STATIC, 'init', [], 'App\\Init::init', CallInfo::STRONG),
                new CallInfo(CallInfo::KIND_DYNAMIC, 'run', ['App\\Runner\\A', 'App\\Runner\\B']),
                new CallInfo(CallInfo::KIND_GLOBAL, 'cleanup', [], 'cleanup', CallInfo::STRONG),
            ],
        ];
        $result = $this->renderer->renderFile('proc.php', $functions, [], [], $calls);

        $this->assertStringContainsString('    *App\\Init::init', $result);
        $this->assertStringContainsString('    ?App\\Runner\\A->run', $result);
        $this->assertStringContainsString('    ?App\\Runner\\B->run', $result);
        $this->assertStringContainsString('    *cleanup', $result);
    }
}
