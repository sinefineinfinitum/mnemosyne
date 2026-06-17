<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation;

use SineFine\Ponymator\Analyzer\CallAnalyzer;
use SineFine\Ponymator\Analyzer\EntityAnalyzer;
use SineFine\Ponymator\Analyzer\FileExtractor;
use SineFine\Ponymator\Analyzer\Linker\CrossReferenceIndexBuilder;
use SineFine\Ponymator\Analyzer\Parser;
use SineFine\Ponymator\Cli\ArgumentParser;
use SineFine\Ponymator\Comparator\HashComparator;
use SineFine\Ponymator\Config;
use SineFine\Ponymator\Documentation\Cleaner\OutdatedDocumentationRemover;
use SineFine\Ponymator\Documentation\Generator\Engine;
use SineFine\Ponymator\Documentation\Generator\PageGenerator;
use SineFine\Ponymator\Documentation\Linker\CrossReferenceFactory;
use SineFine\Ponymator\Documentation\Renderer\EntityRendererInterface;
use SineFine\Ponymator\Documentation\Renderer\FileRendererInterface;
use SineFine\Ponymator\Documentation\Renderer\Markdown\ClassRenderer as MarkdownClassRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\EnumRenderer as MarkdownEnumRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\FileRenderer as MarkdownFileRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\InterfaceRenderer as MarkdownInterfaceRenderer;
use SineFine\Ponymator\Documentation\Renderer\Markdown\MarkdownBuilder;
use SineFine\Ponymator\Documentation\Renderer\Markdown\TraitRenderer as MarkdownTraitRenderer;
use SineFine\Ponymator\Documentation\Renderer\PSV1\ClassRenderer as Psv1ClassRenderer;
use SineFine\Ponymator\Documentation\Renderer\PSV1\EnumRenderer as Psv1EnumRenderer;
use SineFine\Ponymator\Documentation\Renderer\PSV1\FileRenderer as Psv1FileRenderer;
use SineFine\Ponymator\Documentation\Renderer\PSV1\InterfaceRenderer as Psv1InterfaceRenderer;
use SineFine\Ponymator\Documentation\Renderer\PSV1\Psv1Builder;
use SineFine\Ponymator\Documentation\Renderer\PSV1\TraitRenderer as Psv1TraitRenderer;
use SineFine\Ponymator\Filesystem\PathResolver;

class GeneratorFactory
{
    public function create(Config $config, string $outputFormat): Engine
    {
        $parser = new Parser();
        $combinedAnalyzer = new EntityAnalyzer();
        $fileExtractor = new FileExtractor();

        [$entityRenderers, $fileRenderer] = $this->createRenderers($outputFormat);

        $hashComparator = new HashComparator();
        $pathResolver = new PathResolver($config, $this->docExtension($outputFormat));
        $crossReferenceFactory = new CrossReferenceFactory($pathResolver);

        $documenter = new PageGenerator(
            $parser,
            $combinedAnalyzer,
            $fileExtractor,
            $entityRenderers,
            $fileRenderer,
            $crossReferenceFactory,
            new CallAnalyzer(),
        );

        $documentRemover = new OutdatedDocumentationRemover($pathResolver);
        $crossReferenceIndexBuilder = new CrossReferenceIndexBuilder($parser, $pathResolver);

        return new Engine(
            $hashComparator,
            $pathResolver,
            $documenter,
            $documentRemover,
            $crossReferenceIndexBuilder,
        );
    }

    /**
     * @return array{0: EntityRendererInterface[], 1: FileRendererInterface}
     */
    private function createRenderers(string $output): array
    {
        if ($output === ArgumentParser::OUTPUT_PSV1) {
            $builder = new Psv1Builder();

            return [
                [
                    new Psv1ClassRenderer($builder),
                    new Psv1InterfaceRenderer($builder),
                    new Psv1TraitRenderer($builder),
                    new Psv1EnumRenderer($builder),
                ],
                new Psv1FileRenderer($builder),
            ];
        }

        $builder = new MarkdownBuilder();

        return [
            [
                new MarkdownClassRenderer($builder),
                new MarkdownInterfaceRenderer($builder),
                new MarkdownTraitRenderer($builder),
                new MarkdownEnumRenderer($builder),
            ],
            new MarkdownFileRenderer($builder),
        ];
    }

    private function docExtension(string $output): string
    {
        if ($output === ArgumentParser::OUTPUT_PSV1) {
            return 'psv1';
        }

        return 'md';
    }
}
