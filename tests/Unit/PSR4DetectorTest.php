<?php declare(strict_types=1);

namespace SineFine\Ponymator\Tests\Unit;

use PHPUnit\Framework\TestCase;
use SineFine\Ponymator\Analyzer\PSR4Detector;
use PhpParser\ParserFactory;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

class PSR4DetectorTest extends TestCase
{
    private PSR4Detector $detector;

    protected function setUp(): void
    {
        $this->detector = new PSR4Detector();
    }

    private function parseAndResolve(string $code): array
    {
        $parser = (new ParserFactory())->createForNewestSupportedVersion();
        $ast = $parser->parse($code);
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        return $traverser->traverse($ast);
    }

    public function testPsr4Match(): void
    {
        $ast = $this->parseAndResolve('<?php namespace App\Service; class UserService {}');
        $result = $this->detector->classify($ast, 'App/Service/UserService.php');
        $this->assertSame('psr4', $result);
    }

    public function testNonPsr4Mismatch(): void
    {
        $ast = $this->parseAndResolve('<?php namespace App\Service; class UserService {}');
        $result = $this->detector->classify($ast, 'templates/UserService.php');
        $this->assertSame('non-psr4', $result);
    }

    public function testNoNamespace(): void
    {
        $ast = $this->parseAndResolve('<?php class Foo {}');
        $result = $this->detector->classify($ast, 'legacy/Foo.php');
        $this->assertSame('non-psr4', $result);
    }
}
