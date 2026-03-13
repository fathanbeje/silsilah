@extends('layouts.user-profile-wide')

@section('subtitle', trans('app.family_tree'))

@section('user-content')
<div class="tree-tools" data-tree-tools>
    <button
        type="button"
        class="tree-tools__fab"
        data-tree-tools-toggle
        aria-controls="tree-tools-panel"
        aria-expanded="false"
        aria-label="Buka kontrol pohon"
        title="Kontrol pohon"
    >
        <span aria-hidden="true">&#9881;</span>
    </button>
    <button
        type="button"
        class="tree-tools__backdrop"
        data-tree-tools-backdrop
        aria-hidden="true"
        tabindex="-1"
        hidden
    ></button>
    <section
        id="tree-tools-panel"
        class="tree-tools__panel"
        data-tree-tools-panel
        aria-label="Kontrol pohon keluarga"
        hidden
    >
        <div class="tree-tools__head">
            <div class="tree-tools__head-copy">
                <span class="tree-tools__label">Kontrol Pohon</span>
                <span class="tree-tools__subtext">Atur cabang dan ukuran tampilan.</span>
            </div>
            <button
                type="button"
                class="tree-tools__close"
                data-tree-tools-close
                aria-label="Tutup kontrol pohon"
            >&times;</button>
        </div>
        <button
            type="button"
            class="tree-tools__bulk-toggle"
            data-tree-global-toggle
            data-tree-label-collapse="Collapse Semua"
            data-tree-label-expand="Expand Semua"
            data-tree-action="collapse"
        >Collapse Semua</button>
        <div class="tree-tools__zoom" data-tree-zoom-controls>
            <div class="tree-tools__zoom-head">
                <span class="tree-tools__zoom-label">Ukuran Konten</span>
            </div>
            <div class="tree-tools__stepper">
                <button type="button" class="tree-tools__step-btn" data-tree-zoom-out aria-label="Perkecil tampilan">&minus;</button>
                <span class="tree-tools__zoom-value" data-tree-zoom-value>100%</span>
                <button type="button" class="tree-tools__step-btn" data-tree-zoom-in aria-label="Perbesar tampilan">+</button>
            </div>
            <div class="tree-tools__presets">
                @foreach ([50, 75, 80, 85, 90, 95, 100, 110, 125] as $zoomPreset)
                <button
                    type="button"
                    class="tree-tools__preset {{ $zoomPreset === 100 ? 'is-active' : '' }}"
                    data-tree-zoom-preset
                    data-zoom="{{ $zoomPreset }}"
                >{{ $zoomPreset }}%</button>
                @endforeach
            </div>
            <button type="button" class="tree-tools__reset" data-tree-zoom-reset>Reset ke 100%</button>
        </div>
    </section>
</div>
<div class="tree-viewport">
    <div id="wrapper" class="tree-diagram" data-tree-root-id="{{ $user->id }}">
        @include('users.partials.tree-node', ['node' => $node, 'level' => 1, 'isRoot' => true])
    </div>
</div>
@php
    $visibleGenerationStats = collect($generationStats ?? [])
        ->filter(function (array $stat) {
            return !empty($stat['kandung_count']) || !empty($stat['mantu_count']);
        });
    $totalKandung = $visibleGenerationStats->sum('kandung_count');
    $totalMantu = $visibleGenerationStats->sum('mantu_count');
    $totalHidup = $visibleGenerationStats->sum('alive_count');
    $totalWafat = $visibleGenerationStats->sum('deceased_count');
    $totalKeturunan = $totalKandung + $totalMantu;
