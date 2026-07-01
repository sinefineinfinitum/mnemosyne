<?php declare(strict_types=1);

namespace SineFine\Mnemosyne\Analyzer\Visitor;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Trait_;
use PhpParser\NodeVisitor;
use PhpParser\NodeVisitorAbstract;
use SineFine\Mnemosyne\Analyzer\CallInfo;

final class CallCollectingVisitor extends NodeVisitorAbstract
{
    private ?string $currentClass = null;

    private ?string $currentMethod = null;

    private ?string $currentFunction = null;

    /**
     * @var array<string, array<string, list<CallInfo>>> classFqcn => methodName => list<CallInfo>
     */
    private array $calls = [];

    /**
     * @var array<string, list<CallInfo>> functionName => list<CallInfo>
     */
    private array $fileCalls = [];

    public function enterNode(Node $node): ?int
    {
        if ($node instanceof Class_ || $node instanceof Trait_) {
            if ($node instanceof Class_ && $node->isAnonymous()) {
                return NodeVisitor::DONT_TRAVERSE_CHILDREN;
            }

            if ($node->namespacedName === null) {
                return null;
            }

            $this->currentClass = $node->namespacedName->toString();
            $this->currentMethod = null;
            return null;
        }

        if ($node instanceof ClassMethod && $this->currentClass !== null) {
            $this->currentMethod = $node->name->toString();
            return null;
        }

        if ($node instanceof Function_) {
            if ($this->currentClass !== null) {
                return null;
            }
            $this->currentFunction = $node->name->toString();
            return null;
        }

        if ($this->currentClass === null && $this->currentFunction === null) {
            return null;
        }

        if ($node instanceof StaticCall && $node->class instanceof Name) {
            $methodName = $this->extractName($node->name);
            if ($methodName !== null) {
                $this->registerCall(
                    new CallInfo(
                        kind: CallInfo::KIND_STATIC,
                        targetName: $methodName,
                    )
                );
            }
            return null;
        }

        if ($node instanceof StaticPropertyFetch && $node->class instanceof Name) {
            $propName = $this->extractName($node->name);
            if ($propName !== null) {
                $this->registerCall(
                    new CallInfo(
                        kind: CallInfo::KIND_STATIC,
                        targetName: $propName,
                    )
                );
            }
            return null;
        }

        if ($node instanceof MethodCall || $node instanceof NullsafeMethodCall) {
            $methodName = $this->extractName($node->name);
            if ($methodName !== null) {
                $this->registerCall(
                    new CallInfo(
                        kind: CallInfo::KIND_DYNAMIC,
                        targetName: $methodName,
                    )
                );
            }
            return null;
        }

        if ($node instanceof FuncCall && $node->name instanceof Name) {
            $this->registerCall(
                new CallInfo(
                    kind: CallInfo::KIND_GLOBAL,
                    targetName: $node->name->toString(),
                )
            );
            return null;
        }

        if ($node instanceof New_) {
            if ($node->class instanceof Name) {
                $this->registerCall(
                    new CallInfo(
                        kind: CallInfo::KIND_CREATE,
                        targetName: $node->class->toString(),
                    )
                );
            }
            return null;
        }

        return null;
    }

    public function leaveNode(Node $node): ?int
    {
        if ($node instanceof ClassMethod && $this->currentClass !== null) {
            $this->currentMethod = null;
        }

        if ($node instanceof Class_ || $node instanceof Trait_) {
            if ($node instanceof Class_ && $node->isAnonymous()) {
                return null;
            }
            $this->currentClass = null;
        }

        if ($node instanceof Function_ && $this->currentFunction !== null) {
            $this->currentFunction = null;
        }

        return null;
    }

    /**
     * @return array<string, array<string, list<CallInfo>>>
     */
    public function getCalls(): array
    {
        return $this->calls;
    }

    /**
     * @return array<string, list<CallInfo>> functionName => list<CallInfo>
     */
    public function getFileCalls(): array
    {
        return $this->fileCalls;
    }

    private function registerCall(CallInfo $call): void
    {
        if ($this->currentClass !== null && $this->currentMethod !== null) {
            $this->record($this->currentClass, $this->currentMethod, $call);
        } elseif ($this->currentFunction !== null) {
            $this->recordFile($this->currentFunction, $call);
        }
    }

    private function record(string $classFqcn, string $methodName, CallInfo $call): void
    {
        $this->calls[$classFqcn][$methodName] = $this->addToBucket(
            $this->calls[$classFqcn][$methodName] ?? [],
            $call
        );
    }

    private function recordFile(string $functionName, CallInfo $call): void
    {
        $this->fileCalls[$functionName] = $this->addToBucket(
            $this->fileCalls[$functionName] ?? [],
            $call
        );
    }

    /**
     * @param  list<CallInfo> $bucket
     * @return list<CallInfo>
     */
    private function addToBucket(array $bucket, CallInfo $call): array
    {
        foreach ($bucket as $existing) {
            if ($existing->kind === $call->kind && $existing->targetName === $call->targetName) {
                return $bucket;
            }
        }
        $bucket[] = $call;

        return $bucket;
    }

    private function extractName(Node $name): ?string
    {
        if ($name instanceof Identifier) {
            return $name->toString();
        }
        if ($name instanceof Name) {
            return $name->toString();
        }
        return null;
    }
}
