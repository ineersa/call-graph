<?php declare(strict_types=1);

namespace CallGraph\Viz;

use InvalidArgumentException;
use function addcslashes;
use function array_fill_keys;
use function array_key_exists;
use function array_keys;
use function array_slice;
use function arsort;
use function count;
use function implode;
use function in_array;
use function is_array;
use function ksort;
use function preg_match;
use function sprintf;
use function str_contains;
use function str_starts_with;
use function strrpos;
use function substr;
use function usort;

final class DotRenderer
{
    /** @var array{inputEdges: int, renderedEdges: int, renderedNodes: int, droppedByFilter: int, droppedByMaxNodes: int} */
    private array $lastStats = [
        'inputEdges' => 0,
        'renderedEdges' => 0,
        'renderedNodes' => 0,
        'droppedByFilter' => 0,
        'droppedByMaxNodes' => 0,
    ];

    public function __construct(
        private string $mode = 'class',
        private ?string $includePattern = null,
        private ?string $excludePattern = null,
        private ?int $maxNodes = null,
        private bool $clusterByNamespace = true
    ) {
        if (!in_array($this->mode, ['class', 'method'], true)) {
            throw new InvalidArgumentException('Unsupported mode, expected class or method.');
        }

        $this->assertPattern($this->includePattern, 'include');
        $this->assertPattern($this->excludePattern, 'exclude');

        if ($this->maxNodes !== null && $this->maxNodes < 1) {
            throw new InvalidArgumentException('maxNodes must be greater than zero.');
        }
    }

