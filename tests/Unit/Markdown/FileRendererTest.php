<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit\Markdown;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Documentation\Renderer\Markdown\FileRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\MarkdownBuilder;

final class FileRendererTest extends TestCase
{
    private FileRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new FileRenderer(new MarkdownBuilder());
    }

    public function testRenderFileIncludesFrontmatter(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], [], [], []);
        $this->assertStringContainsString('type: file', $result);
    }

    public function testRenderFileIncludesPath(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], [], [], []);
        $this->assertStringContainsString('templates/header.php', $result);
    }

    public function testRenderFileWithFunctions(): void
    {
        $functions = [
            ['name' => 'renderHeader', 'parameters' => [['name' => 'title', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false]], 'returnType' => 'void', ],
        ];
        $result = $this->renderer->renderFile('templates/header.php', $functions, [], [], []);

        $this->assertStringContainsString('renderHeader', $result);
        $this->assertStringContainsString('function renderHeader(string $title): void', $result);
    }

    public function testRenderFileWithFunctionsNoLeadingWhitespaceInSignature(): void
    {
        $functions = [
            ['name' => 'foo', 'parameters' => [], 'returnType' => 'void', ],
        ];
        $result = $this->renderer->renderFile('test.php', $functions, [], [], []);
        $this->assertStringContainsString("```php\nfunction foo(): void\n```", $result);
    }

    public function testRenderFileWithGlobals(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], ['siteName', 'currentUser'], [], []);
        $this->assertStringContainsString('$siteName', $result);
        $this->assertStringContainsString('$currentUser', $result);
    }

    public function testRenderFileNoFunctions(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], [], [], []);
        $this->assertStringNotContainsString('Global functions', $result);
    }

    public function testRenderFileWithConstants(): void
    {
        $constants = [
            ['name' => 'SITE_NAME', 'value' => "'My Site'"],
        ];
        $result = $this->renderer->renderFile('templates/header.php', [], [], $constants, []);
        $this->assertStringContainsString('SITE_NAME', $result);
        $this->assertStringContainsString("'My Site'", $result);
    }

    public function testRenderFileWithFileCallsEmitsCallGraph(): void
    {
        $functions = [
            ['name' => 'loadConfig', 'parameters' => [], 'returnType' => 'array', ],
        ];
        $calls = [
            'loadConfig' => [
                new \SineFine\Ponymator\Analyzer\CallInfo(
                    \SineFine\Ponymator\Analyzer\CallInfo::KIND_STATIC,
                    'parse',
                ),
            ],
        ];
        $result = $this->renderer->renderFile('config.php', $functions, [], [], $calls);

        $this->assertStringContainsString('parse', $result);
        $this->assertStringContainsString('weak', $result);
        $this->assertStringContainsString('loadConfig', $result);
    }

    public function testRenderFileWithoutFileCallsOmitsCallGraph(): void
    {
        $functions = [
            ['name' => 'helper', 'parameters' => [], 'returnType' => 'void', ],
        ];
        $result = $this->renderer->renderFile('utils.php', $functions, [], [], []);

        $this->assertStringNotContainsString('**Calls:**', $result);
    }

    public function testRenderFileFileCallsIgnoreUnknownFunctions(): void
    {
        $functions = [
            ['name' => 'a', 'parameters' => [], 'returnType' => 'void', ],
        ];
        $calls = [
            'unknownFunction' => [
                new \SineFine\Ponymator\Analyzer\CallInfo(
                    \SineFine\Ponymator\Analyzer\CallInfo::KIND_GLOBAL,
                    'doStuff',
                ),
            ],
        ];
        $result = $this->renderer->renderFile('x.php', $functions, [], [], $calls);

        $this->assertStringNotContainsString('doStuff', $result);
        $this->assertStringNotContainsString('(global)', $result);
    }
}
