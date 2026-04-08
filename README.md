# Call Graph

`call-graph` is a PHPStan extension plus a rendering script for extracting and visualizing call graphs.

It generates `callgraph.json` during PHPStan analysis and can render a Graphviz DOT (and optionally SVG) call graph.

## Features

- Extracts method calls (`$obj->method()`), static calls (`Class::method()`), and function calls (`foo()`).
- Uses PHPStan type/reflection data to resolve declaring classes where possible.
- Emits structured JSON with metadata (`file`, `line`, `callType`, `unresolved`).
- Includes compatibility output (`data`) for existing callmap-style tooling.
- Renders DOT/SVG graphs with namespace clustering and regex filtering.
- Excludes function-involved edges by default in visualization (use `--include-functions` to opt in).
- Supports coupling-oriented views with namespace mode and edge-weight filtering.

## Install

```bash
composer require --dev ineersa/call-graph
```

Requirements: PHP 8.2+ and PHPStan 2.1+.

If you use `phpstan/extension-installer`, `callgraph.neon` is auto-registered from package metadata.

Without extension-installer, include it manually in your `phpstan.neon`:

```neon
includes:
    - vendor/ineersa/call-graph/callgraph.neon
```

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

If Graphviz fails with `trouble in init_rank`, use one of these:

```bash
./vendor/bin/callgraph-viz --no-cluster --svg callgraph.svg
./vendor/bin/callgraph-viz --engine sfdp --svg callgraph.svg
```

Useful filters:

```bash
./vendor/bin/callgraph-viz \
  --mode method \
  --include '/^App\\/' \
  --exclude '/\\Tests\\/' \
  --max-nodes 250
```

Large graph / coupling view (recommended):

```bash
./vendor/bin/callgraph-viz \
  --mode namespace \
  --namespace-depth 3 \
  --min-edge-weight 2 \
  --max-nodes 120 \
  --include '/^App\\/' \
  --dot coupling.dot
```

Include functions if needed:

```bash
./vendor/bin/callgraph-viz --include-functions --mode method
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

## Acknowledgements

This project follows the PHPStan pattern for extracting structured data from analysis (collectors, a `CollectedDataNode` rule, and a custom error formatter), as described in [Using PHPStan to Extract Data About Your Codebase](https://phpstan.org/blog/using-phpstan-to-extract-data-about-your-codebase).

The callmap-compatible `data` shape and the overall idea of emitting a JSON call map from PHPStan build on prior work in [stella-maris/callmap](https://gitlab.com/stella-maris/callmap).
