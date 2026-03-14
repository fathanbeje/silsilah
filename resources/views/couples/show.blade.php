@extends('layouts.app')

@section('content')
@can('edit', $couple)
    <div class="pull-right">
        {{ link_to_route('couples.edit', trans('couple.edit'), $couple, ['class' => 'btn btn-warning']) }}
    </div>
@endcan
<h2 class="page-header">
        {{ $couple->husband->display_name }} & {{ $couple->wife->display_name }} <small>{{ trans('couple.detail') }}</small>
</h2>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->has('couple'))
    <div class="alert alert-danger">{{ $errors->first('couple') }}</div>
@endif

@include('couples.partials.stat')
@can('delete', $couple)
    <div class="text-right" style="margin: 12px 0 16px;">
        {{ Form::open(['route' => ['couples.destroy', $couple], 'method' => 'delete', 'style' => 'display:inline']) }}
            <button
                type="submit"
                class="btn btn-danger"
                onclick="return confirm('{{ trans('couple.delete_confirm') }}')"
                {{ $couple->childs_count > 0 ? 'disabled' : '' }}
            >{{ trans('app.delete') }}</button>
        {{ Form::close() }}
        @if ($couple->childs_count > 0)
            <p class="help-block" style="margin-top:8px;">{{ trans('couple.delete_blocked_childs') }}</p>
        @endif
    </div>
@endcan
<br>
<h4 class="page-header">{{ trans('user.childs') }} & {{ trans('user.grand_childs') }}</h4>
@if ($couple->childs->isEmpty())
    <i>{{ trans('app.childs_were_not_recorded') }}</i>
@else
    <?php $no = 0; ?>
    @foreach($couple->childs->chunk(4) as $chunkedChild)
    <div class="row">
        @foreach($chunkedChild as $child)
        <div class="col-md-3">
            <h4><strong>{{ ++$no }}. {{ $child->profileLink() }} <span>({{ $child->gender }})</span></strong></h4>
            <ul style="padding-left: 35px">
                @foreach($child->childs as $grand)
                <li>{{ $grand->profileLink() }} <span>({{ $grand->gender }})</span></li>
                @endforeach
            </ul>
        </div>
        @endforeach
        @if (! $loop->last)
        <div class="clearfix"></div><hr>
        @endif
    </div>
    @endforeach
@endif
@endsection
