<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PhpParser\Parser as PhpParser;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use RuntimeException;

class Parser
{
    private PhpParser $parser;

    public function __construct()
    {
        $this->parser = (new ParserFactory())->createForNewestSupportedVersion();
    }

    /**
     * @return array<int, Node>
     */
    public function parseFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            throw new RuntimeException("File not found: $filePath");
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            throw new RuntimeException("Could not read file: $filePath");
        }

        try {
            $ast = $this->parser->parse($code);
        } catch (Error $e) {
            throw new RuntimeException(
                sprintf("Parse error in %s on line %d: %s", $filePath, $e->getStartLine(), $e->getRawMessage())
            );
        }

        if ($ast === null) {
            throw new RuntimeException("Could not parse file: $filePath");
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        return $traverser->traverse($ast);
    }
}
