<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Documentation\Renderer\FileRenderer;

final class FileRendererTest extends TestCase
{
    private FileRenderer $renderer;

    protected function setUp(): void
    {
        $this->renderer = new FileRenderer();
    }

    public function testRenderFileIncludesFrontmatter(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], [], 'def456');
        $this->assertStringContainsString('psr4: false', $result);
        $this->assertStringContainsString('source_hash: def456', $result);
    }

    public function testRenderFileIncludesPath(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], [], 'def456');
        $this->assertStringContainsString('templates/header.php', $result);
    }

    public function testRenderFileIncludesFileTypeFields(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], [], 'def456');
        $this->assertStringContainsString('**Type:** `file`', $result);
        $this->assertStringContainsString('**Parent:** none', $result);
        $this->assertStringContainsString('**Interfaces:** none', $result);
    }

    public function testRenderFileWithFunctions(): void
    {
        $functions = [
            ['name' => 'renderHeader', 'parameters' => [['name' => 'title', 'type' => 'string', 'typeNullable' => false, 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false]], 'returnType' => 'void', 'returnTypeNullable' => false],
        ];
        $result = $this->renderer->renderFile('templates/header.php', $functions, [], 'def456');

        $this->assertStringContainsString('renderHeader', $result);
        $this->assertStringContainsString('function renderHeader(string $title): void', $result);
    }

    public function testRenderFileWithGlobals(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], ['siteName', 'currentUser'], 'def456');
        $this->assertStringContainsString('$siteName', $result);
        $this->assertStringContainsString('$currentUser', $result);
    }

    public function testRenderFileNoFunctions(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], [], 'def456');
        $this->assertStringNotContainsString('Functions', $result);
    }
}
