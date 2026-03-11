@extends('layouts.app')

@section('ext_css')
<link rel="stylesheet" href="{{ secure_asset('css/family-display.css') }}">
@endsection

@section('content')
<h2 class="page-header">
    {{ $user->name }} <small>{{ trans('app.family_chart') }}</small>
</h2>

@if (session('status'))
<div class="alert alert-success">{{ session('status') }}</div>
@endif

@include('users.partials.chart-content')

@guest
    @include('users.partials.public-claim-card')
@endguest
@endsection