    /**
     * @param list<CallEdge> $edges
     */
    public function render(array $edges): string
    {
        $nodeFrequency = [];
        $graphEdges = [];
        $droppedByFilter = 0;
        $droppedByMaxNodes = 0;

        foreach ($edges as $edge) {
            [$fromLabel, $toLabel] = $this->labelsFor($edge);
            if (!$this->passesFilters($fromLabel, $toLabel)) {
                $droppedByFilter++;
                continue;
            }

            $nodeFrequency[$fromLabel] = ($nodeFrequency[$fromLabel] ?? 0) + 1;
            $nodeFrequency[$toLabel] = ($nodeFrequency[$toLabel] ?? 0) + 1;

            $edgeKey = $fromLabel . "\0" . $toLabel;
            if (!isset($graphEdges[$edgeKey])) {
                $graphEdges[$edgeKey] = [
                    'from' => $fromLabel,
                    'to' => $toLabel,
                    'types' => [],
                    'unresolved' => false,
                ];
            }

            $graphEdges[$edgeKey]['types'][$edge->callType] = true;
            $graphEdges[$edgeKey]['unresolved'] = $graphEdges[$edgeKey]['unresolved'] || $edge->unresolved;
        }

        if ($this->maxNodes !== null && count($nodeFrequency) > $this->maxNodes) {
            arsort($nodeFrequency);
            $allowedNodes = array_slice(array_keys($nodeFrequency), 0, $this->maxNodes);
            $allowedMap = array_fill_keys($allowedNodes, true);

            foreach ($graphEdges as $edgeKey => $graphEdge) {
                if (!isset($allowedMap[$graphEdge['from']]) || !isset($allowedMap[$graphEdge['to']])) {
                    unset($graphEdges[$edgeKey]);
                    $droppedByMaxNodes++;
                }
            }

            $nodeFrequency = [];
            foreach ($graphEdges as $graphEdge) {
                $nodeFrequency[$graphEdge['from']] = ($nodeFrequency[$graphEdge['from']] ?? 0) + 1;
                $nodeFrequency[$graphEdge['to']] = ($nodeFrequency[$graphEdge['to']] ?? 0) + 1;
            }
        }

        $nodeIds = [];
        $nodeLabels = array_keys($nodeFrequency);
        usort($nodeLabels, static fn (string $a, string $b): int => $a <=> $b);
        foreach ($nodeLabels as $label) {
            $nodeIds[$label] = 'n' . substr(sha1($label), 0, 12);
        }

        $lines = [];
        $lines[] = 'digraph CallGraph {';
        $lines[] = '  graph [rankdir=LR, bgcolor="#FFFFFF", splines=true, overlap=false, pad="0.25"];';
        $lines[] = '  node [shape=box, style="rounded,filled", fillcolor="#EAF2FF", color="#4A6FA5", fontname="Helvetica", fontsize=10, margin="0.10,0.06"];';
        $lines[] = '  edge [color="#5D738A", arrowsize=0.7, penwidth=1.0];';

        $clustered = [];
        if ($this->clusterByNamespace) {
            $clusters = $this->buildNamespaceClusters($nodeLabels);
            foreach ($clusters as $namespace => $labels) {
                $clusterId = 'cluster_' . substr(sha1($namespace), 0, 10);
                $lines[] = '  subgraph ' . $clusterId . ' {';
                $lines[] = '    label=' . $this->quote($namespace) . ';';
                $lines[] = '    color="#D7DEE8";';
                $lines[] = '    style="rounded";';

                foreach ($labels as $label) {
                    $clustered[$label] = true;
                    $lines[] = '    ' . $nodeIds[$label] . $this->nodeAttributes($label) . ';';
                }

                $lines[] = '  }';
            }
        }

        foreach ($nodeLabels as $label) {
            if (array_key_exists($label, $clustered)) {
                continue;
            }

            $lines[] = '  ' . $nodeIds[$label] . $this->nodeAttributes($label) . ';';
        }

        ksort($graphEdges);
        foreach ($graphEdges as $graphEdge) {
            $types = array_keys($graphEdge['types']);
            usort($types, static fn (string $a, string $b): int => $a <=> $b);

            $attributes = [
                'tooltip' => implode(', ', $types),
            ];

            if (count($types) > 1) {
                $attributes['label'] = implode(', ', $types);
                $attributes['fontsize'] = '8';
            }

            if ($graphEdge['unresolved']) {
                $attributes['style'] = 'dashed';
                $attributes['color'] = '#A5634D';
            }

            $lines[] = sprintf(
                '  %s -> %s%s;',
                $nodeIds[$graphEdge['from']],
                $nodeIds[$graphEdge['to']],
                $this->attributes($attributes)
            );
        }

        $lines[] = '}';

        $this->lastStats = [
            'inputEdges' => count($edges),
            'renderedEdges' => count($graphEdges),
            'renderedNodes' => count($nodeLabels),
            'droppedByFilter' => $droppedByFilter,
            'droppedByMaxNodes' => $droppedByMaxNodes,
        ];

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    /**
     * @return array{inputEdges: int, renderedEdges: int, renderedNodes: int, droppedByFilter: int, droppedByMaxNodes: int}
     */
    public function getLastStats(): array
    {
        return $this->lastStats;
    }

    private function assertPattern(?string $pattern, string $name): void
    {
        if ($pattern === null || $pattern === '') {
            return;
        }

        if (@preg_match($pattern, '') === false) {
            throw new InvalidArgumentException('Invalid ' . $name . ' regex pattern: ' . $pattern);
        }
    }

    /**
     * @return array{string, string}
     */
    private function labelsFor(CallEdge $edge): array
    {
        if ($this->mode === 'method') {
            return [$edge->methodCallerLabel(), $edge->methodCalleeLabel()];
        }

        return [$edge->classCallerLabel(), $edge->classCalleeLabel()];
    }

    private function passesFilters(string $fromLabel, string $toLabel): bool
    {
        if ($this->includePattern !== null && $this->includePattern !== '') {
            $matchesFrom = preg_match($this->includePattern, $fromLabel) === 1;
            $matchesTo = preg_match($this->includePattern, $toLabel) === 1;
            if (!$matchesFrom && !$matchesTo) {
                return false;
            }
        }

        if ($this->excludePattern !== null && $this->excludePattern !== '') {
            $matchesFrom = preg_match($this->excludePattern, $fromLabel) === 1;
            $matchesTo = preg_match($this->excludePattern, $toLabel) === 1;
            if ($matchesFrom || $matchesTo) {
                return false;
            }
        }

        return true;
    }

    /**
     * @param list<string> $labels
     * @return array<string, list<string>>
     */
    private function buildNamespaceClusters(array $labels): array
    {
        $clusters = [];
        foreach ($labels as $label) {
            $namespace = $this->extractNamespace($label);
            if ($namespace === null) {
                continue;
            }

            $clusters[$namespace][] = $label;
        }

        ksort($clusters);
        foreach ($clusters as $namespace => $clusterLabels) {
            usort($clusterLabels, static fn (string $a, string $b): int => $a <=> $b);
            $clusters[$namespace] = $clusterLabels;
        }

        return $clusters;
    }

    private function extractNamespace(string $label): ?string
    {
        if (str_starts_with($label, 'function ')) {
            return null;
        }

        $className = $label;
        if ($this->mode === 'method' && str_contains($label, '::')) {
            $parts = explode('::', $label, 2);
            $className = $parts[0];
        }

        if (!$this->isClassLike($className)) {
            return null;
        }

        $lastSeparator = strrpos($className, '\\');
        if ($lastSeparator === false) {
            return null;
        }

        return substr($className, 0, $lastSeparator);
    }

    private function isClassLike(string $className): bool
    {
        return preg_match('/^[A-Za-z_\\\\][A-Za-z0-9_\\\\]*$/', $className) === 1;
    }

    private function nodeAttributes(string $label): string
    {
        $attributes = ['label' => $label];
        if (str_starts_with($label, 'function ')) {
            $attributes['shape'] = 'ellipse';
            $attributes['fillcolor'] = '#F7F2E9';
            $attributes['color'] = '#A57A3A';
        }

        return $this->attributes($attributes);
    }

    /**
     * @param array<string, string> $attributes
     */
    private function attributes(array $attributes): string
    {
        if ($attributes === []) {
            return '';
        }

        $parts = [];
        foreach ($attributes as $name => $value) {
            $parts[] = $name . '=' . $this->quote($value);
        }

        return ' [' . implode(', ', $parts) . ']';
    }

    private function quote(string $value): string
    {
        return '"' . addcslashes($value, "\\\"\n\r\t") . '"';
    }
}
