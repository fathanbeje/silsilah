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
        gap: 10px;
        margin-top: 18px;
        flex-wrap: wrap;
    }

    .death-index-tabs__link {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 11px 16px;
        border-radius: 999px;
        border: 1px solid #d9e0e4;
        background: rgba(255, 255, 255, 0.86);
        color: #244152;
        text-decoration: none;
        font-weight: 700;
        transition: transform 0.18s ease, box-shadow 0.18s ease, background 0.18s ease;
    }

    .death-index-tabs__link:hover,
    .death-index-tabs__link:focus {
        color: #244152;
        text-decoration: none;
        transform: translateY(-1px);
        box-shadow: 0 12px 24px rgba(36, 65, 82, 0.08);
    }

    .death-index-tabs__link.is-active {
        background: #244152;
        border-color: #244152;
        color: #fff;
        box-shadow: 0 14px 28px rgba(36, 65, 82, 0.18);
    }

    .death-index-card {
        margin-bottom: 18px;
        overflow: hidden;
        border-radius: 26px;
        border: 1px solid #e6eaed;
        background: #fff;
        box-shadow: 0 16px 34px rgba(31, 42, 42, 0.06);
    }

    .death-index-card__head {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        padding: 18px 22px;
        background: linear-gradient(180deg, #fbfcfd 0%, #f2f7fb 100%);
        border-bottom: 1px solid #e3ebf1;
    }

    .death-index-card__title {
        margin: 0;
        font-size: 20px;
        color: #223444;
    }

    .death-index-card__count {
        display: inline-flex;
        align-items: center;
        padding: 8px 12px;
        border-radius: 999px;
        background: #edf4f9;
        color: #35556d;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.04em;
        text-transform: uppercase;
    }

    .death-index-table-wrap {
        overflow-x: auto;
    }

    .death-index-table {
        width: 100%;
        min-width: 1060px;
        border-collapse: collapse;
    }

    .death-index-table th,
    .death-index-table td {
        padding: 14px 16px;
        border-bottom: 1px solid #ebeff2;
        vertical-align: top;
    }

    .death-index-table thead th {
        background: #f8fafc;
        color: #375064;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        white-space: nowrap;
    }

    .death-index-table tbody tr:nth-child(even) td {
        background: #fcfdfe;
    }

    .death-index-table__group td {
        padding: 14px 16px;
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
        .death-index-hero {
            padding: 20px 16px 16px;
            border-radius: 22px;
        }

        .death-index-hero__top {
            display: block;
        }

        .death-index-hero__badge {
            margin-top: 14px;
        }

        .death-index-hero__title {
            font-size: 24px;
        }

        .death-index-card__head {
            padding: 16px;
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
                href="{{ route('deaths.index', ['tab' => 'all']) }}"
                class="death-index-tabs__link{{ $activeTab === 'all' ? ' is-active' : '' }}"
            >
                Semua Wafat
            </a>
            <a
                href="{{ route('deaths.index', ['tab' => 'haul-bulan-ini']) }}"
                class="death-index-tabs__link{{ $activeTab === 'haul-bulan-ini' ? ' is-active' : '' }}"
            >
                Haul Bulan Ini
                @if ($currentHijriMonthBadge)
                <span>{{ $currentHijriMonthBadge }}</span>
                @endif
            </a>
        </div>
    </section>

    @if ($activeTab === 'haul-bulan-ini')
        <section class="death-index-card">
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
                            <td class="death-index-name">{{ $row['name'] }}</td>
                            <td>{{ $row['generation_label'] }}</td>
                            <td>
                                <span class="death-index-chip {{ $row['relationship_type'] === 'Kandung' ? 'death-index-chip--blood' : 'death-index-chip--spouse' }}">
                                    {{ $row['relationship_type'] }}
                                </span>
                            </td>
                            <td>{{ $row['hijri_haul_label'] }}</td>
                            <td>{{ $row['death_date_label'] }}</td>
                            <td>{{ $row['haul_countdown_label'] }}</td>
                            <td>{{ $row['cemetery_location_label'] }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @endif
        </section>
    @else
        <section class="death-index-card">
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
                            <td class="death-index-name">{{ $row['name'] }}</td>
                            <td>{{ $row['gender_code'] }}</td>
                            <td>{{ $row['parent_label'] }}</td>
                            <td>{{ $row['generation_label'] }}</td>
                            <td>
                                <span class="death-index-chip death-index-chip--muted">{{ $row['nasab_label'] }}</span>
                            </td>
                            <td>
                                <span class="death-index-chip {{ $row['relationship_type'] === 'Kandung' ? 'death-index-chip--blood' : 'death-index-chip--spouse' }}">
                                    {{ $row['relationship_type'] }}
                                </span>
                            </td>
                            <td>{{ $row['death_date_label'] }}</td>
                            <td>{{ $row['hijri_haul_label'] }}</td>
                            <td>{{ $row['haul_countdown_label'] }}</td>
                            <td>{{ $row['cemetery_location_label'] }}</td>
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
