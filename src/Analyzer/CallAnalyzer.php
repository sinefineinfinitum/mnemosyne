<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer;

use PhpParser\Node;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use SineFine\Mnemosyne\Analyzer\Visitor\CallAssociationVisitor;
use SineFine\Mnemosyne\Analyzer\Visitor\CallCollectingVisitor;

final class CallAnalyzer
{
    /**
     * Analyze PHP code to extract and resolve method calls.
     *
     * @param string   $code             PHP source code
     * @param string[] $projectFunctions List of project-defined function names
     */
    public function analyze(string $code, array $projectFunctions = []): CallAnalysisResult
    {
        $ast = $this->parseCode($code);

        $collected = $this->collectCalls($ast);
        $resolved = $this->resolveCalls($ast, $collected['calls'], $collected['fileCalls'], $projectFunctions);

        return new CallAnalysisResult(
            $resolved['calls'],
            $resolved['fileCalls'],
        );
    }

    /**
     * Analyze AST to extract and resolve method calls.
     *
     * @param array<int, Node> $ast
     * @param string[]         $projectFunctions List of project-defined function names
     */
    public function analyzeAst(array $ast, array $projectFunctions = []): CallAnalysisResult
    {
        $collected = $this->collectCalls($ast);
        $resolved = $this->resolveCalls($ast, $collected['calls'], $collected['fileCalls'], $projectFunctions);

        return new CallAnalysisResult(
            $resolved['calls'],
            $resolved['fileCalls'],
        );
    }

    /**
     * Collect all method calls from AST.
     *
     * @param  array<int, Node> $ast
     * @return array{
     *     calls: array<string, array<string, list<CallInfo>>>,
     *     fileCalls: array<string, list<CallInfo>>
     * }
     */
    private function collectCalls(array $ast): array
    {
        if ($ast === []) {
            return ['calls' => [], 'fileCalls' => []];
        }
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $visitor = new CallCollectingVisitor();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);
        return [
            'calls' => $visitor->getCalls(),
            'fileCalls' => $visitor->getFileCalls(),
        ];
    }

    /**
     * Resolve collected calls to their target classes/methods.
     *
     * @param  array<int, Node>                             $ast
     * @param  array<string, array<string, list<CallInfo>>> $calls
     * @param  array<string, list<CallInfo>>                $fileCalls
     * @param  string[]                                     $projectFunctions
     * @return array{calls: array<string, array<string, list<CallInfo>>>, fileCalls: array<string, list<CallInfo>>}
     */
    private function resolveCalls(array $ast, array $calls, array $fileCalls, array $projectFunctions = []): array
    {
        if ($ast === []) {
            return ['calls' => $calls, 'fileCalls' => $fileCalls];
        }
        $resolver = new CallAssociationVisitor($projectFunctions);
        return $resolver->resolve($ast, $calls, $fileCalls);
    }

    /**
     * Parse PHP code into AST.
     *
     * @param  string $code
     * @return array<int, Node>
     */
    private function parseCode(string $code): array
    {
        if (trim($code) === '') {
            return [];
        }
        $parser = (new ParserFactory())->createForHostVersion();
        $ast = $parser->parse('<?php ' . $code);
        if ($ast === null) {
            return [];
        }
        return $ast;
    }
}
