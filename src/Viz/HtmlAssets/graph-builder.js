(function () {
  'use strict';

  function buildGraph(rawEdges, state) {
    var aggregate = new Map();
    var droppedByNamespace = 0;
    var droppedByFunctions = 0;

    rawEdges.forEach(function (edge) {
      var from = endpointInfo(edge, 'callerClass', 'callerMember', state.mode, state.namespaceDepth);
      var to = endpointInfo(edge, 'calleeClass', 'calleeMember', state.mode, state.namespaceDepth);
      if (!state.includeFunctions && (from.isFunction || to.isFunction)) {
        droppedByFunctions += 1;
        return;
      }
      if (!namespaceFilterMatch(from.namespace, to.namespace, state.namespaces, !!state.strictNamespaces)) {
        droppedByNamespace += 1;
        return;
      }
      var key = from.label + '\u0000' + to.label;
      if (!aggregate.has(key)) {
        aggregate.set(key, { from: from.label, to: to.label, types: new Set(), unresolved: false, weight: 0 });
      }
      var item = aggregate.get(key);
      item.weight += 1;
      item.unresolved = item.unresolved || !!edge.unresolved;
      item.types.add(normalizeCallType(edge.callType));
    });

    var droppedByMinEdgeWeight = 0;
    var weightedEdges = [];
    aggregate.forEach(function (item) {
      if (item.weight < state.minEdgeWeight) {
        droppedByMinEdgeWeight += 1;
        return;
      }
      weightedEdges.push(item);
    });

    var nodeWeight = new Map();
    weightedEdges.forEach(function (edge) {
      nodeWeight.set(edge.from, (nodeWeight.get(edge.from) || 0) + edge.weight);
      nodeWeight.set(edge.to, (nodeWeight.get(edge.to) || 0) + edge.weight);
    });

    var droppedByMaxNodes = 0;
    if (state.maxNodes !== null && nodeWeight.size > state.maxNodes) {
      var sortedNodes = Array.from(nodeWeight.entries()).sort(function (left, right) { return right[1] - left[1]; });
      var allowed = new Set(sortedNodes.slice(0, state.maxNodes).map(function (entry) { return entry[0]; }));
      weightedEdges = weightedEdges.filter(function (edge) {
        var keep = allowed.has(edge.from) && allowed.has(edge.to);
        if (!keep) { droppedByMaxNodes += 1; }
        return keep;
      });
      nodeWeight = new Map();
      weightedEdges.forEach(function (edge) {
        nodeWeight.set(edge.from, (nodeWeight.get(edge.from) || 0) + edge.weight);
        nodeWeight.set(edge.to, (nodeWeight.get(edge.to) || 0) + edge.weight);
      });
    }

    var labels = Array.from(nodeWeight.keys()).sort(function (a, b) { return a.localeCompare(b); });
    var labelToId = new Map();
    labels.forEach(function (label, index) { labelToId.set(label, 'n' + String(index)); });

    var nodeById = {};
    var elements = labels.map(function (label) {
      var id = labelToId.get(label);
      var isFunction = label.indexOf('function ') === 0;
      nodeById[id] = { id: id, label: label, namespace: state.mode === 'namespace' ? namespaceFromNamespaceLabel(label) : namespaceForLabel(label, state.mode), isFunction: isFunction, weight: nodeWeight.get(label) || 1 };
      return { data: { id: id, label: label, weight: nodeWeight.get(label) || 1 }, classes: isFunction ? 'function' : '' };
    });

    var edges = weightedEdges.map(function (edge, index) {
      var types = Array.from(edge.types).sort();
      var parts = [];
      if (types.length > 1) { parts.push(types.join(',')); }
      if (edge.weight > 1) { parts.push('x' + edge.weight); }
      return { id: 'e' + String(index), source: labelToId.get(edge.from), target: labelToId.get(edge.to), weight: edge.weight, types: types, unresolved: edge.unresolved, label: parts.join(' ') };
    });

    edges.forEach(function (edge) {
      elements.push({ data: { id: edge.id, source: edge.source, target: edge.target, weight: edge.weight, label: edge.label }, classes: edge.unresolved ? 'unresolved' : '' });
    });

    return { nodeById: nodeById, edges: edges, elements: elements, stats: { renderedNodes: labels.length, renderedEdges: edges.length, droppedByNamespace: droppedByNamespace, droppedByFunctions: droppedByFunctions, droppedByMinEdgeWeight: droppedByMinEdgeWeight, droppedByMaxNodes: droppedByMaxNodes } };
  }

  function endpointInfo(edge, classKey, memberKey, mode, depth) {
    var className = normalizeSymbol(edge[classKey]);
    var member = normalizeSymbol(edge[memberKey]);
    if (mode === 'namespace') {
      if (className !== '') {
        var classLabel = truncateNamespace(className, depth);
        return { label: classLabel, namespace: classNamespace(className), isFunction: false };
      }
      var functionLabelNs = truncateNamespace(member, depth);
      var functionFullNs = functionNamespace(member);
      return functionLabelNs === '{global}'
        ? { label: 'function {global}', namespace: functionFullNs, isFunction: true }
        : { label: 'function ' + functionLabelNs, namespace: functionFullNs, isFunction: true };
    }
    if (className !== '') { return { label: mode === 'method' ? className + '::' + (member || '{unknown}') : className, namespace: classNamespace(className), isFunction: false }; }
    var functionLabel = member || '{unknown}';
    return { label: 'function ' + functionLabel, namespace: functionNamespace(functionLabel), isFunction: true };
  }

  function normalizeSymbol(value) { return typeof value === 'string' ? value.replace(/\//g, '\\').replace(/^\\+/, '').trim() : ''; }
  function truncateNamespace(symbol, depth) { var n = normalizeSymbol(symbol); if (n === '') { return '{global}'; } var p = n.split('\\').filter(Boolean); return p.length === 0 ? '{global}' : p.slice(0, Math.max(1, depth)).join('\\'); }
  function classNamespace(name) { var i = normalizeSymbol(name).lastIndexOf('\\'); return i === -1 ? '{global}' : (normalizeSymbol(name).slice(0, i) || '{global}'); }
  function functionNamespace(name) { var i = normalizeSymbol(name).lastIndexOf('\\'); return i === -1 ? '{global}' : (normalizeSymbol(name).slice(0, i) || '{global}'); }
  function namespaceForLabel(label, mode) { if (label.indexOf('function ') === 0) { return functionNamespace(label.slice(9)); } if (mode === 'method') { var i = label.indexOf('::'); if (i !== -1) { return classNamespace(label.slice(0, i)); } } return classNamespace(label); }
  function namespaceFromNamespaceLabel(label) { if (label.indexOf('function ') === 0) { var f = label.slice(9).trim(); return f === '' ? '{global}' : f; } return label === '' ? '{global}' : label; }
  function namespaceFilterMatch(from, to, filters, strict) {
    if (!Array.isArray(filters) || filters.length === 0) {
      return true;
    }
    if (strict) {
      var fromOk = filters.some(function (p) { return namespaceMatches(from, p); });
      var toOk = filters.some(function (p) { return namespaceMatches(to, p); });
      return fromOk && toOk;
    }
    return filters.some(function (p) { return namespaceMatches(from, p) || namespaceMatches(to, p); });
  }
  function namespaceMatches(value, prefix) { return prefix === '{global}' ? value === '{global}' : value === prefix || value.indexOf(prefix + '\\') === 0; }
  function normalizeCallType(value) { if (typeof value !== 'string') { return 'unknown'; } var t = value.trim(); return t === '' ? 'unknown' : t; }

  window.CallGraphBuild = { buildGraph: buildGraph };
})();
