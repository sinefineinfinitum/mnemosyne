<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Msv1Parser\Contracts;

use SineFine\Mnemosyne\Msv1Parser\Ast\Document;

interface ParserInterface
{
    public function parse(string $content): Document;
}
