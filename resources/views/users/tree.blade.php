@extends('layouts.user-profile-wide')

@section('subtitle', trans('app.family_tree'))

@section('user-content')
@php
    $hotlinkChildren = collect($node['children'] ?? [])->map(function (array $childNode) {
        $childUser = $childNode['user'];
        $spouseCount = collect($childNode['spouse_labels'] ?? [])->count();

        return [
            'id' => $childNode['node_id'],
            'label' => $childUser->display_name,
            'meta' => trim(collect([
                $childNode['children']->count() > 0 ? $childNode['children']->count().' turunan' : null,
                $spouseCount > 0 ? $spouseCount.' pasangan' : null,
            ])->filter()->implode(' · ')),
        ];
    })->values();
@endphp
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
        <span class="tree-tools__fab-glow" aria-hidden="true"></span>
        <span class="tree-tools__fab-icon" aria-hidden="true">&#9881;</span>
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
                <span class="tree-tools__eyebrow">Mode Interaktif</span>
                <span class="tree-tools__label">Kontrol Pohon</span>
                <span class="tree-tools__subtext">Expand semua, ubah skala, lalu pan layar dengan drag.</span>
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
        >
            <span class="tree-tools__bulk-toggle-text">Collapse Semua</span>
            <span class="tree-tools__bulk-toggle-meta">Buka semua cabang lalu pan layar dengan drag.</span>
        </button>
        <div class="tree-tools__zoom" data-tree-zoom-controls>
            <div class="tree-tools__zoom-head">
                <span class="tree-tools__zoom-label">Ukuran Konten</span>
                <span class="tree-tools__zoom-hint">Jaga kenyamanan saat pohon dibuka penuh.</span>
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
@if ($hotlinkChildren->isNotEmpty())
<div class="tree-hotlink" data-tree-hotlink>
    <button
        type="button"
        class="tree-hotlink__fab"
        data-tree-hotlink-toggle
        aria-controls="tree-hotlink-panel"
        aria-expanded="false"
        aria-label="Loncat ke anak root"
        title="Loncat ke anak root"
    >
        <span class="tree-hotlink__fab-icon" aria-hidden="true">&#9906;</span>
        <span class="tree-hotlink__fab-tooltip" data-tree-hotlink-tooltip>Loncat ke anak root</span>
    </button>
    <section
        id="tree-hotlink-panel"
        class="tree-hotlink__panel"
        data-tree-hotlink-panel
        aria-label="Pilih anak dari root aktif"
        hidden
    >
        <div class="tree-hotlink__panel-head">
            <div>
                <div class="tree-hotlink__eyebrow">Hotlink Cabang</div>
                <div class="tree-hotlink__title">Pilih anak dari {{ $user->display_name }}</div>
            </div>
            <button type="button" class="tree-hotlink__close" data-tree-hotlink-close aria-label="Tutup hotlink">&times;</button>
        </div>
        <label class="sr-only" for="tree-hotlink-search">Cari anak root</label>
        <input
            id="tree-hotlink-search"
            type="search"
            class="tree-hotlink__search"
            data-tree-hotlink-input
            placeholder="Cari anak {{ $user->display_name }}"
            autocomplete="off"
        >
        <div class="tree-hotlink__list" data-tree-hotlink-list>
            @foreach ($hotlinkChildren as $hotlinkChild)
            <button
                type="button"
                class="tree-hotlink__option"
                data-tree-hotlink-option
                data-target-node-id="{{ $hotlinkChild['id'] }}"
                data-search-label="{{ \Illuminate\Support\Str::lower($hotlinkChild['label']) }}"
            >
                <span class="tree-hotlink__option-name">{{ $hotlinkChild['label'] }}</span>
                @if ($hotlinkChild['meta'] !== '')
                <span class="tree-hotlink__option-meta">{{ $hotlinkChild['meta'] }}</span>
                @endif
            </button>
            @endforeach
        </div>
        <div class="tree-hotlink__empty" data-tree-hotlink-empty hidden>Nama anak root tidak ditemukan.</div>
    </section>
