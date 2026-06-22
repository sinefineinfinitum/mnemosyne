<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Model;

final class PatternParticipant
{
    public function __construct(
        public string $role,
        public int $entityId,
    ) {
    }
}
