@extends('layouts.user-profile-wide')

@section('subtitle', trans('app.family_tree'))

@section('user-content')
<div class="tree-viewport">
    <div id="wrapper" class="tree-diagram">
        @include('users.partials.tree-node', ['node' => $node, 'level' => 1, 'isRoot' => true])
    </div>
</div>
<div class="container tree-summary-strip">
<hr>
<div class="row">
    @if (!empty($generationCounts[1]))
    <div class="col-md-1 text-right">{{ trans('app.child_count') }}</div>
    <div class="col-md-1 text-left"><strong style="font-size:30px">{{ $generationCounts[1] }}</strong></div>
    @endif
    @if (!empty($generationCounts[2]))
    <div class="col-md-1 text-right">{{ trans('app.grand_child_count') }}</div>
    <div class="col-md-1 text-left"><strong style="font-size:30px">{{ $generationCounts[2] }}</strong></div>
    @endif
    @if (!empty($generationCounts[3]))
    <div class="col-md-1 text-right">Jumlah Cicit</div>
    <div class="col-md-1 text-left"><strong style="font-size:30px">{{ $generationCounts[3] }}</strong></div>
    @endif
    @if (!empty($generationCounts[4]))
    <div class="col-md-1 text-right">Jumlah Canggah</div>
    <div class="col-md-1 text-left"><strong style="font-size:30px">{{ $generationCounts[4] }}</strong></div>
    @endif
    @if (!empty($generationCounts[5]))
    <div class="col-md-1 text-right">Jumlah Wareng</div>
    <div class="col-md-1 text-left"><strong style="font-size:30px">{{ $generationCounts[5] }}</strong></div>
    @endif
    @if (!empty($generationCounts[6]))
    <div class="col-md-1 text-right">Jumlah Udheg2</div>
    <div class="col-md-1 text-left"><strong style="font-size:30px">{{ $generationCounts[6] }}</strong></div>
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

        function createPath(d, className) {
            var path = document.createElementNS(NS, 'path');
            path.setAttribute('d', d);
            path.setAttribute('class', className);
            return path;
        }

        function renderBranch(entry) {
            var branch = entry.querySelector(':scope > [data-tree-branch]');
            var card = entry.querySelector(':scope > [data-tree-card] [data-tree-box]');
            if (!branch || !card) return;

            var childEntries = Array.prototype.filter.call(branch.children, function (child) {
                return child.hasAttribute('data-tree-entry');
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

            svg.appendChild(createPath('M ' + parentX + ' ' + parentY + ' L ' + spineX + ' ' + parentY, 'tree-connector-outline'));
            svg.appendChild(createPath('M ' + parentX + ' ' + parentY + ' L ' + spineX + ' ' + parentY, 'tree-connector'));

            if (childRects.length > 1) {
                svg.appendChild(createPath('M ' + spineX + ' ' + minY + ' L ' + spineX + ' ' + maxY, 'tree-connector-outline'));
                svg.appendChild(createPath('M ' + spineX + ' ' + minY + ' L ' + spineX + ' ' + maxY, 'tree-connector'));
            }

            childRects.forEach(function (item) {
                svg.appendChild(createPath('M ' + spineX + ' ' + item.rect.centerY + ' L ' + item.rect.left + ' ' + item.rect.centerY, 'tree-connector-outline'));
                svg.appendChild(createPath('M ' + spineX + ' ' + item.rect.centerY + ' L ' + item.rect.left + ' ' + item.rect.centerY, 'tree-connector'));
                renderBranch(item.entry);
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
                    renderBranch(child);
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
