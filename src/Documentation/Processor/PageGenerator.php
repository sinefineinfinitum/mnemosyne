<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Processor;

use SineFine\Ponymator\Analyzer\CombinedAnalysisResult;
use SineFine\Ponymator\Analyzer\CombinedAnalyzer;
use SineFine\Ponymator\Analyzer\FileExtractor;
use SineFine\Ponymator\Analyzer\Linker\CrossReferenceContext;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Documentation\Linker\CrossReference;
use SineFine\Ponymator\Documentation\Linker\CrossReferenceFactory;
use SineFine\Ponymator\Documentation\Renderer\EntityRendererInterface;
use SineFine\Ponymator\Documentation\Renderer\FileRendererInterface;

final class PageGenerator
{
    private ?CrossReferenceContext $context = null;

    /**
     * @param Parser                    $parser
     * @param CombinedAnalyzer          $combinedAnalyzer
     * @param FileExtractor             $fileExtractor
     * @param EntityRendererInterface[] $renderers
     * @param FileRendererInterface     $fileRenderer
     * @param CrossReferenceFactory     $crossReferenceFactory
     */
    public function __construct(
        private Parser $parser,
        private CombinedAnalyzer $combinedAnalyzer,
        private FileExtractor $fileExtractor,
        private array $renderers,
        private FileRendererInterface $fileRenderer,
        private CrossReferenceFactory $crossReferenceFactory,
    ) {
    }

    public function document(string $sourcePath, string $relativePath): string
    {
        $ast = $this->parser->parseFile($sourcePath);
        $analysis = $this->combinedAnalyzer->analyze($ast);
        $entities = $analysis->getEntities();

        $functions = $this->fileExtractor->extractFunctions($ast);
        $globals = $this->fileExtractor->extractGlobals($ast);
        $constants = $this->fileExtractor->extractConstants($ast);

        $hasFileLevel = $functions !== [] || $globals !== [] || $constants !== [];

        $content = '';

        if ($entities !== []) {
            $content .= $this->renderEntities($analysis, $entities, $relativePath);
        }

        if ($hasFileLevel) {
            $content .= $this->fileRenderer->renderFile(
                $relativePath,
                $functions,
                $globals,
                $constants,
            );
        }

        return $content;
    }

    public function setContext(CrossReferenceContext $context): void
    {
        $this->context = $context;
    }

    /**
     * @param  CombinedAnalysisResult           $analysis
     * @param  array<int, array<string, mixed>> $entities
     * @param  string                           $relativePath
     * @return string
     */
    private function renderEntities(CombinedAnalysisResult $analysis, array $entities, string $relativePath): string
    {
        $content = '';
        $refProvider = $this->crossReferenceFactory->create($analysis, $this->context, $relativePath);

        foreach ($entities as $entity) {
            $crossRef = $refProvider->getCrossReference($entity['fqn']);
            $content .= $this->renderEntityByType($entity, $crossRef);
        }

        return $content;
    }

    /**
     * @param  array<string, mixed> $entity
     * @param  CrossReference       $crossRefs
     * @return string
     */
    private function renderEntityByType(array $entity, CrossReference $crossRefs): string
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($entity)) {
                return $renderer->renderEntity($entity, $crossRefs);
            }
        }
        return '';
    }
}
