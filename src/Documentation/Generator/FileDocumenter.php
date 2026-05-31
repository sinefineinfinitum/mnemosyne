<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Generator;


use SineFine\Ponymator\Analyzer\DependencyAnalyzer;
use SineFine\Ponymator\Analyzer\EntityExtractor;
use SineFine\Ponymator\Analyzer\FileExtractor;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Analyzer\Visitor\CrossFileScanVisitor;
use SineFine\Ponymator\Comparator\HashComparator;
use SineFine\Ponymator\Documentation\Renderer\EntityRendererInterface;
use SineFine\Ponymator\Documentation\Renderer\FileRenderer;
use SineFine\Ponymator\Filesystem\PathResolver;
use PhpParser\NodeTraverser;
use Throwable;

final class FileDocumenter
{
    /**
     * @var array<string, array<string, list<string>>>
     */
    private array $crossRefs = [];

    /**
     * @param Parser                    $parser
     * @param EntityExtractor           $entityExtractor
     * @param FileExtractor             $fileExtractor
     * @param DependencyAnalyzer        $dependencyAnalyzer
     * @param EntityRendererInterface[] $renderers
     * @param FileRenderer              $fileRenderer
     * @param HashComparator            $hashComparator
     * @param PathResolver              $pathResolver
     */
    public function __construct(
        private Parser $parser,
        private EntityExtractor $entityExtractor,
        private FileExtractor $fileExtractor,
        private DependencyAnalyzer $dependencyAnalyzer,
        private array $renderers,
        private FileRenderer $fileRenderer,
        private HashComparator $hashComparator,
        private PathResolver $pathResolver,
    ) {
    }

    /**
     * @param string[] $sourceFiles
     */
    public function buildCrossReferences(array $sourceFiles): void
    {
        $implMap = [];
        $traitMap = [];

        foreach ($sourceFiles as $relativePath) {
            $sourcePath = $this->pathResolver->sourcePath($relativePath);
            try {
                $ast = $this->parser->parseFile($sourcePath);
                $traverser = new NodeTraverser();
                $visitor = new CrossFileScanVisitor();
                $traverser->addVisitor($visitor);
                $traverser->traverse($ast);

                foreach ($visitor->getInterfacesImplemented() as $interface => $classes) {
                    $implMap[$interface] = array_merge($implMap[$interface] ?? [], $classes);
                }
                foreach ($visitor->getTraitsUsed() as $trait => $classes) {
                    $traitMap[$trait] = array_merge($traitMap[$trait] ?? [], $classes);
                }
            } catch (Throwable $e) {
                fwrite(STDERR, "Warning: Cross-file scan failed for $relativePath — " . $e->getMessage() . "\n");
            }
        }

        foreach ($implMap as $interface => $classes) {
            $classes = array_values(array_unique($classes));
            sort($classes);
            $implMap[$interface] = $classes;
        }
        foreach ($traitMap as $trait => $classes) {
            $classes = array_values(array_unique($classes));
            sort($classes);
            $traitMap[$trait] = $classes;
        }

        $this->crossRefs = [
            'implements' => $implMap,
            'trait_usage' => $traitMap,
        ];
    }

    public function document(string $sourcePath, string $relativePath): string
    {
        $sourceHash = $this->hashComparator->computeHash($sourcePath);
        $ast = $this->parser->parseFile($sourcePath);
        $entities = $this->entityExtractor->extractEntities($ast);

        if (!empty($entities)) {
            $content = '';
            $deps = $this->dependencyAnalyzer->extractDependencies($ast);

            $entityCrossRefs = $this->crossRefs;
            $entityCrossRefs['_sourceHash'] = $sourceHash;

            foreach ($entities as $entity) {
                $entity['dependencies'] = $deps;
                $content .= $this->renderEntityByType($entity, $entityCrossRefs);
            }

            return $content;
        }

        return $this->fileRenderer->renderFile(
            $relativePath,
            $this->fileExtractor->extractFunctions($ast),
            $this->fileExtractor->extractGlobals($ast),
            $this->fileExtractor->extractConstants($ast),
            $sourceHash
        );
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<string, mixed> $crossRefs
     */
    private function renderEntityByType(array $entity, array $crossRefs): string
    {
        foreach ($this->renderers as $renderer) {
            if ($renderer->supports($entity)) {
                return $renderer->renderEntity($entity, $crossRefs);
            }
        }
        return '';
    }
}
