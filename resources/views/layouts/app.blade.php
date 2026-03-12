<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <!-- CSRF Token -->
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ config('app.name', 'Laravel') }}</title>

    <!-- Styles -->
    <link href="{{ secure_asset('css/app.css') }}" rel="stylesheet">
    @yield('ext_css')
    <style>
    .page-header {
        margin-top: 0px;
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
