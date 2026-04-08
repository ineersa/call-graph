<?php declare(strict_types=1);

namespace CallGraph\PHPStan\Collector;

use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * @implements Collector<MethodCall, array{
 *   callerClass: string,
 *   callerMember: string,
 *   callerKind: string,
 *   calleeClass: string,
 *   calleeMember: string,
 *   calleeKind: string,
 *   callType: string,
 *   file: string,
 *   line: int,
 *   unresolved: bool
 * }>
 */
final class MethodCallCollector implements Collector
{
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    /**
     * @param MethodCall $node
     * @return array{
     *   callerClass: string,
     *   callerMember: string,
     *   callerKind: string,
     *   calleeClass: string,
     *   calleeMember: string,
     *   calleeKind: string,
     *   callType: string,
     *   file: string,
     *   line: int,
     *   unresolved: bool
     * }
     */
    public function processNode(Node $node, Scope $scope): array
    {
        $unresolved = false;
        $methodName = '{dynamic-method}';

        if ($node->name instanceof Node\Identifier) {
            $methodName = $node->name->toString();
        } else {
            $unresolved = true;
        }

        $calledClass = '';
        $targetType = $scope->getType($node->var);

        if ($node->name instanceof Node\Identifier) {
            $methodReflection = $scope->getMethodReflection($targetType, $methodName);
            if ($methodReflection !== null) {
                $calledClass = $methodReflection->getDeclaringClass()->getName();
            }
        }

        if ($calledClass === '') {
            $calledClass = CollectorUtils::typeToString($targetType);
            $unresolved = true;
        }

        return CollectorUtils::payload(
            $scope,
            $node,
            $calledClass,
            $methodName,
            'method',
            'method',
            $unresolved
        );
    }
}
