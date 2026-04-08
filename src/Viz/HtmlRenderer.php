<?php declare(strict_types=1);

namespace CallGraph\Viz;

use InvalidArgumentException;
use RuntimeException;
use function array_filter;
use function array_keys;
use function count;
use function date;
use function dirname;
use function explode;
use function file_get_contents;
use function implode;
use function in_array;
use function is_file;
use function json_encode;
use function ksort;
use function str_replace;
use function strrpos;
use function substr;
use const DATE_ATOM;
use const JSON_HEX_AMP;
use const JSON_HEX_APOS;
use const JSON_HEX_QUOT;
use const JSON_HEX_TAG;
use const JSON_THROW_ON_ERROR;
use const JSON_UNESCAPED_SLASHES;

final class HtmlRenderer
{
    /** @var array{inputEdges: int, knownNamespaces: int} */
    private array $lastStats = ['inputEdges' => 0, 'knownNamespaces' => 0];

    public function __construct(
        private string $defaultMode = 'class',
        private bool $defaultIncludeFunctions = false,
        private int $defaultNamespaceDepth = 2,
        private int $defaultMinEdgeWeight = 1,
        private ?int $defaultMaxNodes = null
    ) {
        if (!in_array($this->defaultMode, ['class', 'method', 'namespace'], true)) {
            throw new InvalidArgumentException('Unsupported mode, expected class, method, or namespace.');
        }

        if ($this->defaultNamespaceDepth < 1) {
            throw new InvalidArgumentException('defaultNamespaceDepth must be greater than zero.');
        }

        if ($this->defaultMinEdgeWeight < 1) {
            throw new InvalidArgumentException('defaultMinEdgeWeight must be greater than zero.');
        }

        if ($this->defaultMaxNodes !== null && $this->defaultMaxNodes < 1) {
            throw new InvalidArgumentException('defaultMaxNodes must be greater than zero.');
        }
    }

    /**
     * @param list<CallEdge> $edges
     */
    public function render(array $edges): string
    {
        $rows = [];
        $knownNamespaces = [];

        foreach ($edges as $edge) {
            $rows[] = [
                'callerClass' => $edge->callerClass,
                'callerMember' => $edge->callerMember,
                'calleeClass' => $edge->calleeClass,
                'calleeMember' => $edge->calleeMember,
                'callType' => $edge->callType,
                'unresolved' => $edge->unresolved,
            ];

            $this->collectNamespaceSuggestion($knownNamespaces, $edge->callerClass, $edge->callerMember);
            $this->collectNamespaceSuggestion($knownNamespaces, $edge->calleeClass, $edge->calleeMember);
        }

        ksort($knownNamespaces);

        $payload = [
            'meta' => ['generatedAt' => date(DATE_ATOM), 'edgeCount' => count($rows)],
            'defaults' => [
                'mode' => $this->defaultMode,
                'includeFunctions' => $this->defaultIncludeFunctions,
                'namespaceDepth' => $this->defaultNamespaceDepth,
                'minEdgeWeight' => $this->defaultMinEdgeWeight,
                'maxNodes' => $this->defaultMaxNodes,
            ],
            'namespaces' => array_keys($knownNamespaces),
            'edges' => $rows,
        ];

        $html = str_replace(
            ['__STYLE__', '__GRAPH_BUILDER__', '__APP_JS__', '__PAYLOAD__'],
            [
                $this->readAsset('styles.css'),
                $this->readAsset('graph-builder.js'),
                $this->readAsset('app.js'),
                (string) json_encode(
                    $payload,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT
                ),
            ],
            $this->readAsset('template.html')
        );

        $this->lastStats = ['inputEdges' => count($rows), 'knownNamespaces' => count($knownNamespaces)];

        return $html;
    }

    /** @return array{inputEdges: int, knownNamespaces: int} */
    public function getLastStats(): array
    {
        return $this->lastStats;
    }

    private function readAsset(string $name): string
    {
        $path = dirname(__DIR__) . '/Viz/HtmlAssets/' . $name;
        if (!is_file($path)) {
            throw new RuntimeException('Missing HTML renderer asset: ' . $path);
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new RuntimeException('Unable to read HTML renderer asset: ' . $path);
        }

        return $content;
    }

    /** @param array<string, bool> $knownNamespaces */
    private function collectNamespaceSuggestion(array &$knownNamespaces, string $className, string $member): void
    {
        $symbol = $className !== '' ? $className : $member;
        $normalized = $this->normalizeSymbol($symbol);
        if ($normalized === '') {
            return;
        }

        $separator = strrpos($normalized, '\\');
        if ($separator === false) {
            $knownNamespaces['{global}'] = true;
            return;
        }

        $namespace = substr($normalized, 0, $separator);
        $knownNamespaces[$namespace !== '' ? $namespace : '{global}'] = true;
    }

    private function normalizeSymbol(string $symbol): string
    {
        $parts = array_filter(explode('\\', str_replace('/', '\\', $symbol)), static fn (string $part): bool => $part !== '');
        return $parts === [] ? '' : implode('\\', $parts);
    }
}
