<?php declare(strict_types=1);

namespace CallGraph\PHPStan\Collector;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ExtendedMethodReflection;
use PHPStan\Type\Type;
use PHPStan\Type\VerbosityLevel;

final class CollectorUtils
{
    /**
     * @return array{callerClass: string, callerMember: string, callerKind: string}
     */
    public static function callerContext(Scope $scope): array
    {
        $function = $scope->getFunction();
        if ($function instanceof ExtendedMethodReflection) {
            return [
                'callerClass' => $function->getDeclaringClass()->getName(),
                'callerMember' => $function->getName(),
                'callerKind' => 'method',
            ];
        }

        if ($function !== null) {
            return [
                'callerClass' => '',
                'callerMember' => $function->getName(),
                'callerKind' => 'function',
            ];
        }

        return [
            'callerClass' => '',
            'callerMember' => '{global}',
            'callerKind' => 'unknown',
        ];
    }

    public static function typeToString(Type $type): string
    {
        return $type->describe(VerbosityLevel::typeOnly());
    }

    /**
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
    public static function payload(
        Scope $scope,
        Node $node,
        string $calleeClass,
        string $calleeMember,
        string $calleeKind,
        string $callType,
        bool $unresolved
    ): array {
        $caller = self::callerContext($scope);

        return [
            'callerClass' => $caller['callerClass'],
            'callerMember' => $caller['callerMember'],
            'callerKind' => $caller['callerKind'],
            'calleeClass' => $calleeClass,
            'calleeMember' => $calleeMember,
            'calleeKind' => $calleeKind,
            'callType' => $callType,
            'file' => $scope->getFile(),
            'line' => $node->getStartLine(),
            'unresolved' => $unresolved,
        ];
    }
}
