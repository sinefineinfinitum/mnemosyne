<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Documentation\Renderer\PSV1;

use SineFine\Mnemosyne\Documentation\Linker\CrossReference;
use SineFine\Mnemosyne\Documentation\Renderer\EntityRendererInterface;

final class InterfaceRenderer implements EntityRendererInterface
{
    public function __construct(
        private Psv1Builder $builder,
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
        $psv1 = $this->builder->header($entity['type'], [], $entity['fqn']);

        foreach ($entity['interfaces'] as $interface) {
            $psv1 .= $this->builder->extends($interface);
        }

        foreach ($entity['constants'] as $constant) {
            $psv1 .= $this->builder->constant(
                $constant['name'],
                $constant['visibility'] ?? 'public',
                $constant['type'] ?? null,
                $constant['value'] ?? null
            );
        }

        foreach ($entity['methods'] as $method) {
            $psv1 .= $this->builder->method($method);

            foreach ($method['parameters'] as $parameter) {
                $psv1 .= $this->builder->parameter($parameter);
            }

            $psv1 .= $this->builder->returnType($method['returnType'] ?? null);
        }

        return $psv1;
    }
}
