<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer;

use PhpParser\Error;
use PhpParser\Node;
use PhpParser\ParserFactory;
use PhpParser\Parser as PhpParser;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;

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
            throw new ParserException("File not found: $filePath");
        }

        $code = file_get_contents($filePath);
        if ($code === false) {
            throw new ParserException("Could not read file: $filePath");
        }

        try {
            $ast = $this->parser->parse($code);
        } catch (Error $e) {
            throw new ParserException(
                sprintf("Parse error in %s on line %d: %s", $filePath, $e->getStartLine(), $e->getRawMessage()),
                $e->getCode(),
                $e
            );
        }

        if ($ast === null) {
            throw new ParserException("Could not parse file: $filePath");
        }

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());

        return $traverser->traverse($ast);
    }
}
