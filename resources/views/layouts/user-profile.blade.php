@extends('layouts.app')

@section('content')
    @include('users.partials.action-buttons', ['user' => $user])
    <h2 class="page-header">
        {{ $user->display_name }} <small>@yield('subtitle')</small>
    </h2>
    @yield('user-content')
@endsection
