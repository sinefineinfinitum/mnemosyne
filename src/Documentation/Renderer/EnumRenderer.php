<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer;

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
     * @param array<string, mixed> $crossRefs
     */
    public function renderEntity(array $entity, array $crossRefs): string
    {
        $content = $this->buildContent($entity);
        $hash = hash('sha256', $content);
        $md = $this->builder->frontmatter(
            [
            'type' => 'enum',
            'hash' => $hash,
            'source_hash' => (string) ($crossRefs['_sourceHash'] ?? ''),
            ]
        );
        $md .= $content;
        return $md;
    }

    /**
     * @param array<string, mixed> $entity
     */
    private function buildContent(array $entity): string
    {
        $md = "\n";
        $md .= $this->builder->header(1, '`' . $entity['fqn'] . '`');
        $md .= "\n";

        $md .= $this->builder->header(3, 'Head');
        $md .= $this->builder->kvList(['Type' => '`enum`',]);
        $md .= "\n";

        if ($entity['scalarType'] !== null) {
            $md .= $this->builder->kvList(['Backing type' => '`' . $entity['scalarType'] . '`']);
            $md .= "\n";
        }

        if (!empty($entity['cases'])) {
            $md .= $this->builder->section('Cases', 3, $this->casesTable($entity['cases']));
        }

        if (!empty($entity['constants'])) {
            $md .= $this->builder->section('Constants', 3, $this->builder->constantsTable($entity['constants']));
        }

        if (!empty($entity['properties'])) {
            $md .= $this->builder->section('Properties', 3, $this->builder->propertiesTable($entity['properties']));
        }

        if (!empty($entity['methods'])) {
            $md .= $this->builder->section('Methods', 3, $this->builder->methodsList($entity['methods']));
        }

        if (!empty($entity['dependencies'])) {
            $md .= $this->builder->section('Dependencies', 3, $this->builder->dependenciesList($entity['dependencies']));
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
