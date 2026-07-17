<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Detector\Pattern\Model;

use SineFine\Mnemosyne\Detector\Pattern\Catalog\PatternInterface;

final class PatternMatch
{
    /**
     * @param PatternInterface     $pattern
     * @param PatternParticipant[] $participants
     */
    public function __construct(
        public PatternInterface $pattern,
        public array            $participants,
    ) {
    }

}
