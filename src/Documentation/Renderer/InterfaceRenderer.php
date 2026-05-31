<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer;

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
     * @param array<string, mixed> $crossRefs
     */
    public function renderEntity(array $entity, array $crossRefs): string
    {
        $content = $this->buildContent($entity, $crossRefs);
        $hash = hash('sha256', $content);
        $md = $this->builder->frontmatter(
            [
            'type' => 'interface',
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
            'Type' => '`interface`',
            ]
        );
        $md .= "\n";

        if (!empty($entity['interfaces'])) {
            $md .= $this->builder->section('Extended', 3, $this->builder->itemList($entity['interfaces']));
        }

        if (!empty($entity['constants'])) {
            $md .= $this->builder->section('Constants', 3, $this->builder->constantsTable($entity['constants']));
        }

        if (!empty($entity['methods'])) {
            $md .= $this->builder->section('Methods', 3, $this->builder->methodsList($entity['methods']));
        }

        $implMap = is_array($crossRefs['implements'] ?? null) ? $crossRefs['implements'] : [];
        $implementations = is_array($implMap[$entity['fqn']] ?? null) ? $implMap[$entity['fqn']] : [];
        if (!empty($implementations)) {
            $md .= $this->builder->section('Implementations', 3, $this->builder->classList($implementations));
        }

        if (!empty($entity['dependencies'])) {
            $md .= $this->builder->section('Dependencies', 3, $this->builder->dependenciesList($entity['dependencies']));
        }

        return $md;
    }
}
