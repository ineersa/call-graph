<?php declare(strict_types=1);

namespace CallGraph\PHPStan\Collector;

use PhpParser\Node;
use PhpParser\Node\Expr\StaticCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * @implements Collector<StaticCall, array{
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
final class StaticCallCollector implements Collector
{
    public function getNodeType(): string
    {
        return StaticCall::class;
    }

    /**
     * @param StaticCall $node
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
        $methodName = '{dynamic-static-method}';

        if ($node->name instanceof Node\Identifier) {
            $methodName = $node->name->toString();
        } else {
            $unresolved = true;
        }

        $calledClass = '';
        if ($node->class instanceof Node\Name) {
            $calledClass = (string) $scope->resolveName($node->class);
        } else {
            $targetType = $scope->getType($node->class);

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
        }

        if ($calledClass === '') {
            $calledClass = '{unknown-class}';
            $unresolved = true;
        }

        return CollectorUtils::payload(
            $scope,
            $node,
            $calledClass,
            $methodName,
            'method',
            'static',
            $unresolved
        );
    }
}
