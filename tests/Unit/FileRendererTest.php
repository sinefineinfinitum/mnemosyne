<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Documentation\Renderer\FileRenderer;
use SineFine\Ponymator\Documentation\Renderer\MarkdownBuilder;

final class FileRendererTest extends TestCase
{
    private FileRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new FileRenderer(new MarkdownBuilder());
    }

    public function testRenderFileIncludesFrontmatter(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], [], [], '');
        $this->assertStringContainsString('type: file', $result);
    }

    public function testRenderFileIncludesPath(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], [], [], '');
        $this->assertStringContainsString('templates/header.php', $result);
    }

    public function testRenderFileWithFunctions(): void
    {
        $functions = [
            ['name' => 'renderHeader', 'parameters' => [['name' => 'title', 'type' => 'string', 'typeNullable' => false, 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false]], 'returnType' => 'void', 'returnTypeNullable' => false],
        ];
        $result = $this->renderer->renderFile('templates/header.php', $functions, [], [], '');

        $this->assertStringContainsString('renderHeader', $result);
        $this->assertStringContainsString('function renderHeader(string $title): void', $result);
    }

    public function testRenderFileWithFunctionsNoLeadingWhitespaceInSignature(): void
    {
        $functions = [
            ['name' => 'foo', 'parameters' => [], 'returnType' => 'void', 'returnTypeNullable' => false],
        ];
        $result = $this->renderer->renderFile('test.php', $functions, [], [], '');
        $this->assertStringContainsString("```php\nfunction foo(): void\n```", $result);
    }

    public function testRenderFileWithGlobals(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], ['siteName', 'currentUser'], [], '');
        $this->assertStringContainsString('$siteName', $result);
        $this->assertStringContainsString('$currentUser', $result);
    }

    public function testRenderFileNoFunctions(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], [], [], '');
        $this->assertStringNotContainsString('Global functions', $result);
    }

    public function testRenderFileWithConstants(): void
    {
        $constants = [
            ['name' => 'SITE_NAME', 'value' => "'My Site'"],
        ];
        $result = $this->renderer->renderFile('templates/header.php', [], [], $constants, '');
        $this->assertStringContainsString('SITE_NAME', $result);
        $this->assertStringContainsString("'My Site'", $result);
    }
}
