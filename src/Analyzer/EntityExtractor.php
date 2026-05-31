<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use SineFine\Ponymator\Analyzer\Extractor\AstHelper;
use SineFine\Ponymator\Analyzer\Extractor\ClassExtractor;
use SineFine\Ponymator\Analyzer\Extractor\EnumExtractor;
use SineFine\Ponymator\Analyzer\Extractor\InterfaceExtractor;
use SineFine\Ponymator\Analyzer\Extractor\TraitExtractor;
use SineFine\Ponymator\Analyzer\Visitor\EntityExtractingVisitor;

class EntityExtractor
{
    /**
     * @param  array<int, Node> $ast
     * @return array<int, array<string, mixed>>
     */
    public function extractEntities(array $ast): array
    {
        $namespace = $this->findNamespace($ast) ?? '';
        $astHelper = new AstHelper();

        $extractors = [
            new ClassExtractor($namespace, $astHelper),
            new InterfaceExtractor($namespace, $astHelper),
            new TraitExtractor($namespace, $astHelper),
            new EnumExtractor($namespace, $astHelper),
        ];

        $visitor = new EntityExtractingVisitor($extractors);

        $traverser = new NodeTraverser();
        $traverser->addVisitor($visitor);
        $traverser->traverse($ast);

        return $visitor->entities();
    }

    /**
     * @param array<int, Node> $ast
     */
    private function findNamespace(array $ast): ?string
    {
        foreach ($ast as $node) {
            if ($node instanceof Namespace_) {
                return $node->name?->toCodeString();
            }
        }
        return null;
    }
}
