<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer\PSV1;

use SineFine\Ponymator\Documentation\Linker\CrossReference;
use SineFine\Ponymator\Documentation\Renderer\EntityRendererInterface;

final class EnumRenderer implements EntityRendererInterface
{
    public function __construct(
        private Psv1Builder $builder,
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
        $psv1 = $this->builder->header($entity['type'], [], $entity['fqn']);

        foreach ($entity['cases'] as $case) {
            $psv1 .= $this->builder->enumCase($case, $entity['scalarType']);
        }

        foreach ($entity['interfaces'] as $interface) {
            $psv1 .= $this->builder->implements($interface);
        }

        foreach ($entity['constants'] as $constant) {
            $psv1 .= $this->builder->constant(
                $constant['name'],
                $constant['visibility'] ?? 'public',
                $constant['type'] ?? null,
                $constant['value'] ?? null
            );
        }

        foreach ($entity['properties'] as $property) {
            $psv1 .= $this->builder->property($property);
        }

        foreach ($entity['methods'] as $method) {
            $psv1 .= $this->builder->method($method);

            foreach ($method['parameters'] as $parameter) {
                $psv1 .= $this->builder->parameter($parameter);
            }

            $psv1 .= $this->builder->returnType($method['returnType'] ?? null);

            $methodCreates = $crossRefs->getCreates()[$method['name']] ?? [];
            foreach ($methodCreates as $createdType) {
                $psv1 .= $this->builder->creates($createdType);
            }

            $methodCalls = $crossRefs->getCalls()[$method['name']] ?? [];
            foreach ($methodCalls as $call) {
                $psv1 .= $this->builder->callGraphEntry($call->toArray());
            }
        }

        return $psv1;
    }
}
