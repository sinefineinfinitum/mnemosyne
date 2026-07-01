<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer;


final class CallInfo
{
    public const KIND_STATIC = 'static';
    public const KIND_DYNAMIC = 'dynamic';
    public const KIND_GLOBAL = 'global';
    public const KIND_CREATE = 'create';

    public const STRONG = '*';
    public const WEAK = '?';

    public const KINDS = [
        self::KIND_STATIC,
        self::KIND_DYNAMIC,
        self::KIND_GLOBAL,
        self::KIND_CREATE,
    ];

    public const ASSOCIATIONS = [self::STRONG, self::WEAK];

    /**
     * @param string       $kind
     * @param string       $targetName
     * @param list<string> $candidateTypes
     * @param string|null  $resolvedTargetFqcn
     * @param string       $association
     */
    public function __construct(
        public string $kind,
        public string $targetName,
        public array $candidateTypes = [],
        public ?string $resolvedTargetFqcn = null,
        public string $association = self::WEAK,
    ) {
        if (!in_array($kind, self::KINDS, true)) {
            throw new ParserException("Invalid call kind: $kind");
        }
        if (!in_array($association, self::ASSOCIATIONS, true)) {
            throw new ParserException("Invalid association: $association");
        }
        if ($targetName === '') {
            throw new ParserException('targetName must be non-empty');
        }
    }

    /**
     * @param  string            $association
     * @param  ?string           $resolvedTargetFqcn
     * @param  list<string>|null $candidateTypes
     * @return CallInfo
     */
    public function withAssociation(
        string $association,
        ?string $resolvedTargetFqcn = null,
        ?array $candidateTypes = null
    ): self {
        return new self(
            $this->kind,
            $this->targetName,
            $candidateTypes ?? $this->candidateTypes,
            $resolvedTargetFqcn ?? $this->resolvedTargetFqcn,
            $association,
        );
    }

    public function isResolved(): bool
    {
        return $this->association === self::STRONG && $this->resolvedTargetFqcn !== null;
    }

    /**
     * Deterministic sort key: (kind).
     */
    public function sortKey(): string
    {
        return $this->kind;
    }

    /**
     * @return array{
     *     kind: string,
     *     targetName: string,
     *     candidateTypes: list<string>,
     *     resolvedTargetFqcn: ?string,
     *     association: string
     * }
     */
    public function toArray(): array
    {
        return [
            'kind' => $this->kind,
            'targetName' => $this->targetName,
            'candidateTypes' => $this->candidateTypes,
            'resolvedTargetFqcn' => $this->resolvedTargetFqcn,
            'association' => $this->association,
        ];
    }
}