@endphp
<div class="container tree-summary-strip">
    <hr>
    @if ($visibleGenerationStats->isNotEmpty())
    <div class="tree-summary-card">
        <div class="tree-summary-card__header">
            <div class="tree-summary-card__headline">
                <h3 class="tree-summary-card__title">Statistik Keturunan {{ $user->display_name }}</h3>
                <p class="tree-summary-card__lead">Rincian keturunan kandung dan mantu per generasi</p>
            </div>
            <div class="tree-summary-card__core-badge" data-tree-summary-core>{{ $user->display_name }}</div>
        </div>
        <div class="table-responsive">
            <table class="table tree-summary-table">
                <thead>
                    <tr>
                        <th>Generasi</th>
                        <th class="text-center">Kandung</th>
                        <th class="text-center">Mantu</th>
                        <th class="text-center">Hidup</th>
                        <th class="text-center">Wafat</th>
                        <th class="text-center">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($visibleGenerationStats as $level => $stat)
                    <tr data-generation-level="{{ $level }}">
                        <td data-generation-label>{{ $stat['label'] }}</td>
                        <td class="text-center" data-generation-kandung>{{ $stat['kandung_count'] }}</td>
                        <td class="text-center" data-generation-mantu>{{ $stat['mantu_count'] }}</td>
                        <td class="text-center" data-generation-alive>{{ $stat['alive_count'] }}</td>
                        <td class="text-center" data-generation-deceased>{{ $stat['deceased_count'] }}</td>
                        <td class="text-center" data-generation-total>{{ $stat['member_total_count'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr data-generation-total-row>
                        <th>Total Semua</th>
                        <th class="text-center" data-total-kandung-row>{{ $totalKandung }}</th>
                        <th class="text-center" data-total-mantu-row>{{ $totalMantu }}</th>
                        <th class="text-center" data-total-hidup-row>{{ $totalHidup }}</th>
                        <th class="text-center" data-total-wafat-row>{{ $totalWafat }}</th>
                        <th class="text-center" data-total-keturunan-row>{{ $totalKeturunan }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="tree-summary-totals" data-tree-summary-totals>
            <div class="tree-summary-total-card tree-summary-total-card--kandung">
                <div class="tree-summary-total-card__label">Total Kandung</div>
                <div class="tree-summary-total-card__value" data-total-kandung>{{ $totalKandung }}</div>
                <div class="tree-summary-total-card__meta">Akumulasi seluruh generasi kandung</div>
            </div>
            <div class="tree-summary-total-card tree-summary-total-card--mantu">
                <div class="tree-summary-total-card__label">Total Mantu</div>
                <div class="tree-summary-total-card__value" data-total-mantu>{{ $totalMantu }}</div>
                <div class="tree-summary-total-card__meta">Pasangan unik yang tercatat per generasi</div>
            </div>
            <div class="tree-summary-total-card tree-summary-total-card--hidup">
                <div class="tree-summary-total-card__label">Total Hidup</div>
                <div class="tree-summary-total-card__value" data-total-hidup>{{ $totalHidup }}</div>
                <div class="tree-summary-total-card__meta">Anggota generasi yang masih hidup</div>
            </div>
            <div class="tree-summary-total-card tree-summary-total-card--wafat">
                <div class="tree-summary-total-card__label">Total Wafat</div>
                <div class="tree-summary-total-card__value" data-total-wafat>{{ $totalWafat }}</div>
                <div class="tree-summary-total-card__meta">Anggota generasi yang sudah wafat</div>
            </div>
            <div class="tree-summary-total-card tree-summary-total-card--grand">
                <div class="tree-summary-total-card__label">Jumlah Kandung + Mantu</div>
                <div class="tree-summary-total-card__value" data-total-keturunan>{{ $totalKeturunan }}</div>
                <div class="tree-summary-total-card__meta">Ringkasan total seluruh statistik keturunan</div>
            </div>
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
        var activePreview = null;
        var toolsToggleButton = document.querySelector('[data-tree-tools-toggle]');
        var toolsPanel = document.querySelector('[data-tree-tools-panel]');
        var toolsBackdrop = document.querySelector('[data-tree-tools-backdrop]');
        var toolsCloseButton = document.querySelector('[data-tree-tools-close]');
        var globalToggleButton = document.querySelector('[data-tree-global-toggle]');
        var zoomOutButton = document.querySelector('[data-tree-zoom-out]');
        var zoomInButton = document.querySelector('[data-tree-zoom-in]');
        var zoomValue = document.querySelector('[data-tree-zoom-value]');
        var zoomResetButton = document.querySelector('[data-tree-zoom-reset]');
        var zoomPresetButtons = Array.prototype.slice.call(document.querySelectorAll('[data-tree-zoom-preset]'));
        var ZOOM_MIN = 50;
        var ZOOM_MAX = 125;
        var ZOOM_STEP = 5;
        var DEFAULT_ZOOM = 100;
        var zoomStorageKey = 'family-tree-zoom:' + (wrapper.getAttribute('data-tree-root-id') || 'default');
        var currentZoom = DEFAULT_ZOOM;

        function clampZoom(zoom) {
            var parsedZoom = parseInt(zoom, 10);

            if (isNaN(parsedZoom)) {
                parsedZoom = DEFAULT_ZOOM;
            }

            return Math.min(ZOOM_MAX, Math.max(ZOOM_MIN, parsedZoom));
        }

        function connectorStemOffset() {
            return parseFloat(window.getComputedStyle(document.documentElement).getPropertyValue('--tree-stem-offset')) || 30;
        }

        function saveZoom(zoom) {
            try {
                window.localStorage.setItem(zoomStorageKey, String(clampZoom(zoom)));
            } catch (error) {}
        }

        function storedZoom() {
            try {
                return clampZoom(window.localStorage.getItem(zoomStorageKey));
            } catch (error) {
                return DEFAULT_ZOOM;
            }
        }

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
            })) - connectorStemOffset();
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

        function syncEntryHeights() {
            Array.prototype.forEach.call(
                wrapper.querySelectorAll('[data-tree-entry]'),
                function (entry) {
                    var card = entry.querySelector(':scope > [data-tree-card]');
                    var baseHeight = parseInt(entry.getAttribute('data-entry-base-height') || '64', 10);
                    var scaledBaseHeight = Math.ceil(baseHeight * (currentZoom / 100));
                    var verticalBuffer = Math.ceil(20 * (currentZoom / 100));

                    if (!card) {
                        entry.style.minHeight = scaledBaseHeight + 'px';
                        return;
                    }

                    var cardHeight = Math.ceil(card.offsetHeight || 0);
                    entry.style.minHeight = Math.max(scaledBaseHeight, cardHeight + verticalBuffer) + 'px';
                }
            );
        }

        function queueRender() {
            if (renderQueued) return;

            renderQueued = true;
            window.requestAnimationFrame(function () {
                renderQueued = false;
                syncEntryHeights();
                renderTreeConnectors();
                updateGlobalToggleState();
            });
        }

        function getExpandableEntries() {
            return Array.prototype.filter.call(
                wrapper.querySelectorAll('[data-tree-entry][data-has-children="true"]'),
                function (entry) {
                    return !entry.classList.contains('entry-root');
                }
            );
        }

        function allEntriesExpanded() {
            var entries = getExpandableEntries();

            return entries.length > 0 && entries.every(function (entry) {
                return entry.getAttribute('data-expanded') === 'true';
            });
        }

        function updateGlobalToggleState() {
            if (!globalToggleButton) return;

            var entries = getExpandableEntries();
            var nextAction = allEntriesExpanded() ? 'collapse' : 'expand';
            var labelAttribute = nextAction === 'collapse' ? 'data-tree-label-collapse' : 'data-tree-label-expand';

            globalToggleButton.textContent = globalToggleButton.getAttribute(labelAttribute) || '';
            globalToggleButton.setAttribute('data-tree-action', nextAction);
            globalToggleButton.disabled = entries.length === 0;
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

        function syncZoomUi() {
            if (zoomValue) {
                zoomValue.textContent = currentZoom + '%';
            }

            if (zoomOutButton) {
                zoomOutButton.disabled = currentZoom <= ZOOM_MIN;
            }

            if (zoomInButton) {
                zoomInButton.disabled = currentZoom >= ZOOM_MAX;
            }

            zoomPresetButtons.forEach(function (button) {
                button.classList.toggle('is-active', Number(button.getAttribute('data-zoom')) === currentZoom);
            });
        }

        function setZoom(zoom) {
            currentZoom = clampZoom(zoom);
            document.documentElement.style.setProperty('--tree-scale', String(currentZoom / 100));
            wrapper.setAttribute('data-tree-scale', String(currentZoom));
            syncZoomUi();
            saveZoom(currentZoom);
            queueRender();
        }

        function closeToolsPanel() {
            if (!toolsPanel) return;

            toolsPanel.hidden = true;

            if (toolsBackdrop) {
                toolsBackdrop.hidden = true;
            }

            if (toolsToggleButton) {
                toolsToggleButton.setAttribute('aria-expanded', 'false');
            }
        }

        function toggleToolsPanel() {
            if (!toolsPanel) return;

            var shouldOpen = toolsPanel.hidden;
            toolsPanel.hidden = !shouldOpen;

            if (toolsBackdrop) {
                toolsBackdrop.hidden = !shouldOpen;
            }

            if (toolsToggleButton) {
                toolsToggleButton.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');
            }
        }

        function closePreview(preview) {
            if (!preview) return;

            preview.classList.remove('is-open');

            var trigger = preview.querySelector('[data-tree-preview-trigger]');
            var popup = preview.querySelector('[data-tree-preview-popup]');

            if (trigger) {
                trigger.setAttribute('aria-expanded', 'false');
            }

            if (popup) {
                popup.setAttribute('aria-hidden', 'true');
            }
        }

        function openPreview(preview) {
            if (!preview) return;
            if (activePreview && activePreview !== preview) {
                closePreview(activePreview);
            }

            preview.classList.add('is-open');

            var trigger = preview.querySelector('[data-tree-preview-trigger]');
            var popup = preview.querySelector('[data-tree-preview-popup]');

            if (trigger) {
                trigger.setAttribute('aria-expanded', 'true');
            }

            if (popup) {
                popup.setAttribute('aria-hidden', 'false');
            }

            activePreview = preview;
        }

        function togglePreview(preview) {
            if (!preview) return;

            if (preview.classList.contains('is-open')) {
                closePreview(preview);
                if (activePreview === preview) {
                    activePreview = null;
                }
                return;
            }

            openPreview(preview);
        }

        function closeAllPreviews() {
            Array.prototype.forEach.call(
                wrapper.querySelectorAll('[data-tree-preview].is-open'),
                function (preview) {
                    closePreview(preview);
                }
            );

            activePreview = null;
        }

        wrapper.addEventListener('click', function (event) {
            var previewTrigger = event.target.closest('[data-tree-preview-trigger]');
            if (previewTrigger) {
                event.preventDefault();
                event.stopPropagation();
                togglePreview(previewTrigger.closest('[data-tree-preview]'));
                return;
            }

            if (!event.target.closest('[data-tree-preview]')) {
                closeAllPreviews();
            }

            if (event.target.closest('[data-tree-preview]')) {
                return;
            }

            if (event.target.closest('a')) return;

            var toggleBox = event.target.closest('[data-tree-toggle]');
            if (!toggleBox) return;

            toggleEntry(toggleBox.closest('[data-tree-entry]'));
        });

        wrapper.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeAllPreviews();
                return;
            }

            if (event.key !== 'Enter' && event.key !== ' ') return;

            var previewTrigger = event.target.closest('[data-tree-preview-trigger]');
            if (previewTrigger) {
                event.preventDefault();
                togglePreview(previewTrigger.closest('[data-tree-preview]'));
                return;
            }

            var toggleBox = event.target.closest('[data-tree-toggle]');
            if (!toggleBox) return;

            event.preventDefault();
            toggleEntry(toggleBox.closest('[data-tree-entry]'));
        });

        document.addEventListener('click', function (event) {
            if (!event.target.closest('[data-tree-preview]')) {
                closeAllPreviews();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeToolsPanel();
                closeAllPreviews();
            }
        });

        if (globalToggleButton) {
            globalToggleButton.addEventListener('click', function () {
                setAllExpanded(globalToggleButton.getAttribute('data-tree-action') !== 'collapse');
            });
        }

        if (toolsToggleButton) {
            toolsToggleButton.addEventListener('click', function (event) {
                event.preventDefault();
                toggleToolsPanel();
            });
        }

        if (toolsBackdrop) {
            toolsBackdrop.addEventListener('click', closeToolsPanel);
        }

        if (toolsCloseButton) {
            toolsCloseButton.addEventListener('click', closeToolsPanel);
        }

        if (toolsPanel) {
            toolsPanel.addEventListener('click', function (event) {
                event.stopPropagation();
            });
        }

        if (zoomOutButton) {
            zoomOutButton.addEventListener('click', function () {
                setZoom(currentZoom - ZOOM_STEP);
            });
        }

        if (zoomInButton) {
            zoomInButton.addEventListener('click', function () {
                setZoom(currentZoom + ZOOM_STEP);
            });
        }

        if (zoomResetButton) {
            zoomResetButton.addEventListener('click', function () {
                setZoom(DEFAULT_ZOOM);
            });
        }

        zoomPresetButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                setZoom(button.getAttribute('data-zoom'));
            });
        });

        window.addEventListener('load', queueRender);
        window.addEventListener('resize', queueRender);

        if (document.fonts && document.fonts.ready) {
            document.fonts.ready.then(function () {
                queueRender();
            });
        } else {
            setTimeout(function () {
                queueRender();
            }, 60);
        }

        if ('ResizeObserver' in window) {
            resizeObserver = new ResizeObserver(queueRender);
            resizeObserver.observe(wrapper);
            Array.prototype.forEach.call(wrapper.querySelectorAll('[data-tree-card] img'), function (image) {
                resizeObserver.observe(image);
            });
        }

        setZoom(storedZoom());
        queueRender();
    })();
</script>
@endsection
