<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Generator;

use PhpParser\Node;
use SineFine\Ponymator\Analyzer\DependencyAnalyzer;
use SineFine\Ponymator\Analyzer\EntityExtractor;
use SineFine\Ponymator\Analyzer\FileExtractor;
use SineFine\Ponymator\Analyzer\Link\CrossReferenceContext;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Documentation\Renderer\EntityRendererInterface;
use SineFine\Ponymator\Documentation\Renderer\FileRenderer;
use SineFine\Ponymator\Documentation\Generator\CrossReference;
use SineFine\Ponymator\Filesystem\PathResolver;

final class FileDocumenter
{
    private ?CrossReferenceContext $context = null;

    /**
     * @param Parser                    $parser
     * @param EntityExtractor           $entityExtractor
     * @param FileExtractor             $fileExtractor
     * @param DependencyAnalyzer        $dependencyAnalyzer
     * @param EntityRendererInterface[] $renderers
     * @param FileRenderer              $fileRenderer
     * @param PathResolver              $pathResolver
     */
    public function __construct(
        private Parser $parser,
        private EntityExtractor $entityExtractor,
        private FileExtractor $fileExtractor,
        private DependencyAnalyzer $dependencyAnalyzer,
        private array $renderers,
        private FileRenderer $fileRenderer,
        private PathResolver $pathResolver,
    ) {
    }


    public function document(string $sourcePath, string $relativePath): string
    {
        $ast = $this->parser->parseFile($sourcePath);
        $entities = $this->entityExtractor->extractEntities($ast);

        if (!empty($entities)) {
            return $this->renderEntities($ast, $entities, $relativePath);
        }

        return $this->renderFileGlobals($ast, $relativePath);
    }

    /**
     * @param array<int, Node>                 $ast
     * @param array<int, array<string, mixed>> $entities
     */
    private function renderEntities(array $ast, array $entities, string $relativePath): string
    {
        $content = '';
        $currentDocPath = $this->pathResolver->docRelativePath($relativePath);

        $linker = $this->context !== null
            ? new DocLinker($this->context->getFqnToDocPath(), $this->pathResolver)
            : null;

        $dependencies = $linker?->mapToLinks(
            $this->dependencyAnalyzer->extractDependencies($ast),
            $currentDocPath
        ) ?? [];

        $typeLinkResolver = $linker !== null
            ? fn(string $fqn): ?string => $linker->resolveTypeLink($fqn, $currentDocPath)
            : fn(string $fqn): ?string => null;

        foreach ($entities as $entity) {
            $usedBy = $this->context?->getIndex()->getUsedBy(ltrim($entity['fqn'], '\\')) ?? [];
            $usedByLinks = $linker?->mapToLinks($usedBy, $currentDocPath) ?? [];

            $crossRef = new CrossReference(
                $dependencies,
                $usedByLinks,
                $typeLinkResolver
            );

            $content .= $this->renderEntityByType($entity, $crossRef);
        }

        return $content;
    }

    /**
     * @param array<int, Node> $ast
     */
    private function renderFileGlobals(array $ast, string $relativePath): string
    {
        return $this->fileRenderer->renderFile(
            $relativePath,
            $this->fileExtractor->extractFunctions($ast),
            $this->fileExtractor->extractGlobals($ast),
            $this->fileExtractor->extractConstants($ast),
        );
    }
    public function setContext(CrossReferenceContext $context): void
    {
        $this->context = $context;
    }

    /**
     * @param array<string, mixed> $entity
     * @param CrossReference       $crossRefs
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
