<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Documentation\Generator;

use SineFine\Mnemosyne\Analyzer\CallInfo;
use SineFine\Mnemosyne\Analyzer\EntityAnalysisResult;
use SineFine\Mnemosyne\Analyzer\EntityAnalyzer;
use SineFine\Mnemosyne\Analyzer\FileExtractor;
use SineFine\Mnemosyne\Analyzer\Linker\CrossReferenceContext;
use SineFine\Mnemosyne\Analyzer\Parser;
use SineFine\Mnemosyne\Analyzer\CallAnalysisResult;
use SineFine\Mnemosyne\Analyzer\CallAnalyzer;
use SineFine\Mnemosyne\Documentation\Linker\CrossReference;
use SineFine\Mnemosyne\Documentation\Linker\CrossReferenceFactory;
use SineFine\Mnemosyne\Documentation\Renderer\EntityRendererInterface;
use SineFine\Mnemosyne\Documentation\Renderer\FileRendererInterface;

final class PageGenerator
{
    private ?CrossReferenceContext $context = null;

    private ?CallAnalysisResult $callAnalysisResult = null;

    /**
     * @param Parser                    $parser
     * @param EntityAnalyzer            $entityAnalyzer
     * @param FileExtractor             $fileExtractor
     * @param EntityRendererInterface[] $renderers
     * @param FileRendererInterface     $fileRenderer
     * @param CrossReferenceFactory     $crossReferenceFactory
     * @param CallAnalyzer|null         $callAnalyzer
     */
    public function __construct(
        private Parser                $parser,
        private EntityAnalyzer        $entityAnalyzer,
        private FileExtractor         $fileExtractor,
        private array                 $renderers,
        private FileRendererInterface $fileRenderer,
        private CrossReferenceFactory $crossReferenceFactory,
        private ?CallAnalyzer         $callAnalyzer = null,
    ) {
    }

    public function document(string $sourcePath, string $relativePath): string
    {
        $ast = $this->parser->parseFile($sourcePath);
        $entityAnalysis = $this->entityAnalyzer->analyze($ast);
        $entities = $entityAnalysis->getEntities();

        $fileCalls = [];
        if ($this->callAnalyzer !== null) {
            $projectFunctions = $this->context?->getProjectFunctions() ?? [];
            $this->callAnalysisResult = $this->callAnalyzer->analyzeAst($ast, $projectFunctions);
            $entityAnalysis = $this->mergeCallsIntoEntityAnalysis(
                $entityAnalysis,
                $this->callAnalysisResult->getCalls(),
                $this->callAnalysisResult->getFileCalls(),
            );
            $fileCalls = $this->callAnalysisResult->getFileCalls();
        }

        $functions = $this->fileExtractor->extractFunctions($ast);
        $globals = $this->fileExtractor->extractGlobals($ast);
        $constants = $this->fileExtractor->extractConstants($ast);

        $hasFileLevel = $functions !== [] || $globals !== [] || $constants !== [];

        $content = '';

        if ($entities !== []) {
            $content .= $this->renderEntities($entityAnalysis, $entities, $relativePath);
        }

        if ($hasFileLevel) {
            $content .= $this->fileRenderer->renderFile(
                $relativePath,
                $functions,
                $globals,
                $constants,
                $fileCalls,
            );
        }

        return $content;
    }

    public function setContext(CrossReferenceContext $context): void
    {
        $this->context = $context;
    }

    /**
     * @param  EntityAnalysisResult                         $analysis
     * @param  array<string, array<string, list<CallInfo>>> $calls
     * @param  array<string, list<CallInfo>>                $fileCalls
     * @return EntityAnalysisResult
     */
    private function mergeCallsIntoEntityAnalysis(
        EntityAnalysisResult $analysis,
        array                $calls,
        array                $fileCalls = []
    ): EntityAnalysisResult {
        return new EntityAnalysisResult(
            entities: $analysis->getEntities(),
            dependencies: $analysis->getDependencies(),
            creations: $analysis->getCreations(),
            calls: $calls,
            fileCalls: $fileCalls,
        );
    }

    /**
     * @param  EntityAnalysisResult             $analysis
     * @param  array<int, array<string, mixed>> $entities
     * @param  string                           $relativePath
     * @return string
     */
    private function renderEntities(EntityAnalysisResult $analysis, array $entities, string $relativePath): string
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
