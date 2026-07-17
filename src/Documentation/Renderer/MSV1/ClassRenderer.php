<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Documentation\Renderer\MSV1;

use SineFine\Mnemosyne\Documentation\Linker\CrossReference;
use SineFine\Mnemosyne\Documentation\Renderer\EntityRendererInterface;

final class ClassRenderer implements EntityRendererInterface
{
    public function __construct(
        private Msv1Builder $builder,
    ) {
    }

    public function supports(array $entity): bool
    {
        return $entity['type'] === 'class';
    }

    /**
     * @param array<string, mixed> $entity
     * @param CrossReference       $crossRefs
     */
    public function renderEntity(array $entity, CrossReference $crossRefs): string
    {
        return $this->buildContent($entity, $crossRefs);
    }

    /**
     * @param array<string, mixed> $entity
     * @param CrossReference       $crossRefs
     */
    private function buildContent(array $entity, CrossReference $crossRefs): string
    {
        $msv1 = $this->builder->header($entity['type'], $entity['modifiers'], $entity['fqn']);

        if ($entity['parentClass'] !== null) {
            $msv1 .= $this->builder->extends($entity['parentClass']);
        }

        foreach ($entity['interfaces'] as $interface) {
            $msv1 .= $this->builder->implements($interface);
        }

        foreach ($entity['traits'] as $trait) {
            $msv1 .= $this->builder->traitUse($trait);
        }

        $msv1 .= $this->renderMembers($entity, $crossRefs);

        return $msv1;
    }

    /**
     * @param array<string, mixed> $entity
     */
    private function renderMembers(array $entity, CrossReference $crossRefs): string
    {
        $msv1 = '';

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
