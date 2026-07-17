<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Documentation\Renderer\MSV1;

use SineFine\Mnemosyne\Documentation\Linker\CrossReference;
use SineFine\Mnemosyne\Documentation\Renderer\EntityRendererInterface;

final class EnumRenderer implements EntityRendererInterface
{
    public function __construct(
        private Msv1Builder $builder,
    ) {
    }

    public function supports(array $entity): bool
    {
        return $entity['type'] === 'enum';
    }

    /**
     * @param array<string, mixed> $entity
     * @param CrossReference       $crossRefs
     */
    public function renderEntity(array $entity, CrossReference $crossRefs): string
    {
        $msv1 = $this->builder->header($entity['type'], [], $entity['fqn']);

        foreach ($entity['cases'] as $case) {
            $msv1 .= $this->builder->enumCase($case, $entity['scalarType']);
        }

        foreach ($entity['interfaces'] as $interface) {
            $msv1 .= $this->builder->implements($interface);
        }

        foreach ($entity['constants'] as $constant) {
            $msv1 .= $this->builder->constant(
                $constant['name'],
                $constant['visibility'] ?? 'public',
                $constant['type'] ?? null,
                $constant['value'] ?? null
            );
        }

        foreach ($entity['properties'] as $property) {
            $msv1 .= $this->builder->property($property);
        }

        foreach ($entity['methods'] as $method) {
            $msv1 .= $this->builder->method($method);

            foreach ($method['parameters'] as $parameter) {
                $msv1 .= $this->builder->parameter($parameter);
            }

            $msv1 .= $this->builder->returnType($method['returnType'] ?? null);

            $methodCreates = $crossRefs->getCreates()[$method['name']] ?? [];
            foreach ($methodCreates as $createdType) {
                $msv1 .= $this->builder->creates($createdType);
            }

            $methodCalls = $crossRefs->getCalls()[$method['name']] ?? [];
            foreach ($methodCalls as $call) {
                $msv1 .= $this->builder->callGraphEntry($call->toArray());
            }
        }

        return $msv1;
    }
}
