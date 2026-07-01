<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\Property;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\Node\UnionType;

/**
 * @internal
 */
final class CallResolutionContext
{
    private const BUILTIN_TYPES = [
        'string', 'int', 'float', 'bool', 'array', 'void',
        'null', 'object', 'mixed', 'never', 'true', 'false',
        'self', 'parent', 'static', 'callable', 'iterable',
    ];

    private ?string $currentClass = null;
    private ?string $currentMethod = null;
    private ?string $currentFunction = null;
    private ?string $currentClassParent = null;
    private bool $currentClassIsFinal = false;

    /**
     * @var array<string, list<string>> map of variable name to candidate FQCNs
     */
    private array $variableTypes = [];

    public function getCurrentClass(): ?string
    {
        return $this->currentClass;
    }

    public function getCurrentMethod(): ?string
    {
        return $this->currentMethod;
    }

    public function getCurrentFunction(): ?string
    {
        return $this->currentFunction;
    }

    public function getCurrentClassParent(): ?string
    {
        return $this->currentClassParent;
    }

    public function isCurrentClassFinal(): bool
    {
        return $this->currentClassIsFinal;
    }

    /**
     * @return array<string, list<string>>
     */
    public function getVariableTypes(): array
    {
        return $this->variableTypes;
    }

    public function enterClass(Class_|Trait_ $node): void
    {
        if ($node instanceof Class_ && $node->isAnonymous()) {
            return;
        }
        if ($node->namespacedName === null) {
            return;
        }
        $this->currentClass = $node->namespacedName->toString();
        $this->currentMethod = null;
        $this->currentFunction = null;
        $this->variableTypes = $this->buildPropertyTypeMap($node);

        $this->currentClassParent = $node instanceof Class_ ? $node->extends?->toString() : null;
        $this->currentClassIsFinal = $node instanceof Class_ && $node->isFinal();
    }

    public function leaveClass(): void
    {
        $this->currentClass = null;
        $this->currentClassParent = null;
        $this->currentClassIsFinal = false;
        $this->variableTypes = [];
    }

    public function enterMethod(string $name, FunctionLike $node): void
    {
        $this->currentMethod = $name;
        $this->currentFunction = null;
        $this->variableTypes = array_merge(
            $this->variableTypes,
            $this->buildParameterTypeMap($node)
        );
    }

    public function leaveMethod(): void
    {
        $this->currentMethod = null;
    }

    public function enterFunction(string $name, FunctionLike $node): void
    {
        $this->currentFunction = $name;
        $this->currentClass = null;
        $this->currentMethod = null;
        $this->currentClassParent = null;
        $this->currentClassIsFinal = false;
        $this->variableTypes = $this->buildParameterTypeMap($node);
    }

    public function leaveFunction(): void
    {
        $this->currentFunction = null;
    }

    public function addAssignment(Assign $assign): void
    {
        if (!$assign->var instanceof Variable) {
            return;
        }
        $varName = $assign->var->name;
        if (!is_string($varName)) {
            return;
        }
        $name = '$' . $varName;
        $right = $assign->expr;

        if ($right instanceof New_ && $right->class instanceof Name) {
            $this->variableTypes[$name] = [$right->class->toString()];
            return;
        }

        if ($right instanceof Variable) {
            $rightVarName = $right->name;
            if (is_string($rightVarName) && isset($this->variableTypes['$' . $rightVarName])) {
                $this->variableTypes[$name] = $this->variableTypes['$' . $rightVarName];
            }
        }
    }

    public function isInScope(): bool
    {
        return $this->currentMethod !== null || $this->currentFunction !== null;
    }

    /**
     * @return array<string, list<string>>
     */
    private function buildPropertyTypeMap(Class_|Trait_ $node): array
    {
        $map = [];
        foreach ($node->stmts as $stmt) {
            if (!$stmt instanceof Property || $stmt->type === null) {
                continue;
            }
            $types = $this->extractTypeNames($stmt->type);
            foreach ($stmt->props as $prop) {
                $map['$' . $prop->name->toString()] = $types;
            }
        }
        return $map;
    }

    /**
     * @return array<string, list<string>>
     */
    private function buildParameterTypeMap(FunctionLike $method): array
    {
        $map = [];
        foreach ($method->getParams() as $param) {
            if ($param->type === null || !$param->var instanceof Variable) {
                continue;
            }
            $varName = $param->var->name;
            if (!is_string($varName)) {
                continue;
            }
            $map['$' . $varName] = $this->extractTypeNames($param->type);
        }
        return $map;
    }

    /**
     * @return list<string>
     */
    private function extractTypeNames(Node $typeNode): array
    {
        if ($typeNode instanceof Name || $typeNode instanceof Identifier) {
            $name = $typeNode->toString();
            return in_array(strtolower($name), self::BUILTIN_TYPES, true) ? [] : [$name];
        }
        if ($typeNode instanceof NullableType) {
            return $this->extractTypeNames($typeNode->type);
        }
        if ($typeNode instanceof UnionType || $typeNode instanceof IntersectionType) {
            $types = [];
            foreach ($typeNode->types as $inner) {
                $types = array_merge($types, $this->extractTypeNames($inner));
            }
            return array_values(array_unique($types));
        }
        return [];
    }
}
