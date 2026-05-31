<?php declare(strict_types=1);

namespace SineFine\Ponymator\Documentation\Renderer;

interface EntityRendererInterface
{
    /**
     * @param  array<string, mixed> $entity
     * @return bool
     */
    public function supports(array $entity): bool;

    /**
     * @param  array<string, mixed> $entity
     * @param  array<string, mixed> $crossRefs
     * @return string
     */
    public function renderEntity(array $entity, array $crossRefs): string;
}
