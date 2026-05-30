<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer;

final class FileRenderer extends BaseRenderer
{
    /**
     * @param string                           $relativePath
     * @param array<int, array<string, mixed>> $functions
     * @param string[]                         $globals
     */
    public function renderFile(string $relativePath, array $functions, array $globals, string $sourceHash): string
    {

        $md = $this->buildFrontmatter(
            [
            'psr4' => 'false',
            'role' => 'file',
            'source_hash' => $sourceHash,
            ]
        );

        $md .= "\n";
        $md .= $this->buildHeader(1, '`' . $relativePath . '`');
        $md .= "\n";

        $md .= $this->buildHeader(3, 'Objective part');
        $md .= $this->buildListItem('**Type:** `file`');
        $md .= $this->buildListItem('**Modifiers:** `none`');
        $md .= $this->buildListItem('**Parent:** none');
        $md .= $this->buildListItem('**Interfaces:** none');
        $md .= "\n";

        if (!empty($functions)) {
            $md .= $this->buildHeader(3, 'Functions');
            $md .= $this->functionsList($functions);
            $md .= "\n";
        }

        if (!empty($globals)) {
            $md .= $this->buildHeader(3, 'Globals');
            $md .= $this->globalsList($globals);
            $md .= "\n";
        }

        return $md;
    }

    /**
     * @param array<int, array<string, mixed>> $functions
     */
    private function functionsList(array $functions): string
    {
        $result = '';
        foreach ($functions as $fn) {
            $params = [];
            foreach ($fn['parameters'] ?? [] as $param) {
                $params[] = self::getParameterString($param);
            }

            $sig = 'function ' . $fn['name'] . '(' . implode(', ', $params) . ')';
            if ($fn['returnType'] ?? null) {
                $sig .= ': ' . $fn['returnType'];
            }

            $result .= $this->buildHeader(4, $fn['name']);
            $result .= $this->buildCodeBlock($sig, 'php');
        }
        return $result;
    }

    /**
     * @param string[] $globals
     */
    private function globalsList(array $globals): string
    {
        $result = '';
        foreach ($globals as $global) {
            $result .= $this->buildListItem('`$' . $global . '`');
        }
        return $result;
    }

    /**
     * @param  mixed $param
     * @return string
     */
    public static function getParameterString(mixed $param): string
    {
        $p = '';
        if ($param['type'] !== null) {
            $p .= $param['type'] . ' ';
        }
        if ($param['isVariadic']) {
            $p .= '...';
        }
        if ($param['isPassedByReference']) {
            $p .= '&';
        }
        $p .= '$' . $param['name'];
        if ($param['defaultValue'] !== null) {
            $p .= ' = ' . $param['defaultValue'];
        }
        return $p;
    }
}
