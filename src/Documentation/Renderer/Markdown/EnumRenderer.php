<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer\Markdown;

use SineFine\Ponymator\Comparator\HashGenerator;
use SineFine\Ponymator\Documentation\Linker\CrossReference;
use SineFine\Ponymator\Documentation\Renderer\EntityRendererInterface;

final class EnumRenderer implements EntityRendererInterface
{
    public function __construct(
        private MarkdownBuilder $builder,
    ) {
    }

    public function supports(array $entity): bool
    {
        return $entity['type'] === 'enum';
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
            'type' => 'enum',
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

        $typeLabel = $entity['scalarType'] !== null ? 'backed enum' : 'enum';

        $interfaceLinks = [];
        foreach ($entity['interfaces'] as $interface) {
            $interfaceLinks[] = $linkResolver($interface);
        }

        $md .= $this->builder->declarationLine(
            $typeLabel,
            null,
            null,
            $entity['interfaces'],
            $interfaceLinks,
            $entity['scalarType'],
        );
        $md .= "\n";

        if (!empty($entity['cases'])) {
            $md .= $this->builder->section('Cases', 3, $this->casesTable($entity['cases']));
        }

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

    /**
     * @param array<int, array<string, mixed>> $cases
     */
    private function casesTable(array $cases): string
    {
        $headers = ['Case', 'Value'];
        $rows = [];
        foreach ($cases as $case) {
            $rows[] = [
                '`' . $case['name'] . '`',
                $case['value'] !== null ? '`' . $case['value'] . '`' : '—',
            ];
        }
        return $this->builder->table($headers, $rows);
    }
}
