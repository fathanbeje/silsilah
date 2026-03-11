@extends('layouts.user-profile-wide')

@section('subtitle', trans('app.family_tree'))

@section('user-content')
<div id="wrapper">
    @include('users.partials.tree-node', ['node' => $node, 'level' => 1, 'isRoot' => true])
</div>
<div class="container">
<hr>
<div class="row">
    @if (!empty($generationCounts[1]))
    <div class="col-md-1 text-right">{{ trans('app.child_count') }}</div>
    <div class="col-md-1 text-left"><strong style="font-size:30px">{{ $generationCounts[1] }}</strong></div>
    @endif
    @if (!empty($generationCounts[2]))
    <div class="col-md-1 text-right">{{ trans('app.grand_child_count') }}</div>
    <div class="col-md-1 text-left"><strong style="font-size:30px">{{ $generationCounts[2] }}</strong></div>
    @endif
    @if (!empty($generationCounts[3]))
    <div class="col-md-1 text-right">Jumlah Cicit</div>
    <div class="col-md-1 text-left"><strong style="font-size:30px">{{ $generationCounts[3] }}</strong></div>
    @endif
    @if (!empty($generationCounts[4]))
    <div class="col-md-1 text-right">Jumlah Canggah</div>
    <div class="col-md-1 text-left"><strong style="font-size:30px">{{ $generationCounts[4] }}</strong></div>
    @endif
    @if (!empty($generationCounts[5]))
    <div class="col-md-1 text-right">Jumlah Wareng</div>
    <div class="col-md-1 text-left"><strong style="font-size:30px">{{ $generationCounts[5] }}</strong></div>
    @endif
    @if (!empty($generationCounts[6]))
    <div class="col-md-1 text-right">Jumlah Udheg2</div>
    <div class="col-md-1 text-left"><strong style="font-size:30px">{{ $generationCounts[6] }}</strong></div>
    @endif
</div>
@endsection

@section ('ext_css')
<link rel="stylesheet" href="{{ secure_asset('css/tree.css') }}">
<link rel="stylesheet" href="{{ secure_asset('css/family-display.css') }}">
@endsection
