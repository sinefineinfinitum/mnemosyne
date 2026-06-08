<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer\PSV1;

final class Psv1Builder
{
    /**
     * @param  string   $type
     * @param  string[] $keywords
     * @param  string   $fqn
     * @return string
     */
    public function header(string $type, array $keywords, string $fqn): string
    {
        $parts = ['@' . $type];

        foreach ($keywords as $keyword) {
            $parts[] = $keyword;
        }

        $parts[] = $fqn;

        return implode(' ', $parts) . PHP_EOL;
    }

    public function extends(string $type): string
    {
        return '>' . $type . PHP_EOL;
    }

    public function implements(string $type): string
    {
        return '<' . $type . PHP_EOL;
    }

    public function traitUse(string $type): string
    {
        return '%' . $type . PHP_EOL;
    }

    public function constant(string $name, string $visibility, ?string $type, ?string $value): string
    {
        $line = '!' . $this->modifier($visibility) . $name;

        if ($type !== null) {
            $line .= ':' . $this->type($type);
        }

        if ($value !== null) {
            $line .= '=' . $this->escapeValue($value);
        }

        return $line . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $property
     */
    public function property(array $property): string
    {
        $line = '$' . $this->modifier($property['visibility']);

        $keywords = [];
        if (!empty($property['isStatic'])) {
            $keywords[] = 'static';
        }
        if (!empty($property['isReadonly'])) {
            $keywords[] = 'readonly';
        }

        if (!empty($keywords)) {
            $line .= implode(' ', $keywords) . ' ';
        }

        $line .= $property['name'];

        if ($property['type'] !== null) {
            $line .= ':' . $this->type($property['type']);
        }

        if ($property['defaultValue'] !== null) {
            $line .= '=' . $this->escapeValue($property['defaultValue']);
        }

        return $line . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $method
     */
    public function method(array $method): string
    {
        $line = '.' . $this->modifier($method['visibility']) . $method['name'];

        $keywords = [];
        if (!empty($method['isAbstract'])) {
            $keywords[] = 'abstract';
        }
        if (!empty($method['isFinal'])) {
            $keywords[] = 'final';
        }
        if (!empty($method['isStatic'])) {
            $keywords[] = 'static';
        }

        if (!empty($keywords)) {
            $line .= ' ' . implode(' ', $keywords);
        }

        return $line . PHP_EOL;
    }

    /**
     * Parameter line indented 4 spaces under its method/function.
     *
     * PSV1 rule: one level of indentation (4 spaces) for children of a `.` block.
     *
     * @param array<string, mixed> $parameter
     */
    public function parameter(array $parameter): string
    {
        $line = '    ';

        if (!empty($parameter['isPassedByReference'])) {
            $line .= '&';
        }

        if (!empty($parameter['isVariadic'])) {
            $line .= '...';
        }

        $line .= '$' . $parameter['name'];

        if ($parameter['type'] !== null) {
            $line .= ':' . $this->type($parameter['type']);
        }

        if ($parameter['defaultValue'] !== null) {
            $line .= '=' . $this->escapeValue($parameter['defaultValue']);
        }

        return $line . PHP_EOL;
    }

    /**
     * Return type line indented 4 spaces under its method.
     */
    public function returnType(?string $type): string
    {
        if ($type === null) {
            return '';
        }

        return '    :' . $this->type($type) . PHP_EOL;
    }

    /**
     * Creates line indented 4 spaces under its method.
     */
    public function creates(string $type): string
    {
        return '    ^' . $type . PHP_EOL;
    }

    /**
     * Call Graph entry indented 4 spaces under its method. PSV1 v1.1 format:
     * single-line entries with marker + FQCN + separator + method.
     *
     * Format: `MARKER FQCN SEPARATOR TARGET`
     * - `*` for strong (resolved, single FQCN)
     * - `?` for weak (multiple candidates, unresolved, or late-static)
     * - `::` for static calls, `->` for dynamic calls
     * - No separator for global function calls
     *
     * @param array{kind: string, targetName: string, resolvedTargetFqcn: ?string, association: string, candidateTypes: list<string>} $call
     */
    public function callGraphEntry(array $call): string
    {
        if ($call['kind'] === 'create') {
            return '';
        }

        $marker = $call['association'] === '*' ? '*' : '?';
        $out = '';

        if ($call['resolvedTargetFqcn'] !== null) {
            $out .= '    ' . $marker . $call['resolvedTargetFqcn'] . PHP_EOL;
        } else {
            // For weak calls without resolvedTargetFqcn
            if (!empty($call['candidateTypes'])) {
                // Get type from kind
                $separator = $call['kind'] === 'dynamic' ? '->' : '::';
                $method = $call['targetName'];

                foreach ($call['candidateTypes'] as $candidate) {
                    $out .= '    ?' . $candidate . $separator . $method . PHP_EOL;
                }
            }
        }

        return $out;
    }

    /**
     * @param array<string, mixed> $function
     */
    public function function_(array $function): string
    {
        $line = '.' . $function['name'];

        $keywords = [];
        if (!empty($function['isStatic'])) {
            $keywords[] = 'static';
        }

        if (!empty($keywords)) {
            $line .= ' ' . implode(' ', $keywords);
        }

        return $line . PHP_EOL;
    }

    public function fileConstant(string $name, ?string $type, ?string $value): string
    {
        $line = '!' . $name;

        if ($type !== null) {
            $line .= ':' . $this->type($type);
        }

        if ($value !== null) {
            $line .= '=' . $this->escapeValue($value);
        }

        return $line . PHP_EOL;
    }

    public function globalVariable(string $name): string
    {
        return '$' . $name . PHP_EOL;
    }

    /**
     * @param array<string, mixed> $case
     */
    public function enumCase(array $case, ?string $scalarType): string
    {
        $line = '~' . $case['name'];

        if ($scalarType !== null && isset($case['value'])) {
            $line .= '=' . $this->escapeValue($case['value']);
        }

        return $line . PHP_EOL;
    }

    private function type(string $type): string
    {
        if (str_starts_with($type, '?')) {
            return substr($type, 1) . '|null';
        }

        return $type;
    }

    private function modifier(string $modifier): string
    {
        if ($modifier === 'private') {
            return '-';
        }

        if ($modifier === 'protected') {
            return '#';
        }

        return '+';
    }

    private function escapeValue(string $value): string
    {
        return str_replace(["\r\n", "\r", "\n"], '\n', $value);
    }
}
