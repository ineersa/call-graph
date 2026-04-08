# Call Graph

`call-graph` is a PHPStan extension plus a rendering script for extracting and visualizing call graphs.

It generates `callgraph.json` during PHPStan analysis and can render a Graphviz DOT (and optionally SVG) call graph.

## Features

- Extracts method calls (`$obj->method()`), static calls (`Class::method()`), and function calls (`foo()`).
- Uses PHPStan type/reflection data to resolve declaring classes where possible.
- Emits structured JSON with metadata (`file`, `line`, `callType`, `unresolved`).
- Includes compatibility output (`data`) for existing callmap-style tooling.
- Renders DOT/SVG graphs with namespace clustering and regex filtering.

## Install

```bash
composer require --dev ineersa/call-graph
```

Requirements: PHP 8.2+ and PHPStan 2.1+.

## Generate call graph JSON

Run PHPStan with the extension config:

```bash
./vendor/bin/phpstan analyse -c vendor/ineersa/call-graph/callgraph.neon <path/to/src>
```

By default this writes `callgraph.json` in the current working directory.

Override output location in your own config:

```neon
includes:
    - vendor/ineersa/call-graph/callgraph.neon

services:
    errorFormatter.callgraph:
        class: CallGraph\PHPStan\Formatter\CallGraphJsonFormatter
        arguments:
            outputFile: build/callgraph.json
```

## Render visualization

Generate DOT:

```bash
./vendor/bin/callgraph-viz --input callgraph.json --dot callgraph.dot
```

Generate DOT and SVG (requires Graphviz `dot`):

```bash
./vendor/bin/callgraph-viz --input callgraph.json --dot callgraph.dot --svg callgraph.svg
```

Useful filters:

```bash
./vendor/bin/callgraph-viz \
  --mode method \
  --include '/^App\\/' \
  --exclude '/\\Tests\\/' \
  --max-nodes 250
```

## Output format

`callgraph.json` contains:

- `meta`: generation metadata
- `edges`: full graph edges with enriched metadata
- `data`: callmap-compatible shape (`callingClass`, `callingMethod`, `calledClass`, `calledMethod`)

Example edge:

```json
{
  "callerClass": "App\\Service\\UserService",
  "callerMember": "getUser",
  "callerKind": "method",
  "calleeClass": "App\\Repository\\UserRepository",
  "calleeMember": "find",
  "calleeKind": "method",
  "callType": "method",
  "file": "src/Service/UserService.php",
  "line": 41,
  "unresolved": false
}
```
