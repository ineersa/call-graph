<?php declare(strict_types=1);

namespace CallGraph\PHPStan\Collector;

use PhpParser\Node;
use PhpParser\Node\Expr\FuncCall;
use PHPStan\Analyser\Scope;
use PHPStan\Collectors\Collector;

/**
 * @implements Collector<FuncCall, array{
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
final class FunctionCallCollector implements Collector
{
    public function getNodeType(): string
    {
        return FuncCall::class;
    }

    /**
     * @param FuncCall $node
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
        $functionName = '{dynamic-function}';

        if ($node->name instanceof Node\Name) {
            $functionName = (string) $scope->resolveName($node->name);
        } else {
            $unresolved = true;
        }

        return CollectorUtils::payload(
            $scope,
            $node,
            '',
            $functionName,
            'function',
            'function',
            $unresolved
        );
    }
}
