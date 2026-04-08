<?php declare(strict_types=1);

namespace CallGraph\PHPStan\Formatter;

use CallGraph\PHPStan\Rule\CallGraphRule;
use PHPStan\Command\AnalysisResult;
use PHPStan\Command\ErrorFormatter\ErrorFormatter;
use PHPStan\Command\ErrorFormatter\TableErrorFormatter;
use PHPStan\Command\Output;
use function count;
use function date;
use function dirname;
use function file_put_contents;
use function is_bool;
use function is_dir;
use function is_int;
use function is_string;
use function json_encode;
use function mkdir;
use function usort;
use const DATE_ATOM;
use const JSON_PRETTY_PRINT;
use const JSON_UNESCAPED_SLASHES;

final class CallGraphJsonFormatter implements ErrorFormatter
{
    public function __construct(
        private TableErrorFormatter $tableErrorFormatter,
        private string $outputFile = 'callgraph.json'
    ) {
    }

    public function formatErrors(AnalysisResult $analysisResult, Output $output): int
    {
        if ($analysisResult->hasInternalErrors()) {
            return $this->tableErrorFormatter->formatErrors($analysisResult, $output);
        }

        $edges = [];
        $seen = [];

        foreach ($analysisResult->getFileSpecificErrors() as $error) {
            $identifier = $error->getIdentifier();
            if ($identifier === 'ignore.unmatchedLine' || $identifier === 'ignore.unmatchedIdentifier') {
                continue;
            }

            if ($identifier !== CallGraphRule::IDENTIFIER) {
                return $this->tableErrorFormatter->formatErrors($analysisResult, $output);
            }

            $metadata = $error->getMetadata();

            $edge = [
                'callerClass' => $this->stringValue($metadata['callerClass'] ?? ''),
                'callerMember' => $this->stringValue($metadata['callerMember'] ?? '{unknown}'),
                'callerKind' => $this->stringValue($metadata['callerKind'] ?? 'unknown'),
                'calleeClass' => $this->stringValue($metadata['calleeClass'] ?? ''),
                'calleeMember' => $this->stringValue($metadata['calleeMember'] ?? '{unknown}'),
                'calleeKind' => $this->stringValue($metadata['calleeKind'] ?? 'unknown'),
                'callType' => $this->stringValue($metadata['callType'] ?? 'unknown'),
                'file' => $this->stringValue($metadata['file'] ?? ''),
                'line' => $this->intValue($metadata['line'] ?? 0),
                'unresolved' => $this->boolValue($metadata['unresolved'] ?? false),
            ];

            $key = $edge['callerClass'] . "\0"
                . $edge['callerMember'] . "\0"
                . $edge['calleeClass'] . "\0"
                . $edge['calleeMember'] . "\0"
                . $edge['callType'] . "\0"
                . $edge['file'] . "\0"
                . $edge['line'];

            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $edges[] = $edge;
        }

        usort(
            $edges,
            static fn (array $left, array $right): int => [
                $left['file'],
                $left['line'],
                $left['callerClass'],
                $left['callerMember'],
                $left['calleeClass'],
                $left['calleeMember'],
                $left['callType'],
            ] <=> [
                $right['file'],
                $right['line'],
                $right['callerClass'],
                $right['callerMember'],
                $right['calleeClass'],
                $right['calleeMember'],
                $right['callType'],
            ]
        );

        $directory = dirname($this->outputFile);
        if ($directory !== '.' && $directory !== '' && !is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $compatData = [];
        foreach ($edges as $edge) {
            $compatData[] = [
                'callingClass' => $edge['callerClass'],
                'callingMethod' => $edge['callerMember'],
                'calledClass' => $edge['calleeClass'],
                'calledMethod' => $edge['calleeMember'],
            ];
        }

        file_put_contents(
            $this->outputFile,
            (string) json_encode(
                [
                    'meta' => [
                        'formatVersion' => 1,
                        'generatedAt' => date(DATE_ATOM),
                        'edgeCount' => count($edges),
                    ],
                    'edges' => $edges,
                    'data' => $compatData,
                ],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
            ) . "\n"
        );

        return 0;
    }

    private function stringValue(mixed $value): string
    {
        return is_string($value) ? $value : '';
    }

    private function intValue(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }

    private function boolValue(mixed $value): bool
    {
        return is_bool($value) ? $value : false;
    }
}
