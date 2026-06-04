<?php declare(strict_types=1);

namespace SineFine\Ponymator\Analyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Param;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\Interface_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\TraitUse;
use PhpParser\Node\UnionType;
use PhpParser\NodeVisitorAbstract;

final class CrossReferenceScannerVisitor extends NodeVisitorAbstract
{
    private const BUILTIN_TYPES = [
        'string', 'int', 'float', 'bool', 'array', 'void',
        'null', 'object', 'mixed', 'never', 'true', 'false',
        'self', 'parent',
    ];

    /**
     * @var list<array{string, string}>
     */
    private array $pairs = [];

    /**
     * @var list<string>
     */
    private array $entityFqns = [];

    private ?string $currentEntity = null;

    public function enterNode(Node $node)
    {
        if ($node instanceof ClassLike && $node->namespacedName !== null) {
            if ($node instanceof Class_ && $node->isAnonymous()) {
                return null;
            }
            $this->currentEntity = $node->namespacedName->toString();
            $this->entityFqns[] = $this->currentEntity;
        }

        if ($this->currentEntity === null) {
            return null;
        }

        $currentEntity = $this->currentEntity;

        switch (true) {
        case $node instanceof Class_:
            if ($node->extends !== null) {
                $this->addReference($node->extends, $currentEntity);
            }
            foreach ($node->implements as $interface) {
                $this->addReference($interface, $currentEntity);
            }
            break;

        case $node instanceof Enum_:
            foreach ($node->implements as $interface) {
                $this->addReference($interface, $currentEntity);
            }
            break;

        case $node instanceof Interface_:
            foreach ($node->extends as $parent) {
                $this->addReference($parent, $currentEntity);
            }
            break;

        case $node instanceof TraitUse:
            foreach ($node->traits as $trait) {
                $this->addReference($trait, $currentEntity);
            }
            break;

        case $node instanceof Param && $node->type !== null:
        case $node instanceof Property && $node->type !== null:
            $this->processTypeNode($node->type, $currentEntity);
            break;

        case $node instanceof FunctionLike:
            $returnType = $node->getReturnType();
            if ($returnType !== null) {
                $this->processTypeNode($returnType, $currentEntity);
            }
            break;
        }

        return null;
    }

    /**
     * @return list<array{string, string}>
     */
    public function getPairs(): array
    {
        return $this->pairs;
    }

    /**
     * @return list<string>
     */
    public function getEntityFqns(): array
    {
        return $this->entityFqns;
    }
    private function addReference(Name $name, string $referencingFqn): void
    {
        $this->pairs[] = [$name->toString(), $referencingFqn];
    }

    private function processTypeNode(Node $typeNode, string $referencingFqn): void
    {
        switch (true) {
        case $typeNode instanceof Name:
            $fqn = $typeNode->toString();
            if (!in_array(strtolower($fqn), self::BUILTIN_TYPES, true)) {
                $this->pairs[] = [$fqn, $referencingFqn];
            }
            break;

        case $typeNode instanceof UnionType:
        case $typeNode instanceof IntersectionType:
            foreach ($typeNode->types as $innerType) {
                $this->processTypeNode($innerType, $referencingFqn);
            }
            break;

        case $typeNode instanceof NullableType:
            $this->processTypeNode($typeNode->type, $referencingFqn);
            break;
        }
    }
}
