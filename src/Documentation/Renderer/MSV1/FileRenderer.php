<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Documentation\Renderer\MSV1;

use SineFine\Mnemosyne\Documentation\Renderer\FileRendererInterface;

final class FileRenderer implements FileRendererInterface
{
    public function __construct(
        private Msv1Builder $builder,
    ) {
    }

    /**
     * @param  string                                                     $relativePath
     * @param  array<int, array<string, mixed>>                           $functions
     * @param  string[]                                                   $globals
     * @param  array<int, array<string, mixed>>                           $constants
     * @param  array<string, list<\SineFine\Mnemosyne\Analyzer\CallInfo>> $fileCalls    functionName => list<CallInfo>
     * @return string
     */
    public function renderFile(
        string $relativePath,
        array $functions,
        array $globals,
        array $constants,
        array $fileCalls = []
    ): string {
        $msv1 = $this->builder->header('file', [], $relativePath);

        foreach ($functions as $function) {
            $msv1 .= $this->builder->function_($function);

            foreach ($function['parameters'] as $parameter) {
                $msv1 .= $this->builder->parameter($parameter);
            }

            $msv1 .= $this->builder->returnType($function['returnType'] ?? null);

            $functionCalls = $fileCalls[$function['name']] ?? [];
            foreach ($functionCalls as $call) {
                $msv1 .= $this->builder->callGraphEntry($call->toArray());
            }
        }

        foreach ($constants as $constant) {
            $msv1 .= $this->builder->fileConstant(
                $constant['name'],
                $constant['type'] ?? null,
                $constant['value'] ?? null
            );
        }

        foreach ($globals as $global) {
            $msv1 .= $this->builder->globalVariable($global);
        }

        return $msv1;
    }
}
