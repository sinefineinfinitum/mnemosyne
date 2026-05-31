<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer;

final class TraitRenderer implements EntityRendererInterface
{
    public function __construct(
        private MarkdownBuilder $builder,
    ) {
    }

    public function supports(array $entity): bool
    {
        return $entity['type'] === 'trait';
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<string, mixed> $crossRefs
     */
    public function renderEntity(array $entity, array $crossRefs): string
    {
        $content = $this->buildContent($entity, $crossRefs);
        $hash = hash('sha256', $content);
        $md = $this->builder->frontmatter(
            [
            'type' => 'trait',
            'hash' => $hash,
            'source_hash' => (string) ($crossRefs['_sourceHash'] ?? ''),
            ]
        );
        $md .= $content;
        return $md;
    }

    /**
     * @param array<string, mixed> $entity
     * @param array<string, mixed> $crossRefs
     */
    private function buildContent(array $entity, array $crossRefs): string
    {
        $md = "\n";
        $md .= $this->builder->header(1, '`' . $entity['fqn'] . '`');
        $md .= "\n";

        $md .= $this->builder->header(3, 'Head');
        $md .= $this->builder->kvList(
            [
            'Type' => '`trait`',
            ]
        );
        $md .= "\n";

        if (!empty($entity['constants'])) {
            $md .= $this->builder->section('Constants', 3, $this->builder->constantsTable($entity['constants']));
        }

        if (!empty($entity['properties'])) {
            $md .= $this->builder->section('Properties', 3, $this->builder->propertiesTable($entity['properties']));
        }

        if (!empty($entity['methods'])) {
            $md .= $this->builder->section('Methods', 3, $this->builder->methodsList($entity['methods']));
        }

        $usageMap = is_array($crossRefs['trait_usage'] ?? null) ? $crossRefs['trait_usage'] : [];
        $usingClasses = is_array($usageMap[$entity['fqn']] ?? null) ? $usageMap[$entity['fqn']] : [];
        if (!empty($usingClasses)) {
            $md .= $this->builder->section('Used by', 3, $this->builder->classList($usingClasses));
        }

        if (!empty($entity['dependencies'])) {
            $md .= $this->builder->section('Dependencies', 3, $this->builder->dependenciesList($entity['dependencies']));
        }

        return $md;
    }
}
