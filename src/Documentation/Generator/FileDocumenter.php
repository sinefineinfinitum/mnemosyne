<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Generator;


use SineFine\Ponymator\Analyzer\DependencyAnalyzer;
use SineFine\Ponymator\Analyzer\EntityExtractor;
use SineFine\Ponymator\Analyzer\FileExtractor;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Analyzer\PSR4Detector;
use SineFine\Ponymator\Comparator\HashComparator;
use SineFine\Ponymator\Documentation\Renderer\FileRenderer;
use SineFine\Ponymator\Documentation\Renderer\PSR4Renderer;

final class FileDocumenter
{
    public function __construct(
        private Parser $parser,
        private EntityExtractor $entityExtractor,
        private FileExtractor $fileExtractor,
        private DependencyAnalyzer $dependencyAnalyzer,
        private PSR4Detector $psr4Detector,
        private PSR4Renderer $psr4Renderer,
        private FileRenderer $fileRenderer,
        private HashComparator $hashComparator
    ) {
    }

    public function document(string $sourcePath, string $relativePath): string
    {
        $sourceHash = $this->hashComparator->computeHash($sourcePath);
        $ast = $this->parser->parseFile($sourcePath);
        $classification = $this->psr4Detector->classify($ast, $relativePath);
        $deps = $this->dependencyAnalyzer->extractDependencies($ast);

        if ($classification === 'psr4') {
            $entities = $this->entityExtractor->extractEntities($ast);
            $content = '';

            foreach ($entities as $entity) {
                $entity['dependencies'] = $deps;
                $content .= $this->psr4Renderer->renderEntity($entity, $sourceHash);
            }

            return $content;
        }

        return $this->fileRenderer->renderFile(
            $relativePath,
            $this->fileExtractor->extractFunctions($ast),
            $this->fileExtractor->extractGlobals($ast),
            $sourceHash
        );
    }
}
