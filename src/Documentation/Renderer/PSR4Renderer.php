<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer;

final class PSR4Renderer extends BaseRenderer
{
    /**
     * @param array<string, mixed> $entity
     */
    public function renderEntity(array $entity, string $sourceHash): string
    {

        $md = $this->buildFrontmatter(
            [
            'psr4' => 'true',
            'role' => 'entity',
            'source_hash' => $sourceHash,
            ]
        );

        $md .= "\n";
        $md .= $this->buildHeader(1, '`' . $entity['fqn'] . '`');
        $md .= "\n";

        $md .= $this->buildHeader(3, 'Type and modifiers');
        $md .= $this->typeAndModifiers($entity);
        $md .= "\n";

        if (!empty($entity['methods'])) {
            $md .= $this->buildHeader(3, 'API — public method signatures');
            $md .= $this->methodsTable($entity['methods']);
            $md .= "\n";
        }

        if (!empty($entity['dependencies'])) {
            $md .= $this->buildHeader(3, 'External Dependencies');
            $md .= $this->dependenciesList($entity['dependencies']);
            $md .= "\n";
        }

        return $md;
    }

    /**
     * @param array<string, mixed> $entity
     */
    private function typeAndModifiers(array $entity): string
    {
        $type = match ($entity['type']) {
            'class' => 'final class',
            default => $entity['type'],
        };

        $modifiers = !empty($entity['modifiers']) ? implode(' ', $entity['modifiers']) : 'none';
        $parent = $entity['parentClass'] !== null ? '`' . $entity['parentClass'] . '`' : 'none';
        $interfaces = !empty($entity['interfaces']) ? implode(', ', array_map(fn($i) => '`' . $i . '`', $entity['interfaces'])) : 'none';

        return
            $this->buildListItem('**Type:** `' . $type . '`') .
            $this->buildListItem('**Modifiers:** `' . $modifiers . '`') .
            $this->buildListItem('**Parent:** ' . $parent) .
            $this->buildListItem('**Interfaces:** ' . $interfaces);
    }

    /**
     * @param array<int, array<string, mixed>> $methods
     */
    private function methodsTable(array $methods): string
    {
        $headers = ['Method', 'Parameters', 'Returns', 'Exceptions'];
        $rows = [];

        foreach ($methods as $method) {
            $sig = $this->buildSignature($method);
            $params = $this->buildParameterString($method['parameters']);
            $returnType = $method['returnType'] ?? 'void';
            $rows[] = ['`' . $sig . '`', $params, '`' . $returnType . '`', '—'];
        }

        return $this->buildTable($headers, $rows);
    }

    /**
     * @param array<string, mixed> $method
     */
    private function buildSignature(array $method): string
    {
        $sig = $method['name'];
        return $sig;
    }

    /**
     * @param array<int, array<string, mixed>> $params
     */
    private function buildParameterString(array $params): string
    {
        $parts = [];
        foreach ($params as $param) {
            $p = FileRenderer::getParameterString($param);
            $parts[] = $p;
        }
        return implode(', ', $parts);
    }

    /**
     * @param string[] $deps
     */
    private function dependenciesList(array $deps): string
    {
        $result = '';
        foreach ($deps as $dep) {
            $result .= $this->buildListItem('`' . $dep . '`');
        }
        return $result;
    }
}
