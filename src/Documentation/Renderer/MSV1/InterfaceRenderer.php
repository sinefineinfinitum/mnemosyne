<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Documentation\Renderer\MSV1;

use SineFine\Mnemosyne\Documentation\Linker\CrossReference;
use SineFine\Mnemosyne\Documentation\Renderer\EntityRendererInterface;

final class InterfaceRenderer implements EntityRendererInterface
{
    public function __construct(
        private Msv1Builder $builder,
    ) {
    }

    public function supports(array $entity): bool
    {
        return $entity['type'] === 'interface';
    }

    /**
     * @param  array<string, mixed> $entity
     * @param  CrossReference       $crossRefs
     * @return string
     */
    public function renderEntity(array $entity, CrossReference $crossRefs): string
    {
        $msv1 = $this->builder->header($entity['type'], [], $entity['fqn']);

        foreach ($entity['interfaces'] as $interface) {
            $msv1 .= $this->builder->extends($interface);
        }

        foreach ($entity['constants'] as $constant) {
            $msv1 .= $this->builder->constant(
                $constant['name'],
                $constant['visibility'] ?? 'public',
                $constant['type'] ?? null,
                $constant['value'] ?? null
            );
        }

        foreach ($entity['methods'] as $method) {
            $msv1 .= $this->builder->method($method);

            foreach ($method['parameters'] as $parameter) {
                $msv1 .= $this->builder->parameter($parameter);
            }

            $msv1 .= $this->builder->returnType($method['returnType'] ?? null);
        }

        return $msv1;
    }
}
