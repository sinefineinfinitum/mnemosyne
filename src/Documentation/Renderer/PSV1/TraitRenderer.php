<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Documentation\Renderer\PSV1;

use SineFine\Mnemosyne\Documentation\Linker\CrossReference;
use SineFine\Mnemosyne\Documentation\Renderer\EntityRendererInterface;

final class TraitRenderer implements EntityRendererInterface
{
    public function __construct(
        private Psv1Builder $builder,
    ) {
    }

    public function supports(array $entity): bool
    {
        return $entity['type'] === 'trait';
    }

    /**
     * @param array<string, mixed> $entity
     * @param CrossReference       $crossRefs
     */
    public function renderEntity(array $entity, CrossReference $crossRefs): string
    {
        $psv1 = $this->builder->header($entity['type'], [], $entity['fqn']);

        foreach ($entity['traits'] ?? [] as $trait) {
            $psv1 .= $this->builder->traitUse($trait);
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
