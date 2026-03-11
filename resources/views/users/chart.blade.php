@extends('layouts.user-profile-wide')

@section('subtitle', trans('app.family_chart'))

@section('ext_css')
<link rel="stylesheet" href="{{ secure_asset('css/family-display.css') }}">
@endsection

@section('user-content')
@include('users.partials.chart-content')
@endsection
