<?php declare(strict_types=1);

namespace CallGraph\Viz;

use RuntimeException;
use function array_is_list;
use function file_exists;
use function file_get_contents;
use function is_array;
use function json_decode;
use const JSON_THROW_ON_ERROR;

final class CallGraphLoader
{
    /**
     * @return list<CallEdge>
     */
    public function loadFromFile(string $path): array
    {
        if (!file_exists($path)) {
            throw new RuntimeException('Input file does not exist: ' . $path);
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            throw new RuntimeException('Unable to read input file: ' . $path);
        }

        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new RuntimeException('Input file does not contain a JSON object: ' . $path);
        }

        $rows = [];
        if (isset($decoded['edges']) && is_array($decoded['edges'])) {
            $rows = $decoded['edges'];
        } elseif (isset($decoded['data']) && is_array($decoded['data'])) {
            $rows = $decoded['data'];
        }

        $edges = [];
        foreach ($rows as $row) {
            if (!is_array($row) || array_is_list($row)) {
                continue;
            }

            /** @var array<string, mixed> $row */
            $edges[] = CallEdge::fromArray($row);
        }

        return $edges;
    }
}
