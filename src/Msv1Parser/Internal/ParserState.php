<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Msv1Parser\Internal;

use SineFine\Mnemosyne\Msv1Parser\Ast\EntityNode;
use SineFine\Mnemosyne\Msv1Parser\Ast\MemberNode;

/**
 * @internal Carries the parser's "what is currently open" context across
 *           iterations of the top-level parse loop. All mutations go through
 *           the transition methods, which enforce the entity/method invariants
 *           (opening a new entity closes any active method; opening a method
 *           requires an active entity).
 */
final class ParserState
{
    private ?EntityNode $entity = null;
    private ?MemberNode $method = null;

    /**
     * Opens a new entity context. Implicitly closes any active method —
     * a method's children are scoped to a single entity.
     */
    public function openEntity(EntityNode $entity): void
    {
        $this->entity = $entity;
        $this->method = null;
    }

    /**
     * Opens a method (or function) context. Requires an active entity.
     */
    public function openMethod(MemberNode $method): void
    {
        if ($this->entity === null) {
            throw new \LogicException('Cannot open method without an active entity');
        }
        $this->method = $method;
    }

    public function closeMethod(): void
    {
        $this->method = null;
    }

    public function entity(): ?EntityNode
    {
        return $this->entity;
    }

    public function method(): ?MemberNode
    {
        return $this->method;
    }
}
