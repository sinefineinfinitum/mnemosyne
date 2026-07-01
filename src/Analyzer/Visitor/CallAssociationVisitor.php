<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\NodeVisitorAbstract;
use SineFine\Mnemosyne\Analyzer\CallInfo;


final class CallAssociationVisitor extends NodeVisitorAbstract
{
    private CallResolutionContext $context;

    /**
     * @var array<string, array<string, list<CallInfo>>>
     */
    private array $calls = [];

    /**
     * @var array<string, list<CallInfo>>
     */
    private array $fileCalls = [];

    /**
     * @param string[] $projectFunctions list of project-defined function FQCNs
     */
    public function __construct(
        private array $projectFunctions = [],
    ) {
        $this->context = new CallResolutionContext();
    }

    /**
     * @param  array<int, Node>                             $ast
     * @param  array<string, array<string, list<CallInfo>>> $calls
     * @param  array<string, list<CallInfo>>                $fileCalls
     * @return array{calls: array<string, array<string, list<CallInfo>>>, fileCalls: array<string, list<CallInfo>>}
     */
    public function resolve(array $ast, array $calls, array $fileCalls = []): array
    {
        $this->calls = $calls;
        $this->fileCalls = $fileCalls;
        $traverser = new NodeTraverser();
        $traverser->addVisitor(new NameResolver());
        $traverser->addVisitor($this);
        $traverser->traverse($ast);

        $this->filterNonProjectGlobalCalls();

        return [
            'calls' => $this->calls,
            'fileCalls' => $this->fileCalls,
        ];
    }

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Class_ || $node instanceof Trait_) {
            $this->context->enterClass($node);
        }

        if ($node instanceof ClassMethod && $this->context->getCurrentClass() !== null) {
            $this->context->enterMethod($node->name->toString(), $node);
        }

        if ($node instanceof Function_) {
            $name = $node->namespacedName !== null
                ? $node->namespacedName->toString()
                : $node->name->toString();
            $this->context->enterFunction($name, $node);
        }

        if ($node instanceof Assign && $this->context->isInScope() && $node->var instanceof Variable) {
            $this->context->addAssignment($node);
        }

        if (!$this->context->isInScope()) {
            return null;
        }

        if ($node instanceof StaticCall && $node->class instanceof Name) {
            $this->resolveStaticCall($node);
            return null;
        }

        if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            $this->resolveDynamicCall($node);
            return null;
        }

        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $this->resolveGlobalCall($node);
            return null;
        }

        if ($node instanceof New_ && ($node->class instanceof Name || $node->class instanceof Class_)) {
            $this->resolveNew($node);
            return null;
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof ClassMethod && $this->context->getCurrentClass() !== null) {
            $this->context->leaveMethod();
        }

        if ($node instanceof Class_ || $node instanceof Trait_) {
            $this->context->leaveClass();
        }

        if ($node instanceof Function_) {
            $this->context->leaveFunction();
        }

        return null;
    }

    /**
     * @return list<CallInfo>
     */
    private function getBucket(): array
    {
        if ($this->context->getCurrentClass() !== null && $this->context->getCurrentMethod() !== null) {
            return $this->calls[$this->context->getCurrentClass()][$this->context->getCurrentMethod()] ?? [];
        }
        if ($this->context->getCurrentFunction() !== null) {
            return $this->fileCalls[$this->context->getCurrentFunction()] ?? [];
        }
        return [];
    }

    /**
     * @param list<CallInfo> $bucket
     */
    private function setBucket(array $bucket): void
    {
        if ($this->context->getCurrentClass() !== null && $this->context->getCurrentMethod() !== null) {
            $this->calls[$this->context->getCurrentClass()][$this->context->getCurrentMethod()] = $bucket;
        } elseif ($this->context->getCurrentFunction() !== null) {
            $this->fileCalls[$this->context->getCurrentFunction()] = $bucket;
        }
    }

    private function resolveStaticCall(StaticCall $node): void
    {
        if (!$node->class instanceof Name) {
            return;
        }
        $target = $node->class->toString();
        $methodName = $this->extractCallName($node->name);
        if ($methodName === null) {
            return;
        }

        $isLateStatic = false;
        if ($target === 'self' && $this->context->getCurrentClass() !== null) {
            $target = $this->context->getCurrentClass();
        } elseif ($target === 'parent' && $this->context->getCurrentClass() !== null) {
            if ($this->context->getCurrentClassParent() !== null) {
                $target = $this->context->getCurrentClassParent();
            } else {
                return;
            }
        } elseif ($target === 'static' && $this->context->getCurrentClass() !== null) {
            $target = $this->context->getCurrentClass();
            if (!$this->context->isCurrentClassFinal()) {
                $isLateStatic = true;
            }
        }

        $bucket = $this->getBucket();
        foreach ($bucket as $i => $call) {
            if ($call->kind === CallInfo::KIND_STATIC && $call->targetName === $methodName) {
                $kind = $isLateStatic ? CallInfo::WEAK : CallInfo::STRONG;
                $bucket[$i] = $call->withAssociation(
                    $kind,
                    $target . '::' . $methodName,
                    [$target],
                );
                break;
            }
        }
        $this->setBucket($bucket);
    }

    private function resolveDynamicCall(MethodCall|NullsafeMethodCall $node): void
    {
        $methodName = $this->extractCallName($node->name);
        if ($methodName === null) {
            return;
        }

        $candidates = [];
        $isThis = false;

        if ($node->var instanceof Variable) {
            if ($node->var->name === 'this' && $this->context->getCurrentClass() !== null) {
                $candidates = [$this->context->getCurrentClass()];
                $isThis = true;
            } else {
                $varName = $node->var->name;
                if (is_string($varName)) {
                    $candidates = $this->context->getVariableTypes()['$' . $varName] ?? [];
                }
            }
        } elseif ($node->var instanceof PropertyFetch && $node->var->var instanceof Variable) {
            $varName = $node->var->var->name;
            if (is_string($varName)) {
                $candidates = $this->context->getVariableTypes()['$' . $varName] ?? [];
            }
        }

        $bucket = $this->getBucket();
        foreach ($bucket as $i => $call) {
            if ($call->kind === CallInfo::KIND_DYNAMIC && $call->targetName === $methodName) {
                if ($isThis) {
                    $bucket[$i] = $call->withAssociation(
                        CallInfo::STRONG,
                        $this->context->getCurrentClass() . '->' . $methodName,
                        [$this->context->getCurrentClass()],
                    );
                } elseif (count($candidates) === 1) {
                    $bucket[$i] = $call->withAssociation(
                        CallInfo::STRONG,
                        $candidates[0] . '->' . $methodName,
                        $candidates,
                    );
                } elseif (count($candidates) > 1) {
                    $bucket[$i] = $call->withAssociation(
                        CallInfo::WEAK,
                        null,
                        $candidates,
                    );
                } else {
                    $bucket[$i] = $call->withAssociation(
                        CallInfo::WEAK,
                        null,
                        [],
                    );
                }
                break;
            }
        }
        $this->setBucket($bucket);
    }

    private function resolveGlobalCall(FuncCall $node): void
    {
        $bucket = $this->getBucket();
        $methodName = $this->extractCallName($node->name);
        if ($methodName === null) {
            return;
        }

        foreach ($bucket as $i => $call) {
            if ($call->kind === CallInfo::KIND_GLOBAL && $call->targetName === $methodName) {
                if ($this->isProjectFunction($call->targetName)) {
                    $bucket[$i] = $call->withAssociation(
                        CallInfo::STRONG,
                        $call->targetName,
                        [],
                    );
                } else {
                    $bucket[$i] = $call->withAssociation(
                        CallInfo::WEAK,
                        null,
                        [],
                    );
                }
                break;
            }
        }
        $this->setBucket($bucket);
    }

    private function resolveNew(New_ $node): void
    {
        if (!$node->class instanceof Name) {
            return;
        }
        $target = $node->class->toString();
        $bucket = $this->getBucket();
        foreach ($bucket as $i => $call) {
            if ($call->kind === CallInfo::KIND_CREATE && $call->targetName === $target) {
                $bucket[$i] = $call->withAssociation(
                    CallInfo::STRONG,
                    $target,
                    [$target],
                );
                break;
            }
        }
        $this->setBucket($bucket);
    }

    private function filterNonProjectGlobalCalls(): void
    {
        foreach ($this->calls as $className => $methods) {
            foreach ($methods as $methodName => $bucket) {
                $this->calls[$className][$methodName] = array_values(
                    array_filter(
                        $bucket, function (CallInfo $call) {
                            return $call->kind !== CallInfo::KIND_GLOBAL || $this->isProjectFunction($call->targetName);
                        }
                    )
                );
            }
        }

        foreach ($this->fileCalls as $functionName => $bucket) {
            $this->fileCalls[$functionName] = array_values(
                array_filter(
                    $bucket, function (CallInfo $call) {
                        return $call->kind !== CallInfo::KIND_GLOBAL || $this->isProjectFunction($call->targetName);
                    }
                )
            );
        }
    }

    private function isProjectFunction(string $functionName): bool
    {
        $normalized = ltrim($functionName, '\\');
        return in_array($normalized, $this->projectFunctions, true);
    }

    private function extractCallName(Node $name): ?string
    {
        if ($name instanceof Identifier || $name instanceof Name) {
            return $name->toString();
        }
        return null;
    }
}
