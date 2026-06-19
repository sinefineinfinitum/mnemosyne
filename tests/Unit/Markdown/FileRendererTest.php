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

    public function testRenderFileWithFunctions(): void
    {
        $functions = [
            ['name' => 'renderHeader', 'parameters' => [['name' => 'title', 'type' => 'string', 'defaultValue' => null, 'isVariadic' => false, 'isPassedByReference' => false]], 'returnType' => 'void', ],
        ];
        $result = $this->renderer->renderFile('templates/header.php', $functions, [], [], []);

        $this->assertSame("---\ntype: file\nhash: ac83b11856fd\n---\n\n# `templates/header.php`\n\n### Global functions\n\n#### renderHeader\n```php\nfunction renderHeader(string \$title): void\n```\n\n", $result);
    }

    public function testRenderFileWithFunctionsNoLeadingWhitespaceInSignature(): void
    {
        $functions = [
            ['name' => 'foo', 'parameters' => [], 'returnType' => 'void', ],
        ];
        $result = $this->renderer->renderFile('test.php', $functions, [], [], []);
        $this->assertSame("---\ntype: file\nhash: df8bb0db4f8e\n---\n\n# `test.php`\n\n### Global functions\n\n#### foo\n```php\nfunction foo(): void\n```\n\n", $result);
    }

    public function testRenderFileWithGlobals(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], ['siteName', 'currentUser'], [], []);
        $this->assertSame("---\ntype: file\nhash: 49733a34d896\n---\n\n# `templates/header.php`\n\n### Global variables\n\n- `\$siteName`\n- `\$currentUser`\n\n", $result);
    }

    public function testRenderFileNoFunctions(): void
    {
        $result = $this->renderer->renderFile('templates/header.php', [], [], [], []);
        $this->assertSame("---\ntype: file\nhash: e3b0c44298fc\n---\n\n# `templates/header.php`\n\n", $result);
    }

    public function testRenderFileWithConstants(): void
    {
        $constants = [
            ['name' => 'SITE_NAME', 'value' => "'My Site'"],
        ];
        $result = $this->renderer->renderFile('templates/header.php', [], [], $constants, []);
        $this->assertSame("---\ntype: file\nhash: d6fd65107193\n---\n\n# `templates/header.php`\n\n### Global constants\n\n|Name|Value|\n|---|---|\n|`SITE_NAME`|`'My Site'`|\n\n", $result);
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

        $this->assertSame("---\ntype: file\nhash: 1ba5db88e2b2\n---\n\n# `config.php`\n\n### Global functions\n\n#### loadConfig\n```php\nfunction loadConfig(): array\n```\n- **Calls:**\n  - `weak` `parse`\n\n", $result);
    }

    public function testRenderFileWithoutFileCallsOmitsCallGraph(): void
    {
        $functions = [
            ['name' => 'helper', 'parameters' => [], 'returnType' => 'void', ],
        ];
        $result = $this->renderer->renderFile('utils.php', $functions, [], [], []);

        $this->assertSame("---\ntype: file\nhash: 011d36ea7bf3\n---\n\n# `utils.php`\n\n### Global functions\n\n#### helper\n```php\nfunction helper(): void\n```\n\n", $result);
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

        $this->assertSame("---\ntype: file\nhash: 5ab5242f6dd5\n---\n\n# `x.php`\n\n### Global functions\n\n#### a\n```php\nfunction a(): void\n```\n\n", $result);
    }
}
