<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer\Markdown;

use SineFine\Ponymator\Comparator\HashGenerator;
use SineFine\Ponymator\Documentation\Linker\CrossReference;
use SineFine\Ponymator\Documentation\Renderer\EntityRendererInterface;

final class InterfaceRenderer implements EntityRendererInterface
{
    public function __construct(
        private MarkdownBuilder $builder,
    ) {
    }

    public function supports(array $entity): bool
    {
        return $entity['type'] === 'interface';
    }

    /**
     * @param array<string, mixed> $entity
     * @param CrossReference       $crossRefs
     */
    public function renderEntity(array $entity, CrossReference $crossRefs): string
    {
        $content = $this->buildContent($entity, $crossRefs);
        $hash = HashGenerator::shortHash($content);
        $md = $this->builder->frontmatter(
            [
            'type' => 'interface',
            'hash' => $hash,
            ]
        );
        $md .= $content;
        return $md;
    }

    /**
     * @param array<string, mixed> $entity
     * @param CrossReference       $crossRefs
     */
    private function buildContent(array $entity, CrossReference $crossRefs): string
    {
        $linkResolver = $crossRefs->getTypeLinkResolver();

        $md = "\n";
        $md .= $this->builder->header(1, '`' . $entity['fqn'] . '`');
        $md .= "\n";

        $interfaceLinks = [];
        foreach ($entity['interfaces'] as $interface) {
            $interfaceLinks[] = $linkResolver($interface);
        }

        $md .= $this->builder->declarationLine(
            'interface',
            null,
            null,
            $entity['interfaces'],
            $interfaceLinks,
            null,
            'extends',
            'extends'
        );
        $md .= "\n";

        if (!empty($entity['constants'])) {
            $md .= $this->builder->section('Constants', 3, $this->builder->constantsTable($entity['constants']));
        }

        if (!empty($entity['properties'])) {
            $md .= $this->builder->section('Properties', 3, $this->builder->propertiesList($entity['properties'], $linkResolver));
        }

        if (!empty($entity['methods'])) {
            $md .= $this->builder->section('Methods', 3, $this->builder->methodsList($entity['methods'], $linkResolver));
        }

        if (!empty($crossRefs->getUsedByLinks())) {
            $md .= $this->builder->usedBySection($crossRefs->getUsedByLinks());
        }

        return $md;
    }
}
