<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer;

use SineFine\Ponymator\Documentation\Generator\CrossReference;
use SineFine\Ponymator\Comparator\HashGenerator;

final class ClassRenderer implements EntityRendererInterface
{
    public function __construct(
        private MarkdownBuilder $builder,
    ) {
    }

    public function supports(array $entity): bool
    {
        return $entity['type'] === 'class';
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
            'type' => 'class',
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

        $typeLabel = trim(implode(' ', $entity['modifiers']) . ' class');

        $parentLink = $entity['parentClass'] !== null
            ? $linkResolver($entity['parentClass'])
            : null;

        $interfaceLinks = [];
        foreach ($entity['interfaces'] as $interface) {
            $interfaceLinks[] = $linkResolver($interface);
        }

        $md .= $this->builder->declarationLine(
            $typeLabel,
            $entity['parentClass'],
            $parentLink,
            $entity['interfaces'],
            $interfaceLinks,
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
