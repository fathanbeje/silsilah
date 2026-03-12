<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ app_setting('site_header_name', config('app.name', 'Laravel')) }}</title>

    <!-- Styles -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Berkshire+Swash&display=swap" rel="stylesheet">
    <link href="{{ secure_asset('css/app.css') }}" rel="stylesheet">
    @yield('ext_css')
    <style>
    .page-header {
        margin-top: 0px;
    }

    .site-brand {
        display: inline-flex !important;
        align-items: center;
        min-height: 50px;
        padding-top: 10px;
        padding-bottom: 10px;
        font-family: 'Berkshire Swash', 'Palatino Linotype', serif;
        font-size: 27px;
        line-height: 1.1;
        color: #342810 !important;
        letter-spacing: 0.02em;
        text-shadow: 0 1px 0 rgba(255, 255, 255, 0.45);
    }

    .site-brand:hover,
    .site-brand:focus {
        color: #2a200d !important;
        text-decoration: none;
    }

    .site-brand__text {
        position: relative;
        display: inline-block;
        padding: 0 6px 2px;
    }

    .site-brand__text:after {
        content: '';
        position: absolute;
        left: 6px;
        right: 6px;
        bottom: -2px;
        height: 8px;
        border-radius: 999px;
        background: linear-gradient(90deg, rgba(212, 175, 55, 0.28), rgba(212, 175, 55, 0.05));
        z-index: -1;
    }

    @media (max-width: 767px) {
        .site-brand {
            max-width: calc(100vw - 120px);
            font-size: 21px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
    }
    </style>
</head>
<body>
    @php
        $mobileLauncherRoutes = [
            'users.search',
            'users.search.page',
            'users.chart',
            'users.show',
            'users.tree',
            'users.marriages',
            'users.death',
            'profile',
        ];
    @endphp
    <div id="app">
        @include('layouts.partials.nav')

        <div class="container">
        @yield('content')
        </div>
    </div>
    @if (in_array(Route::currentRouteName(), $mobileLauncherRoutes, true))
        @include('layouts.partials.mobile-family-launcher')
    @endif

    <!-- Scripts -->
    <script src="{{ secure_asset('js/app.js') }}"></script>
    @yield('ext_js')
    @yield('script')
    <script>
        var header = $('h2.page-header').contents();
        str = '';
        mainText = header.filter(function () {
                // return type of text
                return this.nodeType === 3;
            })[0];
        str += mainText.data.trim();

        if (mainText.nextSibling) {
            // next siblings should be a small tag text
            str += " - "+mainText.nextSibling.innerText;
        }
        $('title').prepend(str+" - ");
    </script>
</body>
</html>
