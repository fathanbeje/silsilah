@extends('layouts.app')

@section('ext_css')
<style>
    .family-search-hero {
        position: relative;
        overflow: hidden;
        margin: -10px 0 26px;
        padding: 28px 24px 24px;
        border-radius: 28px;
        background:
            radial-gradient(circle at top right, rgba(212, 175, 55, 0.28), transparent 28%),
            linear-gradient(135deg, #f8f4e7 0%, #eef4f1 52%, #f7f8fb 100%);
        border: 1px solid #e6e1d2;
        box-shadow: 0 18px 40px rgba(63, 58, 42, 0.08);
    }

    .family-search-hero__title {
        margin: 0 0 8px;
        font-size: 32px;
        line-height: 1.15;
        font-weight: 700;
        color: #1f2a2a;
    }

    .family-search-hero__lead {
        max-width: 760px;
        margin: 0 0 18px;
        font-size: 16px;
        line-height: 1.6;
        color: #526060;
    }

    .family-search-hero__search {
        position: relative;
        max-width: 840px;
        margin-bottom: 18px;
    }

    .family-search-hero__search .input-group {
        position: relative;
        display: flex;
        align-items: stretch;
        gap: 10px;
        padding: 10px;
        border-radius: 22px;
        background: rgba(255, 255, 255, 0.82);
        border: 1px solid rgba(65, 74, 74, 0.08);
        box-shadow: inset 0 1px 0 rgba(255, 255, 255, 0.75);
    }

    .family-search-hero__search .form-control {
        height: 54px;
        border: 0;
        box-shadow: none;
        border-radius: 16px;
        background: #fff;
        padding: 0 18px;
        font-size: 16px;
    }

    .family-search-hero__search .btn {
        height: 54px;
        border-radius: 16px !important;
        padding: 0 18px;
        font-weight: 700;
    }

    .family-search-hero__search .btn-primary {
        border-color: #1f2a2a;
        background: #1f2a2a;
    }

    .family-search-hero__search .btn-default {
        border-color: #d8d8d8;
        background: #f5f5f5;
    }

    .family-search-hero__autocomplete {
        position: absolute;
        top: calc(100% + 8px);
        left: 16px;
        right: 190px;
        z-index: 20;
        display: none;
        overflow: hidden;
        border-radius: 18px;
        box-shadow: 0 20px 40px rgba(31, 42, 42, 0.12);
        border: 1px solid #ececec;
    }

    .family-search-examples {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
        align-items: center;
    }

    .family-search-examples__label {
        color: #6d7777;
        font-size: 13px;
        font-weight: 700;
    }

    .family-search-examples__chip {
        display: inline-flex;
        align-items: center;
        padding: 9px 12px;
        border-radius: 999px;
        background: #fff;
        border: 1px solid #dedede;
        color: #304040;
        text-decoration: none;
        font-size: 13px;
    }

    .family-search-empty {
        margin-bottom: 20px;
    }

    .family-search-empty__grid {
        display: grid;
        grid-template-columns: 1.2fr 1fr;
        gap: 18px;
    }

    .family-search-empty__card {
        padding: 22px;
        border-radius: 24px;
        border: 1px solid #e9e9e9;
        background: #fff;
        box-shadow: 0 14px 30px rgba(32, 40, 40, 0.05);
    }

    .family-search-empty__card h3 {
        margin: 0 0 10px;
        font-size: 20px;
        color: #1f2a2a;
    }

    .family-search-empty__card p {
        margin: 0 0 14px;
        color: #607070;
        line-height: 1.6;
    }

    .family-search-empty__links {
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }

    .family-search-empty__links a {
        border-radius: 999px;
        padding: 9px 14px;
    }

    .family-search-guide__trigger {
        margin-left: 10px;
    }

    .family-search-guide__list {
        margin: 0;
        padding-left: 18px;
        color: #526060;
        line-height: 1.7;
    }

    .family-search-guide__list li + li {
        margin-top: 6px;
    }

    .family-search-guide__tip {
        margin-top: 14px;
        padding: 12px 14px;
        border-radius: 14px;
        background: #f8f5ea;
        color: #6b5b19;
    }

    .family-search-results-header {
        margin-bottom: 16px;
    }

    @media (max-width: 767px) {
        .family-search-hero {
            padding: 22px 16px 18px;
            border-radius: 24px;
        }

        .family-search-hero__title {
            font-size: 26px;
        }

        .family-search-hero__search .input-group {
            display: block;
            padding: 8px;
        }

        .family-search-hero__search .form-control {
            margin-bottom: 8px;
        }

        .family-search-hero__search .input-group-btn {
            display: flex;
            width: 100%;
            gap: 8px;
        }

        .family-search-hero__search .input-group-btn .btn {
            flex: 1 1 0;
        }

        .family-search-hero__autocomplete {
            left: 8px;
            right: 8px;
            top: calc(100% + 6px);
        }

        .family-search-empty__grid {
            grid-template-columns: 1fr;
        }
    }
</style>
@endsection

@section('content')
@if (session('status'))
<div class="alert alert-success">{{ session('status') }}</div>
@endif

<section class="family-search-hero">
    <h1 class="family-search-hero__title">Cari Nama Keluarga</h1>
    <p class="family-search-hero__lead">
        Ketik nama Anda atau orang tua Anda. Lalu buka bagan atau pohon keluarga.
    </p>

    {{ Form::open(['method' => 'get','class' => 'family-search-hero__search']) }}
    <div class="input-group">
        {{ Form::text('q', request('q'), ['class' => 'form-control', 'placeholder' => $searchPlaceholder ?? 'Contoh: ketik nama keluarga', 'autocomplete' => 'off', 'id' => 'family-search-input']) }}
        <span class="input-group-btn">
            {{ Form::submit('Cari Sekarang', ['class' => 'btn btn-primary']) }}
            <button type="button" class="btn btn-default family-search-guide__trigger" data-toggle="modal" data-target="#family-search-guide-modal">Panduan</button>
            @if (request('q'))
            {{ link_to_route('users.search', 'Reset', [], ['class' => 'btn btn-default']) }}
            @endif
        </span>
    </div>
    <div id="family-search-autocomplete" class="family-search-hero__autocomplete list-group"></div>
    {{ Form::close() }}

    @if (!empty($searchExamples) && $searchExamples->isNotEmpty())
    <div class="family-search-examples">
        <span class="family-search-examples__label">Cari cepat:</span>
        @foreach ($searchExamples as $searchExample)
        <a class="family-search-examples__chip" href="{{ route('users.search', ['q' => $searchExample]) }}">{{ $searchExample }}</a>
        @endforeach
    </div>
    @endif

    @if (!empty($landingTreeRootUser))
    <div class="family-search-examples" style="margin-top:12px;">
        <span class="family-search-examples__label">Lihat pohon:</span>
        <a class="family-search-examples__chip" href="{{ route('users.tree', $landingTreeRootUser) }}">{{ $landingTreeRootUser->name ?: $landingTreeRootUser->display_name }}</a>
    </div>
    @endif
</section>

@if (!empty($showSearchResults))
<div class="family-search-results-header">
    <h2 class="page-header">
        Hasil Pencarian
        <small class="pull-right">{!! trans('app.user_found', ['total' => $users->total(), 'keyword' => request('q')]) !!}</small>
    </h2>
</div>
{{ $users->appends(Request::except('page'))->render() }}
@foreach ($users->chunk(4) as $chunkedUser)
<div class="row">
    @foreach ($chunkedUser as $user)
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-heading text-center">
                {{ userPhoto($user, ['style' => 'width:100%;max-width:300px']) }}
                @if ($user->age)
                    {!! $user->age_string !!}
                @endif
            </div>
            <div class="panel-body">
                <h3 class="panel-title">{{ link_to_route('users.chart', $user->display_name, [$user->id]) }} <span>({{ $user->gender }})</span></h3>
                <div>{{ trans('user.nickname') }} : {{ $user->nickname }}</div>
                <hr style="margin: 5px 0;">
                <div>{{ trans('user.father') }} : {{ optional($user->father)->display_name }}</div>
                <div>{{ trans('user.mother') }} : {{ optional($user->mother)->display_name }}</div>
            </div>
            <div class="panel-footer">
                {{ link_to_route('users.chart', trans('app.show_family_chart'), [$user->id], ['class' => 'btn btn-default btn-xs']) }}
            </div>
        </div>
    </div>
    @endforeach
</div>
@endforeach

{{ $users->appends(Request::except('page'))->render() }}
@else
<section class="family-search-empty">
    <div class="family-search-empty__grid">
        <div class="family-search-empty__card">
            <h3>Mulai dari sini</h3>
            <p>Cari nama Anda dulu. Kalau belum ketemu, cari nama ayah atau ibu.</p>
            <div class="family-search-empty__links">
                <a href="#family-search-input" class="btn btn-primary">Cari Nama</a>
                @if (!empty($landingTreeRootUser))
                <a href="{{ route('users.tree', $landingTreeRootUser) }}" class="btn btn-default">Lihat Pohon Keluarga</a>
                @endif
                <button type="button" class="btn btn-default" data-toggle="modal" data-target="#family-search-guide-modal">Panduan Singkat</button>
            </div>
        </div>

        <div class="family-search-empty__card">
            <h3>Punya akun?</h3>
            <p>Buka data keluarga yang cocok, lalu klik <strong>Daftarkan Akun Anda</strong>.</p>
            <div class="family-search-empty__links">
                <a href="#family-search-input" class="btn btn-default">Cari untuk Daftar</a>
                @auth
                    @if (is_system_admin(auth()->user()))
                        <a href="{{ route('birthdays.index') }}" class="btn btn-default">{{ __('birthday.birthday') }}</a>
                    @endif
                @endauth
                @guest
                <a href="{{ route('login') }}" class="btn btn-primary">Login</a>
                @endguest
            </div>
        </div>
    </div>
</section>
@endif

<div class="modal fade" id="family-search-guide-modal" tabindex="-1" role="dialog" aria-labelledby="family-search-guide-modal-label">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <button type="button" class="close" data-dismiss="modal" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
                <h4 class="modal-title" id="family-search-guide-modal-label">Panduan Singkat</h4>
            </div>
            <div class="modal-body">
                <ol class="family-search-guide__list">
                    <li>Ketik nama Anda, ayah, atau ibu.</li>
                    <li>Pilih hasil yang paling cocok.</li>
                    <li>Buka <strong>Bagan</strong> untuk melihat hubungan keluarga.</li>
                    <li>Buka <strong>Pohon</strong> jika ingin melihat keturunan tanpa login.</li>
                    <li>Kalau ingin punya akun, buka data yang cocok lalu klik <strong>Daftarkan Akun Anda</strong>.</li>
                </ol>
                <div class="family-search-guide__tip">
                    Jika data belum tepat, gunakan menu <strong>Usulkan Perubahan Data</strong> pada halaman anggota keluarga tersebut.
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">Mengerti</button>
            </div>
        </div>
    </div>
</div>
@endsection

@section('script')
<script>
    (function () {
        var input = document.getElementById('family-search-input');
        var list = document.getElementById('family-search-autocomplete');
        var timer = null;
        var guideStorageKey = 'family-search-guide-shown';

        if (!input || !list) {
            return;
        }

        function hideList() {
            list.style.display = 'none';
            list.innerHTML = '';
        }

        function renderItems(items) {
            if (!items.length) {
                hideList();
                return;
            }

            function escapeHtml(value) {
                return String(value || '').replace(/[&<>"']/g, function (char) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    }[char];
                });
            }

            list.innerHTML = items.map(function (item) {
                var parents = item.parents ? '<div class="small text-muted">' + escapeHtml(item.parents) + '</div>' : '';
                return '<a class="list-group-item" href="' + item.chart_url + '">' +
                    '<strong>' + escapeHtml(item.name) + '</strong> (' + escapeHtml(item.gender) + ')' +
                    '<div>' + escapeHtml(item.nickname) + '</div>' +
                    parents +
                '</a>';
            }).join('');
            list.style.display = 'block';
        }

        input.addEventListener('input', function () {
            var value = input.value.trim();
            window.clearTimeout(timer);

            if (value.length < 2) {
                hideList();
                return;
            }

            timer = window.setTimeout(function () {
                window.fetch('{{ route('users.autocomplete') }}?q=' + encodeURIComponent(value), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) { return response.json(); })
                    .then(renderItems)
                    .catch(hideList);
            }, 180);
        });

        document.addEventListener('click', function (event) {
            if (!list.contains(event.target) && event.target !== input) {
                hideList();
            }
        });

        if (window.jQuery && window.jQuery.fn.modal) {
            try {
                if (!window.sessionStorage.getItem(guideStorageKey)) {
                    window.jQuery('#family-search-guide-modal').modal('show');
                    window.sessionStorage.setItem(guideStorageKey, '1');
                }
            } catch (error) {}
        }
    })();
</script>
@endsection
