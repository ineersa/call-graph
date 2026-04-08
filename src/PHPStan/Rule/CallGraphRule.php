<?php declare(strict_types=1);

namespace CallGraph\PHPStan\Rule;

use CallGraph\PHPStan\Collector\FunctionCallCollector;
use CallGraph\PHPStan\Collector\MethodCallCollector;
use CallGraph\PHPStan\Collector\StaticCallCollector;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\CollectedDataNode;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

/**
 * @implements Rule<CollectedDataNode>
 * @phpstan-type Payload array{
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
final class CallGraphRule implements Rule
{
    public const IDENTIFIER = 'callgraph.data';

    /** @var list<class-string> */
    private const COLLECTORS = [
        MethodCallCollector::class,
        StaticCallCollector::class,
        FunctionCallCollector::class,
    ];

    public function getNodeType(): string
    {
        return CollectedDataNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $errors = [];

        foreach (self::COLLECTORS as $collectorClass) {
            foreach ($node->get($collectorClass) as $rows) {
                foreach ($this->normalizeRows($rows) as $row) {
                    $errors[] = RuleErrorBuilder::message('Call graph metadata')
                        ->identifier(self::IDENTIFIER)
                        ->metadata($row)
                        ->build();
                }
            }
        }

        return $errors;
    }

    /**
     * @param mixed $rows
     * @return list<Payload>
     */
    private function normalizeRows(mixed $rows): array
    {
        if ($this->isPayload($rows)) {
            return [$rows];
        }

        if (!is_array($rows)) {
            return [];
        }

        $normalized = [];
        foreach ($rows as $row) {
            if ($this->isPayload($row)) {
                $normalized[] = $row;
            }
        }

        return $normalized;
    }

    /**
     * @param mixed $row
     * @phpstan-assert-if-true Payload $row
     */
    private function isPayload(mixed $row): bool
    {
        return is_array($row)
            && array_key_exists('callerClass', $row)
            && array_key_exists('callerMember', $row)
            && array_key_exists('calleeClass', $row)
            && array_key_exists('calleeMember', $row)
            && array_key_exists('callType', $row)
            && array_key_exists('file', $row)
            && array_key_exists('line', $row);
    }
}