</div>
@endif
<div class="tree-stage" data-tree-stage>
    <div class="tree-viewport" data-tree-viewport data-tree-drag-surface data-drag-enabled="false">
        <div id="wrapper" class="tree-diagram" data-tree-root-id="{{ $user->id }}">
            @include('users.partials.tree-node', ['node' => $node, 'level' => 1, 'isRoot' => true])
        </div>
    </div>
</div>
@php
    $visibleGenerationStats = collect($generationStats ?? [])
        ->filter(function (array $stat) {
            return !empty($stat['kandung_count']) || !empty($stat['mantu_count']);
        });
    $totalKandung = $visibleGenerationStats->sum('kandung_count');
    $totalMantu = $visibleGenerationStats->sum('mantu_count');
    $totalKandungHidup = $visibleGenerationStats->sum('kandung_alive_count');
    $totalKandungWafat = $visibleGenerationStats->sum('kandung_deceased_count');
    $totalMantuHidup = $visibleGenerationStats->sum('mantu_alive_count');
    $totalMantuWafat = $visibleGenerationStats->sum('mantu_deceased_count');
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
                        <th class="text-center">Total</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($visibleGenerationStats as $level => $stat)
                    <tr data-generation-level="{{ $level }}">
                        <td data-generation-label>{{ $stat['label'] }}</td>
                        <td class="text-center" data-generation-kandung>
                            <span class="tree-summary-stat">
                                <span class="tree-summary-stat__total">{{ $stat['kandung_count'] }}</span>
                                <span class="tree-summary-stat__breakdown">
                                    <span class="tree-summary-stat__chip tree-summary-stat__chip--alive" data-generation-kandung-alive>H {{ $stat['kandung_alive_count'] }}</span>
                                    <span class="tree-summary-stat__chip tree-summary-stat__chip--deceased" data-generation-kandung-deceased>W {{ $stat['kandung_deceased_count'] }}</span>
                                </span>
                            </span>
                        </td>
                        <td class="text-center" data-generation-mantu>
                            <span class="tree-summary-stat">
                                <span class="tree-summary-stat__total">{{ $stat['mantu_count'] }}</span>
                                <span class="tree-summary-stat__breakdown">
                                    <span class="tree-summary-stat__chip tree-summary-stat__chip--alive" data-generation-mantu-alive>H {{ $stat['mantu_alive_count'] }}</span>
                                    <span class="tree-summary-stat__chip tree-summary-stat__chip--deceased" data-generation-mantu-deceased>W {{ $stat['mantu_deceased_count'] }}</span>
                                </span>
                            </span>
                        </td>
                        <td class="text-center" data-generation-total>{{ $stat['member_total_count'] }}</td>
                    </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr data-generation-total-row>
                        <th>Total Semua</th>
                        <th class="text-center" data-total-kandung-row>
                            <span class="tree-summary-stat tree-summary-stat--footer">
                                <span class="tree-summary-stat__total">{{ $totalKandung }}</span>
                                <span class="tree-summary-stat__breakdown">
                                    <span class="tree-summary-stat__chip tree-summary-stat__chip--alive" data-total-kandung-alive-row>H {{ $totalKandungHidup }}</span>
                                    <span class="tree-summary-stat__chip tree-summary-stat__chip--deceased" data-total-kandung-deceased-row>W {{ $totalKandungWafat }}</span>
                                </span>
                            </span>
                        </th>
                        <th class="text-center" data-total-mantu-row>
                            <span class="tree-summary-stat tree-summary-stat--footer">
                                <span class="tree-summary-stat__total">{{ $totalMantu }}</span>
                                <span class="tree-summary-stat__breakdown">
                                    <span class="tree-summary-stat__chip tree-summary-stat__chip--alive" data-total-mantu-alive-row>H {{ $totalMantuHidup }}</span>
                                    <span class="tree-summary-stat__chip tree-summary-stat__chip--deceased" data-total-mantu-deceased-row>W {{ $totalMantuWafat }}</span>
                                </span>
                            </span>
                        </th>
                        <th class="text-center" data-total-keturunan-row>{{ $totalKeturunan }}</th>
                    </tr>
                </tfoot>
            </table>
        </div>
        <div class="tree-summary-totals" data-tree-summary-totals>
            <div class="tree-summary-total-card tree-summary-total-card--kandung">
                <div class="tree-summary-total-card__label">Total Kandung</div>
                <div class="tree-summary-total-card__value" data-total-kandung>{{ $totalKandung }}</div>
                <div class="tree-summary-total-card__meta">H {{ $totalKandungHidup }} · W {{ $totalKandungWafat }}</div>
            </div>
            <div class="tree-summary-total-card tree-summary-total-card--mantu">
                <div class="tree-summary-total-card__label">Total Mantu</div>
                <div class="tree-summary-total-card__value" data-total-mantu>{{ $totalMantu }}</div>
                <div class="tree-summary-total-card__meta">H {{ $totalMantuHidup }} · W {{ $totalMantuWafat }}</div>
            </div>
            <div class="tree-summary-total-card tree-summary-total-card--hidup">
                <div class="tree-summary-total-card__label">Total Hidup</div>
                <div class="tree-summary-total-card__value" data-total-hidup>{{ $totalHidup }}</div>
                <div class="tree-summary-total-card__meta">Akumulasi kandung dan mantu yang masih hidup</div>
            </div>
            <div class="tree-summary-total-card tree-summary-total-card--wafat">
                <div class="tree-summary-total-card__label">Total Wafat</div>
                <div class="tree-summary-total-card__value" data-total-wafat>{{ $totalWafat }}</div>
                <div class="tree-summary-total-card__meta">Akumulasi kandung dan mantu yang sudah wafat</div>
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
        var treeStage = document.querySelector('[data-tree-stage]');
        var treeViewport = document.querySelector('[data-tree-viewport]');
        var wrapper = document.getElementById('wrapper');
        if (!wrapper || !treeViewport || !treeStage) return;

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
        var globalToggleText = globalToggleButton ? globalToggleButton.querySelector('.tree-tools__bulk-toggle-text') : null;
        var hotlink = document.querySelector('[data-tree-hotlink]');
        var hotlinkToggleButton = document.querySelector('[data-tree-hotlink-toggle]');
        var hotlinkPanel = document.querySelector('[data-tree-hotlink-panel]');
        var hotlinkCloseButton = document.querySelector('[data-tree-hotlink-close]');
        var hotlinkInput = document.querySelector('[data-tree-hotlink-input]');
        var hotlinkOptions = Array.prototype.slice.call(document.querySelectorAll('[data-tree-hotlink-option]'));
        var hotlinkEmptyState = document.querySelector('[data-tree-hotlink-empty]');
        var zoomOutButton = document.querySelector('[data-tree-zoom-out]');
        var zoomInButton = document.querySelector('[data-tree-zoom-in]');
        var zoomValue = document.querySelector('[data-tree-zoom-value]');
        var zoomResetButton = document.querySelector('[data-tree-zoom-reset]');
        var zoomPresetButtons = Array.prototype.slice.call(document.querySelectorAll('[data-tree-zoom-preset]'));
        var ZOOM_MIN = 50;
        var ZOOM_MAX = 125;
        var ZOOM_STEP = 5;
        var DEFAULT_ZOOM = 100;
        var DRAG_THRESHOLD = 8;
        var zoomStorageKey = 'family-tree-zoom:' + (wrapper.getAttribute('data-tree-root-id') || 'default');
        var currentZoom = DEFAULT_ZOOM;
        var dragState = {
            active: false,
            pointerId: null,
            startX: 0,
            startY: 0,
            startScrollLeft: 0,
            startWindowY: 0,
            didDrag: false,
            suppressClick: false
        };

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

        function isDragTargetInteractive(target) {
            return !!target.closest('a, button, input, [data-tree-card], [data-tree-preview], [data-tree-tools], [data-tree-hotlink]');
        }

        function releaseDragState() {
            if (dragState.active && dragState.pointerId !== null && treeViewport.releasePointerCapture) {
                try {
                    treeViewport.releasePointerCapture(dragState.pointerId);
                } catch (error) {}
            }

            dragState.active = false;
            dragState.pointerId = null;
            treeViewport.classList.remove('is-drag-armed', 'is-dragging');
            document.body.classList.remove('tree-dragging');
        }

        function onPointerDown(event) {
            if (event.button !== 0 || event.pointerType === 'touch' || !allEntriesExpanded() || isDragTargetInteractive(event.target)) {
                return;
            }

            dragState.active = true;
            dragState.pointerId = event.pointerId;
            dragState.startX = event.clientX;
            dragState.startY = event.clientY;
            dragState.startScrollLeft = treeViewport.scrollLeft;
            dragState.startWindowY = window.scrollY;
            dragState.didDrag = false;

            treeViewport.classList.add('is-drag-armed');

            if (treeViewport.setPointerCapture) {
                try {
                    treeViewport.setPointerCapture(event.pointerId);
                } catch (error) {}
            }
        }

        function onPointerMove(event) {
            if (!dragState.active || dragState.pointerId !== event.pointerId) return;

            var deltaX = event.clientX - dragState.startX;
            var deltaY = event.clientY - dragState.startY;

            if (!dragState.didDrag && Math.max(Math.abs(deltaX), Math.abs(deltaY)) < DRAG_THRESHOLD) {
                return;
            }

            if (!dragState.didDrag) {
                dragState.didDrag = true;
                treeViewport.classList.add('is-dragging');
                document.body.classList.add('tree-dragging');
            }

            event.preventDefault();

            treeViewport.scrollLeft = dragState.startScrollLeft - deltaX;
            window.scrollTo({
                top: Math.max(0, dragState.startWindowY - deltaY),
                behavior: 'auto'
            });
        }

        function onPointerUp(event) {
            if (!dragState.active || dragState.pointerId !== event.pointerId) return;

            dragState.suppressClick = dragState.didDrag;
            releaseDragState();
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
            var label = globalToggleButton.getAttribute(labelAttribute) || '';
            var expanded = nextAction === 'collapse';

            if (globalToggleText) {
                globalToggleText.textContent = label;
            } else {
                globalToggleButton.textContent = label;
            }

            treeStage.classList.toggle('is-expanded-all', expanded);
            treeViewport.setAttribute('data-drag-enabled', expanded ? 'true' : 'false');
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

        function setHotlinkPanelState(shouldOpen) {
            if (!hotlinkPanel || !hotlinkToggleButton) return;

            hotlinkPanel.hidden = !shouldOpen;
            hotlinkToggleButton.setAttribute('aria-expanded', shouldOpen ? 'true' : 'false');

            if (!shouldOpen && hotlinkInput) {
                hotlinkInput.value = '';
                filterHotlinkOptions('');
            }
        }

        function closeHotlinkPanel() {
            setHotlinkPanelState(false);
        }

        function toggleHotlinkPanel() {
            if (!hotlinkPanel) return;

            var shouldOpen = hotlinkPanel.hidden;
            if (shouldOpen) {
                closeToolsPanel();
            }
            setHotlinkPanelState(shouldOpen);

            if (shouldOpen && hotlinkInput) {
                window.requestAnimationFrame(function () {
                    hotlinkInput.focus();
                    hotlinkInput.select();
                });
            }
        }

        function filterHotlinkOptions(query) {
            var normalizedQuery = (query || '').toLowerCase().trim();
            var visibleCount = 0;

            hotlinkOptions.forEach(function (option) {
                var label = (option.getAttribute('data-search-label') || '').toLowerCase();
                var matches = normalizedQuery === '' || label.indexOf(normalizedQuery) !== -1;
                option.hidden = !matches;

                if (matches) {
                    visibleCount += 1;
                }
            });

            if (hotlinkEmptyState) {
                hotlinkEmptyState.hidden = visibleCount > 0;
            }
        }

        function pulseTarget(entry) {
            if (!entry) return;

            entry.classList.remove('is-hotlink-target');
            window.requestAnimationFrame(function () {
                entry.classList.add('is-hotlink-target');
                window.setTimeout(function () {
                    entry.classList.remove('is-hotlink-target');
                }, 1800);
            });
        }

        function focusNodeById(nodeId) {
            if (!nodeId) return;

            var entry = wrapper.querySelector('[data-tree-entry][data-node-id="' + nodeId + '"]');
            if (!entry) return;

            var card = entry.querySelector(':scope > [data-tree-card] [data-tree-box]');
            if (!card) return;

            var cardRect = card.getBoundingClientRect();
            var viewportRect = treeViewport.getBoundingClientRect();
            var targetLeft = treeViewport.scrollLeft + (cardRect.left - viewportRect.left) - Math.max(18, ((treeViewport.clientWidth - cardRect.width) / 2));
            var targetTop = window.scrollY + cardRect.top - Math.max(110, (window.innerHeight * 0.24));

            treeViewport.scrollTo({
                left: Math.max(0, targetLeft),
                behavior: 'smooth'
            });
            window.scrollTo({
                top: Math.max(0, targetTop),
                behavior: 'smooth'
            });

            pulseTarget(entry);
            closeHotlinkPanel();
            closeAllPreviews();
        }

        function toggleToolsPanel() {
            if (!toolsPanel) return;

            var shouldOpen = toolsPanel.hidden;
            if (shouldOpen) {
                closeHotlinkPanel();
            }
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

            if (dragState.suppressClick) {
                event.preventDefault();
                event.stopPropagation();
                dragState.suppressClick = false;
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
                closeHotlinkPanel();
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

        if (hotlinkToggleButton) {
            hotlinkToggleButton.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();
                toggleHotlinkPanel();
            });
        }

        if (hotlinkCloseButton) {
            hotlinkCloseButton.addEventListener('click', function (event) {
                event.preventDefault();
                closeHotlinkPanel();
            });
        }

        if (hotlinkPanel) {
            hotlinkPanel.addEventListener('click', function (event) {
                event.stopPropagation();
            });
        }

        if (hotlinkInput) {
            hotlinkInput.addEventListener('input', function () {
                filterHotlinkOptions(hotlinkInput.value);
            });
        }

        hotlinkOptions.forEach(function (option) {
            option.addEventListener('click', function () {
                focusNodeById(option.getAttribute('data-target-node-id'));
            });
        });

        document.addEventListener('click', function (event) {
            if (hotlink && !event.target.closest('[data-tree-hotlink]')) {
                closeHotlinkPanel();
            }
        });

        treeViewport.addEventListener('pointerdown', onPointerDown);
        treeViewport.addEventListener('click', function (event) {
            if (!dragState.suppressClick) return;

            event.preventDefault();
            event.stopPropagation();
            dragState.suppressClick = false;
        }, true);
        window.addEventListener('pointermove', onPointerMove, { passive: false });
        window.addEventListener('pointerup', onPointerUp);
        window.addEventListener('pointercancel', onPointerUp);

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
            resizeObserver.observe(treeViewport);
            Array.prototype.forEach.call(wrapper.querySelectorAll('[data-tree-card] img'), function (image) {
                resizeObserver.observe(image);
            });
        }

        setZoom(storedZoom());
        filterHotlinkOptions('');
        queueRender();
    })();
</script>
@endsection
