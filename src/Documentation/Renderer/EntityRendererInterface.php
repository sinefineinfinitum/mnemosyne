<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Documentation\Renderer;

use SineFine\Mnemosyne\Documentation\Linker\CrossReference;

interface EntityRendererInterface
{
    /**
     * @param  array<string, mixed> $entity
     * @return bool
     */
    public function supports(array $entity): bool;

    /**
     * @param  array<string, mixed> $entity
     * @param  CrossReference       $crossRefs
     * @return string
     */
    public function renderEntity(array $entity, CrossReference $crossRefs): string;
}
