<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Msv1Parser\Ast;

class Document
{
    /** @var EntityNode[] */
    public array $entities = [];

    public string $parserVersion = '1.0';

    public ?string $sourcePath = null;

    public ?string $sourceHash = null;
}
