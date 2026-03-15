@extends('layouts.app')

@section('ext_css')
<style>
    .death-index-shell {
        margin-top: -8px;
    }

    .death-index-hero {
        margin-bottom: 24px;
        padding: 24px 24px 18px;
        border-radius: 28px;
        background:
            radial-gradient(circle at top right, rgba(212, 175, 55, 0.18), transparent 26%),
            linear-gradient(135deg, #f9f5ea 0%, #eef4f6 54%, #f7f9fb 100%);
        border: 1px solid #e5e2d8;
        box-shadow: 0 18px 38px rgba(31, 42, 42, 0.08);
    }

    .death-index-hero__top {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 16px;
        margin-bottom: 14px;
    }

    .death-index-hero__title {
        margin: 0;
        font-size: 30px;
        line-height: 1.15;
        color: #1f2a2a;
    }

    .death-index-hero__lead {
        margin: 0;
        max-width: 840px;
        font-size: 15px;
        line-height: 1.7;
        color: #5c6969;
    }

    .death-index-hero__badge {
        display: inline-flex;
        align-items: center;
        padding: 10px 16px;
        border-radius: 999px;
        background: #1f4462;
        color: #fff;
        font-weight: 700;
        box-shadow: 0 14px 26px rgba(31, 68, 98, 0.18);
        white-space: nowrap;
    }

    .death-index-tabs {
        display: inline-flex;
        gap: 8px;
        margin-top: 18px;
        flex-wrap: wrap;
        padding: 6px;
        border-radius: 22px;
        border: 1px solid rgba(217, 224, 228, 0.9);
        background: rgba(255, 255, 255, 0.62);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
    }

    .death-index-tabs__link {
        position: relative;
        display: inline-flex;
        flex-direction: column;
        align-items: flex-start;
        gap: 3px;
        min-width: 170px;
        padding: 12px 16px 11px;
        border-radius: 16px;
        border: 1px solid transparent;
        background: rgba(255, 255, 255, 0.72);
        color: #244152;
        text-decoration: none;
        font-weight: 700;
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.55);
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease, border-color 0.18s ease;
    }

    .death-index-tabs__label {
        display: block;
        font-size: 14px;
        line-height: 1.2;
    }

    .death-index-tabs__meta {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        color: #6d7f8d;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.05em;
        line-height: 1.2;
        text-transform: uppercase;
    }

    .death-index-tabs__meta::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 999px;
        background: currentColor;
        opacity: 0.55;
    }

    .death-index-tabs__link:hover,
    .death-index-tabs__link:focus {
        color: #244152;
        text-decoration: none;
        transform: translateY(-1px);
        border-color: #d6e1e8;
        box-shadow: 0 10px 20px rgba(36, 65, 82, 0.08);
    }

    .death-index-tabs__link.is-active {
        background: linear-gradient(135deg, #244152 0%, #30586d 100%);
        border-color: #244152;
        color: #fff;
        box-shadow: 0 14px 28px rgba(36, 65, 82, 0.2);
    }

    .death-index-tabs__link.is-active::after {
        content: '';
        position: absolute;
        left: 16px;
        right: 16px;
        bottom: 6px;
        height: 3px;
        border-radius: 999px;
        background: rgba(255, 255, 255, 0.72);
    }

    .death-index-tabs__link.is-active .death-index-tabs__meta {
        color: rgba(255, 255, 255, 0.82);
    }

    .death-index-card {
        margin-bottom: 16px;
        overflow: visible;
        border-radius: 22px;
        border: 1px solid #e1e8ed;
        background: #fff;
        box-shadow: 0 14px 30px rgba(31, 42, 42, 0.05);
    }

    .death-index-card__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 15px 18px;
        background: linear-gradient(180deg, #fcfdfe 0%, #f4f8fb 100%);
        border-bottom: 1px solid #e6edf2;
    }

    .death-index-card__title {
        margin: 0;
        font-size: 18px;
        letter-spacing: 0.01em;
        color: #223444;
    }

    .death-index-card__count {
        display: inline-flex;
        align-items: center;
        padding: 7px 11px;
        border-radius: 999px;
        background: #edf4f9;
        color: #35556d;
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .death-index-results {
        scroll-margin-top: 16px;
    }

    .death-index-table-wrap {
        position: relative;
        overflow-x: auto;
        overflow-y: visible;
        border-radius: 0 0 22px 22px;
        cursor: grab;
    }

    .death-index-table-wrap.is-drag-armed,
    .death-index-table-wrap.is-dragging {
        cursor: grabbing;
    }

    .death-index-table-wrap.is-dragging,
    .death-index-table-wrap.is-dragging * {
        user-select: none;
    }

    .death-index-table {
        width: 100%;
        min-width: 1060px;
        border-collapse: separate;
        border-spacing: 0;
    }

    .death-index-table th,
    .death-index-table td {
        padding: 11px 14px;
        border-bottom: 1px solid #ebeff2;
        vertical-align: top;
    }

    .death-index-table thead th {
        background: rgba(248, 250, 252, 0.96);
        backdrop-filter: blur(10px);
        color: #375064;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        white-space: nowrap;
        box-shadow: inset 0 -1px 0 #dde7ee, 0 8px 18px rgba(31, 42, 42, 0.05);
    }

    .death-index-table thead th:first-child {
        border-top-left-radius: 12px;
    }

    .death-index-table thead th:last-child {
        border-top-right-radius: 12px;
    }

    .death-index-floating-head {
        position: fixed;
        left: 0;
        top: 0;
        z-index: 1100;
        display: none;
        pointer-events: none;
    }

    .death-index-floating-head.is-visible {
        display: block;
    }

    .death-index-floating-head__viewport {
        overflow: hidden;
        border: 1px solid #dfe8ee;
        border-radius: 12px 12px 0 0;
        background: rgba(248, 250, 252, 0.98);
        box-shadow: 0 12px 24px rgba(31, 42, 42, 0.1);
        backdrop-filter: blur(12px);
    }

    .death-index-floating-head table {
        margin: 0;
        border-collapse: separate;
        border-spacing: 0;
    }

    .death-index-floating-head th {
        padding: 11px 14px;
        color: #375064;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        white-space: nowrap;
        background: rgba(248, 250, 252, 0.98);
        box-shadow: inset 0 -1px 0 #dde7ee;
    }

    .death-index-floating-head th:first-child {
        border-top-left-radius: 12px;
    }

    .death-index-floating-head th:last-child {
        border-top-right-radius: 12px;
    }

    .death-index-table td::before {
        content: none;
    }

    .death-index-table tbody tr:nth-child(even) td {
        background: #fcfdfe;
    }

    .death-index-table tbody tr[data-death-row]:hover td {
        background: #f7fbfd;
    }

    .death-index-table__group td {
        padding: 12px 14px;
        background: #eef5fa !important;
        border-bottom-color: #dbe5ee;
    }

    .death-index-table__group-label {
        font-size: 16px;
        font-weight: 700;
        color: #234056;
    }

    .death-index-table__group-count {
        margin-left: 10px;
        color: #678196;
        font-size: 12px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.05em;
    }

    .death-index-name {
        font-weight: 700;
        letter-spacing: 0.01em;
        color: #213a4e;
    }

    .death-index-chip {
        display: inline-flex;
        align-items: center;
        padding: 6px 10px;
        border-radius: 999px;
        font-size: 12px;
        font-weight: 700;
        line-height: 1;
        white-space: nowrap;
    }

    .death-index-chip--blood {
        background: #e4f2e8;
        color: #2d6b44;
    }

    .death-index-chip--spouse {
        background: #f4e9cf;
        color: #7b5b12;
    }

    .death-index-chip--muted {
        background: #eef2f6;
        color: #587086;
    }

    .death-index-empty {
        padding: 34px 28px;
        text-align: center;
        color: #6b7777;
    }

    .death-index-empty h3 {
        margin: 0 0 8px;
        color: #263642;
    }

    @media (max-width: 767px) {
        .death-index-shell {
            margin-left: -4px;
            margin-right: -4px;
        }

        .death-index-hero {
            margin-bottom: 18px;
            padding: 16px 14px 14px;
            border-radius: 20px;
        }

        .death-index-hero__top {
            display: block;
            margin-bottom: 10px;
        }

        .death-index-hero__badge {
            margin-top: 12px;
            padding: 8px 12px;
            font-size: 12px;
        }

        .death-index-hero__title {
            font-size: 22px;
        }

        .death-index-hero__lead {
            font-size: 13px;
            line-height: 1.6;
        }

        .death-index-tabs {
            display: flex;
            gap: 8px;
            margin-top: 14px;
            margin-right: -2px;
            padding-bottom: 4px;
            overflow-x: auto;
            overflow-y: hidden;
            flex-wrap: nowrap;
            -webkit-overflow-scrolling: touch;
            scrollbar-width: none;
        }

        .death-index-tabs::-webkit-scrollbar {
            display: none;
        }

        .death-index-tabs__link {
            flex: 0 0 auto;
            min-width: 156px;
            padding: 10px 14px;
        }

        .death-index-card {
            border-radius: 20px;
        }

        .death-index-card__head {
            align-items: flex-start;
            padding: 14px;
            flex-direction: column;
        }

        .death-index-card__title {
            font-size: 18px;
        }

        .death-index-card__count {
            padding: 7px 10px;
        }

        .death-index-table-wrap {
            overflow: visible;
            border-radius: 0;
        }

        .death-index-floating-head {
            display: none !important;
        }

        .death-index-table,
        .death-index-table thead,
        .death-index-table tbody,
        .death-index-table tr,
        .death-index-table th,
        .death-index-table td {
            display: block;
            width: 100%;
        }

        .death-index-table {
            min-width: 0;
        }

        .death-index-table thead {
            display: none;
        }

        .death-index-table tbody {
            padding: 10px;
        }

        .death-index-table tbody tr {
            margin-bottom: 12px;
        }

        .death-index-table tbody tr:last-child {
            margin-bottom: 0;
        }

        .death-index-table tbody tr[data-death-row] {
            overflow: hidden;
            border: 1px solid #e8edf1;
            border-radius: 18px;
            background: #fff;
            box-shadow: 0 10px 24px rgba(31, 42, 42, 0.05);
        }

        .death-index-table tbody tr[data-death-row] td {
            display: flex;
            gap: 12px;
            justify-content: space-between;
            padding: 11px 12px;
            border-bottom: 1px solid #eef2f4;
            background: transparent !important;
            text-align: right;
        }

        .death-index-table tbody tr[data-death-row] td:last-child {
            border-bottom: 0;
        }

        .death-index-table tbody tr[data-death-row] td[data-empty="1"] {
            display: none;
        }

        .death-index-table td::before {
            content: attr(data-label);
            flex: 0 0 42%;
            min-width: 0;
            color: #6a7c8a;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: 0.05em;
            line-height: 1.45;
            text-transform: uppercase;
            text-align: left;
        }

        .death-index-name {
            font-size: 15px;
            line-height: 1.45;
        }

        .death-index-table__group td {
            padding: 4px 2px 8px;
            border: 0;
            background: transparent !important;
        }

        .death-index-table__group-label,
        .death-index-table__group-count {
            display: block;
        }

        .death-index-table__group-count {
            margin-top: 4px;
            margin-left: 0;
        }

        .death-index-empty {
            padding: 26px 18px;
        }

        .death-index-chip {
            max-width: 100%;
            white-space: normal;
            justify-content: flex-end;
            text-align: right;
        }
    }
</style>
@endsection

@section('content')
<div class="death-index-shell">
    <section class="death-index-hero">
        <div class="death-index-hero__top">
            <div>
                <h1 class="death-index-hero__title">Database Wafat {{ optional($coreUser)->display_name }}</h1>
                <p class="death-index-hero__lead">
                    Daftar anggota keluarga yang wafat, dikelompokkan berdasarkan level keturunan dari core aktif. Tab haul memakai bulan Hijriyah saat ini.
                </p>
            </div>
            @if ($activeTab === 'haul-bulan-ini' && $currentHijriMonthBadge)
            <span class="death-index-hero__badge">{{ $currentHijriMonthBadge }}</span>
            @endif
        </div>

        <div class="death-index-tabs">
            <a
                href="{{ route('deaths.index', ['tab' => 'all']) }}#death-index-results"
                class="death-index-tabs__link{{ $activeTab === 'all' ? ' is-active' : '' }}"
                @if ($activeTab === 'all') aria-current="page" @endif
            >
                <span class="death-index-tabs__label">Semua Wafat</span>
                <span class="death-index-tabs__meta">{{ $allRows->count() }} data @if ($activeTab === 'all') aktif @endif</span>
            </a>
            <a
                href="{{ route('deaths.index', ['tab' => 'haul-bulan-ini']) }}#death-index-results"
                class="death-index-tabs__link{{ $activeTab === 'haul-bulan-ini' ? ' is-active' : '' }}"
                @if ($activeTab === 'haul-bulan-ini') aria-current="page" @endif
            >
                <span class="death-index-tabs__label">Haul Bulan Ini</span>
                <span class="death-index-tabs__meta">
                    {{ $haulRows->count() }} data
                    @if ($currentHijriMonthBadge)
                        - {{ $currentHijriMonthBadge }}
                    @endif
                    @if ($activeTab === 'haul-bulan-ini') aktif @endif
                </span>
            </a>
        </div>
    </section>

    @if ($activeTab === 'haul-bulan-ini')
        <section class="death-index-card death-index-results" id="death-index-results">
            <div class="death-index-card__head">
                <h2 class="death-index-card__title">Haul Bulan Ini</h2>
                <span class="death-index-card__count">{{ $haulRows->count() }} data</span>
            </div>

            @if ($haulRows->isEmpty())
            <div class="death-index-empty">
                <h3>Belum ada haul pada bulan Hijriyah ini</h3>
                <p>Data dengan tanggal wafat lengkap akan otomatis muncul saat bulan Hijriyah-nya sesuai.</p>
            </div>
            @else
            <div class="death-index-table-wrap">
                <table class="death-index-table">
                    <thead>
                        <tr>
                            <th>Nama Lengkap</th>
                            <th>Level</th>
                            <th>Hub</th>
                            <th>Tanggal Haul Hijriyah</th>
                            <th>Tanggal Wafat</th>
                            <th>Countdown Haul</th>
                            <th>Lokasi Makam</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($haulRows as $row)
                        <tr data-death-row="{{ $row['id'] }}">
                            <td class="death-index-name" data-label="Nama lengkap">{{ $row['name'] }}</td>
                            <td data-label="Level">{{ $row['generation_label'] }}</td>
                            <td data-label="Hubungan">
                                <span class="death-index-chip {{ $row['relationship_type'] === 'Kandung' ? 'death-index-chip--blood' : 'death-index-chip--spouse' }}">
                                    {{ $row['relationship_type'] }}
                                </span>
                            </td>
                            <td data-label="Tanggal haul Hijriyah" data-empty="{{ $row['hijri_haul_label'] === 'Tidak tersedia' ? '1' : '0' }}">{{ $row['hijri_haul_label'] }}</td>
                            <td data-label="Tanggal wafat">{{ $row['death_date_label'] }}</td>
                            <td data-label="Countdown haul" data-empty="{{ $row['haul_countdown_label'] === 'Tidak tersedia' ? '1' : '0' }}">{{ $row['haul_countdown_label'] }}</td>
                            <td data-label="Lokasi makam" data-empty="{{ $row['cemetery_location_label'] === 'Tidak tersedia' ? '1' : '0' }}">{{ $row['cemetery_location_label'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>
    @else
        <section class="death-index-card death-index-results" id="death-index-results">
            <div class="death-index-card__head">
                <h2 class="death-index-card__title">Semua Wafat</h2>
                <span class="death-index-card__count">{{ $allRows->count() }} data</span>
            </div>

            @if ($allRows->isEmpty())
            <div class="death-index-empty">
                <h3>Belum ada data wafat pada tenant ini</h3>
                <p>Data akan muncul setelah anggota keluarga memiliki tahun atau tanggal wafat.</p>
            </div>
            @else
            <div class="death-index-table-wrap">
                <table class="death-index-table">
                    <thead>
                        <tr>
                            <th>Nama Lengkap</th>
                            <th>L/P</th>
                            <th>Ortu</th>
                            <th>Level</th>
                            <th>Nasab</th>
                            <th>Hub</th>
                            <th>Tanggal Wafat</th>
                            <th>Tanggal Haul Hijriyah</th>
                            <th>Countdown Haul</th>
                            <th>Lokasi Makam</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($allGroups as $group)
                        <tr class="death-index-table__group">
                            <td colspan="10">
                                <span class="death-index-table__group-label">{{ $group['label'] }}</span>
                                <span class="death-index-table__group-count">{{ $group['count'] }} data</span>
                            </td>
                        </tr>
                        @foreach ($group['rows'] as $row)
                        <tr data-death-row="{{ $row['id'] }}">
                            <td class="death-index-name" data-label="Nama lengkap">{{ $row['name'] }}</td>
                            <td data-label="L/P">{{ $row['gender_code'] }}</td>
                            <td data-label="Orang tua" data-empty="{{ $row['parent_label'] === 'Tidak tersedia' ? '1' : '0' }}">{{ $row['parent_label'] }}</td>
                            <td data-label="Level">{{ $row['generation_label'] }}</td>
                            <td data-label="Nasab" data-empty="{{ $row['nasab_label'] === 'Tidak tersedia' ? '1' : '0' }}">
                                <span class="death-index-chip death-index-chip--muted">{{ $row['nasab_label'] }}</span>
                            </td>
                            <td data-label="Hubungan">
                                <span class="death-index-chip {{ $row['relationship_type'] === 'Kandung' ? 'death-index-chip--blood' : 'death-index-chip--spouse' }}">
                                    {{ $row['relationship_type'] }}
                                </span>
                            </td>
                            <td data-label="Tanggal wafat">{{ $row['death_date_label'] }}</td>
                            <td data-label="Tanggal haul Hijriyah" data-empty="{{ $row['hijri_haul_label'] === 'Tidak tersedia' ? '1' : '0' }}">{{ $row['hijri_haul_label'] }}</td>
                            <td data-label="Countdown haul" data-empty="{{ $row['haul_countdown_label'] === 'Tidak tersedia' ? '1' : '0' }}">{{ $row['haul_countdown_label'] }}</td>
                            <td data-label="Lokasi makam" data-empty="{{ $row['cemetery_location_label'] === 'Tidak tersedia' ? '1' : '0' }}">{{ $row['cemetery_location_label'] }}</td>
                        </tr>
                        @endforeach
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>
    @endif
</div>
@endsection

@section('ext_js')
<script>
    (function () {
        var desktopBreakpoint = 767;
        var wrappers = Array.prototype.slice.call(document.querySelectorAll('.death-index-table-wrap'));

        if (!wrappers.length) {
            return;
        }

        var instances = wrappers.map(function (wrapper) {
            var table = wrapper.querySelector('.death-index-table');
            var thead = table ? table.querySelector('thead') : null;

            if (!table || !thead) {
                return null;
            }

            var floating = document.createElement('div');
            floating.className = 'death-index-floating-head';
            floating.innerHTML = '<div class="death-index-floating-head__viewport"><table aria-hidden="true"><thead>' + thead.innerHTML + '</thead></table></div>';
            document.body.appendChild(floating);

            var floatingTable = floating.querySelector('table');
            var sourceThs = Array.prototype.slice.call(thead.querySelectorAll('th'));
            var floatingThs = Array.prototype.slice.call(floating.querySelectorAll('th'));
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
            var dragThreshold = 6;

            function syncWidths() {
                floatingTable.style.width = table.offsetWidth + 'px';

                sourceThs.forEach(function (th, index) {
                    var width = th.getBoundingClientRect().width;
                    var floatingTh = floatingThs[index];

                    if (!floatingTh) {
                        return;
                    }

                    floatingTh.style.width = width + 'px';
                    floatingTh.style.minWidth = width + 'px';
                    floatingTh.style.maxWidth = width + 'px';
                });
            }

            function hide() {
                floating.classList.remove('is-visible');
            }

            function releaseDragState() {
                if (dragState.active && dragState.pointerId !== null && wrapper.releasePointerCapture) {
                    try {
                        wrapper.releasePointerCapture(dragState.pointerId);
                    } catch (error) {}
                }

                dragState.active = false;
                dragState.pointerId = null;
                wrapper.classList.remove('is-drag-armed', 'is-dragging');
            }

            function onPointerDown(event) {
                if (window.innerWidth <= desktopBreakpoint || event.button !== 0 || event.pointerType === 'touch') {
                    return;
                }

                if (event.target.closest('a, button, input, textarea, select, label')) {
                    return;
                }

                dragState.active = true;
                dragState.pointerId = event.pointerId;
                dragState.startX = event.clientX;
                dragState.startY = event.clientY;
                dragState.startScrollLeft = wrapper.scrollLeft;
                dragState.startWindowY = window.scrollY;
                dragState.didDrag = false;
                dragState.suppressClick = false;

                wrapper.classList.add('is-drag-armed');

                if (wrapper.setPointerCapture) {
                    try {
                        wrapper.setPointerCapture(event.pointerId);
                    } catch (error) {}
                }
            }

            function onPointerMove(event) {
                if (!dragState.active || dragState.pointerId !== event.pointerId) {
                    return;
                }

                var deltaX = event.clientX - dragState.startX;
                var deltaY = event.clientY - dragState.startY;

                if (!dragState.didDrag && Math.max(Math.abs(deltaX), Math.abs(deltaY)) < dragThreshold) {
                    return;
                }

                if (!dragState.didDrag) {
                    dragState.didDrag = true;
                    wrapper.classList.add('is-dragging');
                }

                event.preventDefault();

                wrapper.scrollLeft = dragState.startScrollLeft - deltaX;
                window.scrollTo({
                    top: Math.max(0, dragState.startWindowY - deltaY),
                    behavior: 'auto'
                });
            }

            function onPointerUp(event) {
                if (!dragState.active || dragState.pointerId !== event.pointerId) {
                    return;
                }

                dragState.suppressClick = dragState.didDrag;
                releaseDragState();
            }

            function update() {
                if (window.innerWidth <= desktopBreakpoint) {
                    hide();
                    return;
                }

                syncWidths();

                var wrapperRect = wrapper.getBoundingClientRect();
                var headerHeight = thead.getBoundingClientRect().height;
                var topOffset = 0;
                var shouldShow = wrapperRect.top <= topOffset && wrapperRect.bottom - headerHeight > topOffset;

                if (!shouldShow) {
                    hide();
                    return;
                }

                floating.classList.add('is-visible');
                floating.style.top = topOffset + 'px';
                floating.style.left = wrapperRect.left + 'px';
                floating.style.width = wrapper.clientWidth + 'px';
                floatingTable.style.transform = 'translateX(' + (-wrapper.scrollLeft) + 'px)';
            }

            wrapper.addEventListener('scroll', update, { passive: true });
            wrapper.addEventListener('pointerdown', onPointerDown);
            wrapper.addEventListener('pointermove', onPointerMove);
            wrapper.addEventListener('pointerup', onPointerUp);
            wrapper.addEventListener('pointercancel', onPointerUp);
            wrapper.addEventListener('lostpointercapture', releaseDragState);
            wrapper.addEventListener('click', function (event) {
                if (!dragState.suppressClick) {
                    return;
                }

                event.preventDefault();
                event.stopPropagation();
                dragState.suppressClick = false;
            }, true);

            return {
                update: update,
                hide: hide,
                release: releaseDragState
            };
        }).filter(Boolean);

        if (!instances.length) {
            return;
        }

        var ticking = false;

        function refresh() {
            instances.forEach(function (instance) {
                instance.update();
            });
            ticking = false;
        }

        function requestRefresh() {
            if (ticking) {
                return;
            }

            ticking = true;
            window.requestAnimationFrame(refresh);
        }

        window.addEventListener('scroll', requestRefresh, { passive: true });
        window.addEventListener('resize', requestRefresh);
        window.addEventListener('orientationchange', requestRefresh);
        document.addEventListener('visibilitychange', requestRefresh);
        requestRefresh();
    })();
</script>
@endsection
