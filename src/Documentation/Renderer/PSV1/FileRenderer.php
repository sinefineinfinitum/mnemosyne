<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer\PSV1;

use SineFine\Ponymator\Documentation\Renderer\FileRendererInterface;

final class FileRenderer implements FileRendererInterface
{
    public function __construct(
        private Psv1Builder $builder,
    ) {
    }

    /**
     * @param  string                                                     $relativePath
     * @param  array<int, array<string, mixed>>                           $functions
     * @param  string[]                                                   $globals
     * @param  array<int, array<string, mixed>>                           $constants
     * @param  array<string, list<\SineFine\Ponymator\Analyzer\CallInfo>> $fileCalls    functionName => list<CallInfo>
     * @return string
     */
    public function renderFile(
        string $relativePath,
        array $functions,
        array $globals,
        array $constants,
        array $fileCalls = []
    ): string {
        $psv1 = $this->builder->header('file', [], $relativePath);

        foreach ($functions as $function) {
            $psv1 .= $this->builder->function_($function);

            foreach ($function['parameters'] as $parameter) {
                $psv1 .= $this->builder->parameter($parameter);
            }

            $psv1 .= $this->builder->returnType($function['returnType'] ?? null);

            $functionCalls = $fileCalls[$function['name']] ?? [];
            foreach ($functionCalls as $call) {
                $psv1 .= $this->builder->callGraphEntry($call->toArray());
            }
        }

        foreach ($constants as $constant) {
            $psv1 .= $this->builder->fileConstant(
                $constant['name'],
                $constant['type'] ?? null,
                $constant['value'] ?? null
            );
        }

        foreach ($globals as $global) {
            $psv1 .= $this->builder->globalVariable($global);
        }

        return $psv1;
    }
}
