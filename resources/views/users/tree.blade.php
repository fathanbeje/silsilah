@extends('layouts.user-profile-wide')

@section('subtitle', trans('app.family_tree'))

@section('user-content')
<div class="tree-viewport">
    <div id="wrapper" class="tree-diagram">
        @include('users.partials.tree-node', ['node' => $node, 'level' => 1, 'isRoot' => true])
    </div>
</div>
@php
    $visibleGenerationStats = collect($generationStats ?? [])
        ->filter(function (array $stat) {
            return !empty($stat['kandung_count']) || !empty($stat['mantu_count']);
        });
@endphp
<div class="container tree-summary-strip">
    <hr>
    @if ($visibleGenerationStats->isNotEmpty())
    <div class="tree-summary-card">
        <div class="tree-summary-card__header">
            <h3 class="tree-summary-card__title">Statistik Keturunan</h3>
            <p class="tree-summary-card__lead">Rincian keturunan kandung dan mantu dari core per generasi.</p>
        </div>
        <div class="table-responsive">
            <table class="table table-striped tree-summary-table">
                <thead>
                    <tr>
                        <th>Generasi</th>
                        <th class="text-center">Kandung</th>
                        <th class="text-center">Mantu</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($visibleGenerationStats as $level => $stat)
                    <tr data-generation-level="{{ $level }}">
                        <td data-generation-label>{{ $stat['label'] }}</td>
                        <td class="text-center" data-generation-kandung>{{ $stat['kandung_count'] }}</td>
                        <td class="text-center" data-generation-mantu>{{ $stat['mantu_count'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
    @endif
</div>
@endsection

@section ('ext_css')
<link rel="stylesheet" href="{{ secure_asset('css/tree.css') }}">
<link rel="stylesheet" href="{{ secure_asset('css/family-display.css') }}">
@endsection

@section ('ext_js')
<script>
    (function () {
        var wrapper = document.getElementById('wrapper');
        if (!wrapper) return;

        var NS = 'http://www.w3.org/2000/svg';
        var svg = null;
        var renderQueued = false;
        var resizeObserver = null;
        var connectorPalette = ['#b7cde0', '#bed8cf', '#c8d2e8', '#d1d9c9', '#d8cde3', '#d4dde8'];

        function ensureSvg() {
            if (svg) return svg;

            svg = document.createElementNS(NS, 'svg');
            svg.setAttribute('class', 'tree-connectors');
            svg.setAttribute('aria-hidden', 'true');
            wrapper.insertBefore(svg, wrapper.firstChild);
            return svg;
        }

        function localRect(element) {
            var rect = element.getBoundingClientRect();
            var wrapperRect = wrapper.getBoundingClientRect();

            return {
                left: rect.left - wrapperRect.left,
                top: rect.top - wrapperRect.top,
                right: rect.right - wrapperRect.left,
                bottom: rect.bottom - wrapperRect.top,
                width: rect.width,
                height: rect.height,
                centerY: rect.top - wrapperRect.top + (rect.height / 2)
            };
        }

        function connectorColor(depth) {
            return connectorPalette[Math.max(0, depth - 1) % connectorPalette.length];
        }

        function createPath(d, className, stroke) {
            var path = document.createElementNS(NS, 'path');
            path.setAttribute('d', d);
            path.setAttribute('class', className);
            if (stroke) {
                path.setAttribute('stroke', stroke);
            }
            return path;
        }

        function renderBranch(entry, depth) {
            var branch = entry.querySelector(':scope > [data-tree-branch]');
            var card = entry.querySelector(':scope > [data-tree-card] [data-tree-box]');
            if (!branch || !card) return;
            if (branch.hidden || window.getComputedStyle(branch).display === 'none') return;

            var childEntries = Array.prototype.filter.call(branch.children, function (child) {
                return child.hasAttribute('data-tree-entry') && window.getComputedStyle(child).display !== 'none';
            });

            if (!childEntries.length) return;

            var parentRect = localRect(card);
            var parentX = parentRect.right;
            var parentY = parentRect.centerY;
            var childRects = childEntries.map(function (childEntry) {
                var childCard = childEntry.querySelector(':scope > [data-tree-card] [data-tree-box]');
                return {
                    entry: childEntry,
                    rect: localRect(childCard)
                };
            });

            var spineX = Math.min.apply(null, childRects.map(function (item) {
                return item.rect.left;
            })) - 30;
            var minY = Math.min.apply(null, childRects.map(function (item) {
                return item.rect.centerY;
            }));
            var maxY = Math.max.apply(null, childRects.map(function (item) {
                return item.rect.centerY;
            }));
            var stroke = connectorColor(depth);

            svg.appendChild(createPath('M ' + parentX + ' ' + parentY + ' L ' + spineX + ' ' + parentY, 'tree-connector-outline'));
            svg.appendChild(createPath('M ' + parentX + ' ' + parentY + ' L ' + spineX + ' ' + parentY, 'tree-connector', stroke));

            if (childRects.length > 1) {
                svg.appendChild(createPath('M ' + spineX + ' ' + minY + ' L ' + spineX + ' ' + maxY, 'tree-connector-outline'));
                svg.appendChild(createPath('M ' + spineX + ' ' + minY + ' L ' + spineX + ' ' + maxY, 'tree-connector', stroke));
            }

            childRects.forEach(function (item) {
                svg.appendChild(createPath('M ' + spineX + ' ' + item.rect.centerY + ' L ' + item.rect.left + ' ' + item.rect.centerY, 'tree-connector-outline'));
                svg.appendChild(createPath('M ' + spineX + ' ' + item.rect.centerY + ' L ' + item.rect.left + ' ' + item.rect.centerY, 'tree-connector', stroke));
                renderBranch(item.entry, depth + 1);
            });
        }

        function renderTreeConnectors() {
            var treeSvg = ensureSvg();
            var width = Math.ceil(wrapper.scrollWidth || wrapper.offsetWidth || 0);
            var height = Math.ceil(wrapper.scrollHeight || wrapper.offsetHeight || 0);

            treeSvg.setAttribute('width', width);
            treeSvg.setAttribute('height', height);
            treeSvg.setAttribute('viewBox', '0 0 ' + width + ' ' + height);
            treeSvg.innerHTML = '';

            wrapper.classList.add('tree-diagram--enhanced');

            Array.prototype.forEach.call(wrapper.children, function (child) {
                if (child.hasAttribute && child.hasAttribute('data-tree-entry')) {
                    renderBranch(child, 1);
                }
            });
        }

        function queueRender() {
            if (renderQueued) return;

            renderQueued = true;
            window.requestAnimationFrame(function () {
                renderQueued = false;
                renderTreeConnectors();
            });
        }

        function setExpanded(entry, expanded) {
            var branch = entry.querySelector(':scope > [data-tree-branch]');
            var box = entry.querySelector(':scope > [data-tree-card] [data-tree-box]');
            var suppressRender = arguments.length > 2 ? arguments[2] : false;

            if (!branch || !box || entry.classList.contains('entry-root')) return;

            entry.setAttribute('data-expanded', expanded ? 'true' : 'false');
            branch.hidden = !expanded;

            if (box.hasAttribute('aria-expanded')) {
                box.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            }

            if (!suppressRender) {
                queueRender();
            }
        }

        function setAllExpanded(expanded) {
            Array.prototype.forEach.call(
                wrapper.querySelectorAll('[data-tree-entry][data-has-children="true"]'),
                function (entry) {
                    if (entry.classList.contains('entry-root')) {
                        return;
                    }

                    setExpanded(entry, expanded, true);
                }
            );

            queueRender();
        }

        function toggleEntry(entry) {
            if (!entry || entry.getAttribute('data-has-children') !== 'true') return;
            if (entry.classList.contains('entry-root')) return;

            setExpanded(entry, entry.getAttribute('data-expanded') !== 'true');
        }

        wrapper.addEventListener('click', function (event) {
            var bulkAction = event.target.closest('[data-tree-bulk-action]');
            if (bulkAction) {
                event.preventDefault();
                setAllExpanded(bulkAction.getAttribute('data-tree-bulk-action') === 'expand');
                return;
            }

            if (event.target.closest('a')) return;

            var toggleBox = event.target.closest('[data-tree-toggle]');
            if (!toggleBox) return;

            toggleEntry(toggleBox.closest('[data-tree-entry]'));
        });

        wrapper.addEventListener('keydown', function (event) {
            if (event.key !== 'Enter' && event.key !== ' ') return;

            var toggleBox = event.target.closest('[data-tree-toggle]');
            if (!toggleBox) return;

            event.preventDefault();
            toggleEntry(toggleBox.closest('[data-tree-entry]'));
        });

        window.addEventListener('load', queueRender);
        window.addEventListener('resize', queueRender);

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(queueRender);
        } else {
            setTimeout(queueRender, 60);
        }

        if ('ResizeObserver' in window) {
            resizeObserver = new ResizeObserver(queueRender);
            resizeObserver.observe(wrapper);
        }

        queueRender();
    })();
</script>
@endsection
