<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer;

use PhpParser\Node;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use SineFine\Ponymator\Analyzer\Extractor\AstHelper;
use SineFine\Ponymator\Analyzer\Extractor\ClassExtractor;
use SineFine\Ponymator\Analyzer\Extractor\EnumExtractor;
use SineFine\Ponymator\Analyzer\Extractor\InterfaceExtractor;
use SineFine\Ponymator\Analyzer\Extractor\TraitExtractor;
use SineFine\Ponymator\Analyzer\Visitor\DependencyCollectingVisitor;
use SineFine\Ponymator\Analyzer\Visitor\EntityExtractingVisitor;
use SineFine\Ponymator\Analyzer\Visitor\ObjectCreationCollectingVisitor;

final class EntityAnalyzer
{
    /**
     * @param array<int, Node> $ast
     */
    public function analyze(array $ast): EntityAnalysisResult
    {
        $namespace = $this->findNamespace($ast) ?? '';
        $astHelper = new AstHelper();

        $entityVisitor = new EntityExtractingVisitor(
            [
            new ClassExtractor($namespace, $astHelper),
            new InterfaceExtractor($namespace, $astHelper),
            new TraitExtractor($namespace, $astHelper),
            new EnumExtractor($namespace, $astHelper),
            ]
        );

        $dependencyVisitor = new DependencyCollectingVisitor();
        $creationVisitor = new ObjectCreationCollectingVisitor();

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($entityVisitor);
        $traverser->addVisitor($dependencyVisitor);
        $traverser->addVisitor($creationVisitor);
        $traverser->traverse($ast);

        return new EntityAnalysisResult(
            entities: $entityVisitor->entities(),
            dependencies: $dependencyVisitor->dependencies(),
            creations: $creationVisitor->getAllCreates(),
        );
    }

    /**
     * @param array<int, Node> $ast
     */
    private function findNamespace(array $ast): ?string
    {
        foreach ($ast as $node) {
            if ($node instanceof Namespace_) {
                // TODO: handles only the first namespace — entities in subsequent blocks get wrong FQCN
                return $node->name?->toCodeString();
            }
        }

        return null;
    }
}
