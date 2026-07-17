<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Documentation;

use SineFine\Mnemosyne\Analyzer\CallAnalyzer;
use SineFine\Mnemosyne\Analyzer\EntityAnalyzer;
use SineFine\Mnemosyne\Analyzer\FileExtractor;
use SineFine\Mnemosyne\Analyzer\Linker\CrossReferenceIndexBuilder;
use SineFine\Mnemosyne\Analyzer\Parser;
use SineFine\Mnemosyne\Cli\ArgumentParser;
use SineFine\Mnemosyne\Comparator\HashComparator;
use SineFine\Mnemosyne\Config;
use SineFine\Mnemosyne\Documentation\Cleaner\OutdatedDocumentationRemover;
use SineFine\Mnemosyne\Documentation\Generator\Engine;
use SineFine\Mnemosyne\Documentation\Generator\PageGenerator;
use SineFine\Mnemosyne\Documentation\Linker\CrossReferenceFactory;
use SineFine\Mnemosyne\Documentation\Renderer\EntityRendererInterface;
use SineFine\Mnemosyne\Documentation\Renderer\FileRendererInterface;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\ClassRenderer as MarkdownClassRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\EnumRenderer as MarkdownEnumRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\FileRenderer as MarkdownFileRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\InterfaceRenderer as MarkdownInterfaceRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\MarkdownBuilder;
use SineFine\Mnemosyne\Documentation\Renderer\Markdown\TraitRenderer as MarkdownTraitRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\MSV1\ClassRenderer as Msv1ClassRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\MSV1\EnumRenderer as Msv1EnumRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\MSV1\FileRenderer as Msv1FileRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\MSV1\InterfaceRenderer as Msv1InterfaceRenderer;
use SineFine\Mnemosyne\Documentation\Renderer\MSV1\Msv1Builder;
use SineFine\Mnemosyne\Documentation\Renderer\MSV1\TraitRenderer as Msv1TraitRenderer;
use SineFine\Mnemosyne\Filesystem\PathResolver;

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
        if ($output === ArgumentParser::OUTPUT_MSV1) {
            $builder = new Msv1Builder();

            return [
                [
                    new Msv1ClassRenderer($builder),
                    new Msv1InterfaceRenderer($builder),
                    new Msv1TraitRenderer($builder),
                    new Msv1EnumRenderer($builder),
                ],
                new Msv1FileRenderer($builder),
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
        if ($output === ArgumentParser::OUTPUT_MSV1) {
            return 'msv1';
        }

        return 'md';
    }
}
