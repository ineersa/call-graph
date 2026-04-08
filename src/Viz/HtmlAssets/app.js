(function () {
  'use strict';

  var payloadEl = document.getElementById('callgraph-payload');
  var graphRoot = document.getElementById('graph');
  var emptyState = document.getElementById('emptyState');

  function escapeHtml(value) {
    return String(value).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#039;');
  }

  function parseMode(value, fallback) { return value === 'class' || value === 'method' || value === 'namespace' ? value : fallback; }
  function parsePositiveInt(value, fallback) { var n = parseInt(String(value), 10); return Number.isFinite(n) && n > 0 ? n : fallback; }
  function parseOptionalPositiveInt(value) { if (value === null || typeof value === 'undefined') { return null; } var t = String(value).trim(); if (t === '') { return null; } var n = parseInt(t, 10); return Number.isFinite(n) && n > 0 ? n : null; }
  function parseNamespaces(value) { if (typeof value !== 'string' || value.trim() === '') { return []; } var s = new Set(); value.split(',').forEach(function (x) { var n = x.trim().replace(/\//g, '\\').replace(/^\\+/, '').replace(/\\+$/, ''); if (n !== '') { s.add(n); } }); return Array.from(s).sort(); }
  function fatal(message) { graphRoot.style.display = 'none'; emptyState.style.display = 'grid'; emptyState.textContent = message; }

  if (!payloadEl) { fatal('Missing embedded graph payload.'); return; }
  if (!window.CallGraphBuild || typeof window.CallGraphBuild.buildGraph !== 'function') { fatal('Graph builder script is not loaded.'); return; }
  if (typeof window.cytoscape !== 'function') { fatal('Cytoscape.js could not be loaded from CDN.'); return; }

  var payload;
  try { payload = JSON.parse(payloadEl.textContent || '{}'); } catch (error) { fatal('Unable to parse graph payload.'); return; }

  var defaults = payload.defaults || {};
  var edges = Array.isArray(payload.edges) ? payload.edges : [];
  var hints = Array.isArray(payload.namespaces) ? payload.namespaces : [];

  var modeInput = document.getElementById('mode');
  var namespaceDepthInput = document.getElementById('namespaceDepth');
  var minEdgeWeightInput = document.getElementById('minEdgeWeight');
  var maxNodesInput = document.getElementById('maxNodes');
  var namespacesInput = document.getElementById('namespaces');
  var includeFunctionsInput = document.getElementById('includeFunctions');
  var strictNamespacesInput = document.getElementById('strictNamespaces');
  var statsRoot = document.getElementById('stats');
  var activeFiltersRoot = document.getElementById('activeFilters');
  var focusTarget = document.getElementById('focusTarget');
  var outgoingList = document.getElementById('outgoing');
  var incomingList = document.getElementById('incoming');

  document.getElementById('namespaceHints').innerHTML = hints.slice(0, 800).map(function (ns) { return '<option value="' + escapeHtml(ns) + '"></option>'; }).join('');

  var params = new URLSearchParams(window.location.search);
  var state = {
    mode: parseMode(params.get('mode'), parseMode(defaults.mode, 'class')),
    includeFunctions: params.get('includeFunctions') === '1' || (!!defaults.includeFunctions && params.get('includeFunctions') === null),
    strictNamespaces: params.get('strictNamespaces') === '1',
    namespaceDepth: parsePositiveInt(params.get('namespaceDepth'), parsePositiveInt(defaults.namespaceDepth, 2)),
    minEdgeWeight: parsePositiveInt(params.get('minEdgeWeight'), parsePositiveInt(defaults.minEdgeWeight, 1)),
    maxNodes: params.get('maxNodes') === null ? parseOptionalPositiveInt(defaults.maxNodes) : parseOptionalPositiveInt(params.get('maxNodes')),
    namespaces: parseNamespaces(params.get('ns'))
  };

  modeInput.value = state.mode;
  includeFunctionsInput.checked = state.includeFunctions;
  strictNamespacesInput.checked = state.strictNamespaces;
  namespaceDepthInput.value = String(state.namespaceDepth);
  minEdgeWeightInput.value = String(state.minEdgeWeight);
  maxNodesInput.value = state.maxNodes === null ? '' : String(state.maxNodes);
  namespacesInput.value = state.namespaces.join(',');

  var graphData = window.CallGraphBuild.buildGraph(edges, state);
  var chips = [
    ['Input edges', edges.length], ['Rendered nodes', graphData.stats.renderedNodes], ['Rendered edges', graphData.stats.renderedEdges],
    ['Drop namespace', graphData.stats.droppedByNamespace], ['Drop functions', graphData.stats.droppedByFunctions], ['Drop weight', graphData.stats.droppedByMinEdgeWeight], ['Drop max nodes', graphData.stats.droppedByMaxNodes]
  ];
  statsRoot.innerHTML = chips.map(function (x) { return '<span class="chip"><strong>' + x[1] + '</strong> ' + x[0] + '</span>'; }).join('');

  var active = [
    'mode=' + state.mode,
    'namespaceDepth=' + state.namespaceDepth,
    'minEdgeWeight=' + state.minEdgeWeight,
    state.includeFunctions ? 'includeFunctions=1' : 'includeFunctions=0',
    state.strictNamespaces ? 'strictNamespaces=1' : 'strictNamespaces=0'
  ];
  if (state.maxNodes !== null) { active.push('maxNodes=' + state.maxNodes); }
  if (state.namespaces.length > 0) { active.push('ns=' + state.namespaces.join(',')); }
  activeFiltersRoot.innerHTML = active.map(function (x) { return '<span class="chip">' + escapeHtml(x) + '</span>'; }).join('');

  document.getElementById('applyFilters').addEventListener('click', reloadFromForm);
  document.getElementById('resetFilters').addEventListener('click', function () { window.location.href = window.location.pathname; });
  document.getElementById('filters').addEventListener('submit', function (event) { event.preventDefault(); reloadFromForm(); });
  document.getElementById('filters').addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') { return; }
    if (event.target && event.target.tagName === 'TEXTAREA') { return; }
    event.preventDefault();
    reloadFromForm();
  });
  [modeInput, namespaceDepthInput, minEdgeWeightInput, maxNodesInput, includeFunctionsInput, strictNamespacesInput]
    .forEach(function (el) { el.addEventListener('change', reloadFromForm); });

  if (graphData.elements.length === 0 || graphData.edges.length === 0) {
    fatal('No edges match current filters. Reset filters or lower thresholds.');
    outgoingList.innerHTML = '<li>No node selected.</li>';
    incomingList.innerHTML = '<li>No node selected.</li>';
    return;
  }

  var cy = window.cytoscape({ container: graphRoot, elements: graphData.elements, style: [
    { selector: 'node', style: { 'background-color': '#1f7cb8', 'border-color': '#175d86', 'border-width': 1, label: 'data(label)', color: '#1c2f42', 'font-family': 'Manrope', 'font-size': 10, 'text-wrap': 'wrap', 'text-max-width': 200, 'text-background-color': '#ffffff', 'text-background-opacity': 0.86, 'text-background-padding': '2px', 'text-background-shape': 'roundrectangle', width: 'mapData(weight, 1, 30, 18, 44)', height: 'mapData(weight, 1, 30, 18, 44)' } },
    { selector: 'node.function', style: { 'background-color': '#c77a2b', 'border-color': '#8b551b', shape: 'ellipse' } },
    { selector: 'edge', style: { 'curve-style': 'bezier', 'line-color': '#5a758e', 'target-arrow-color': '#5a758e', 'target-arrow-shape': 'triangle', width: 'mapData(weight, 1, 20, 1.2, 6.5)', label: 'data(label)', 'font-family': 'Manrope', 'font-size': 9, color: '#334b60', 'text-background-color': '#ffffff', 'text-background-opacity': 0.8, 'text-background-padding': '1px', 'text-rotation': 'autorotate' } },
    { selector: 'edge.unresolved', style: { 'line-style': 'dashed', 'line-color': '#a45745', 'target-arrow-color': '#a45745' } },
    { selector: '.hidden', style: { display: 'none' } }, { selector: '.focused', style: { 'border-width': 3, 'border-color': '#1e8d6f' } }, { selector: '.neighbor', style: { 'border-width': 2, 'border-color': '#c77a2b' } }
  ] });

  function layoutMain() { cy.layout({ name: 'cose', animate: false, fit: true, padding: 26, nodeRepulsion: 220000, idealEdgeLength: 120, edgeElasticity: 80, nodeOverlap: 26 }).run(); }
  function showNodeLists(nodeId) {
    var out = graphData.edges.filter(function (e) { return e.source === nodeId; }).sort(function (a, b) { return b.weight - a.weight; });
    var inc = graphData.edges.filter(function (e) { return e.target === nodeId; }).sort(function (a, b) { return b.weight - a.weight; });
    function listHtml(items, dir) { if (items.length === 0) { return '<li>None</li>'; } return items.map(function (e) { var n = graphData.nodeById[dir === 'out' ? e.target : e.source]; return '<li>' + escapeHtml(n.label) + '<br>weight=' + e.weight + ', type=' + escapeHtml(e.types.join(', ')) + (e.unresolved ? ', unresolved' : '') + '</li>'; }).join(''); }
    outgoingList.innerHTML = listHtml(out, 'out'); incomingList.innerHTML = listHtml(inc, 'in');
  }
  function clearFocus() { cy.elements().removeClass('hidden focused neighbor'); layoutMain(); focusTarget.textContent = 'Click node to focus neighborhood.'; outgoingList.innerHTML = '<li>No node selected.</li>'; incomingList.innerHTML = '<li>No node selected.</li>'; }

  layoutMain();
  clearFocus();
  cy.on('tap', 'node', function (event) { var node = event.target; cy.elements().removeClass('focused neighbor').addClass('hidden'); var hood = node.closedNeighborhood(); hood.removeClass('hidden'); node.addClass('focused'); hood.nodes().not(node).addClass('neighbor'); cy.elements().not('.hidden').layout({ name: 'breadthfirst', directed: true, roots: node, animate: false, fit: true, padding: 30, spacingFactor: 1.2 }).run(); var n = graphData.nodeById[node.id()]; focusTarget.textContent = 'Focus: ' + n.label + ' [' + n.namespace + ']'; showNodeLists(node.id()); });
  cy.on('tap', function (event) { if (event.target === cy) { clearFocus(); } });

  function reloadFromForm() {
    var q = new URLSearchParams();
    q.set('mode', parseMode(modeInput.value, 'class'));
    q.set('namespaceDepth', String(parsePositiveInt(namespaceDepthInput.value, 2)));
    q.set('minEdgeWeight', String(parsePositiveInt(minEdgeWeightInput.value, 1)));
    var maxNodes = parseOptionalPositiveInt(maxNodesInput.value); if (maxNodes !== null) { q.set('maxNodes', String(maxNodes)); }
    if (includeFunctionsInput.checked) { q.set('includeFunctions', '1'); }
    if (strictNamespacesInput.checked) { q.set('strictNamespaces', '1'); }
    var ns = parseNamespaces(namespacesInput.value); if (ns.length > 0) { q.set('ns', ns.join(',')); }
    window.location.search = q.toString();
  }

  document.getElementById('clearFocus').addEventListener('click', clearFocus);
})();
