@extends('layouts.app')

@section('content')
<h2 class="page-header">
        {{ $couple->husband->display_name }} &amp; {{ $couple->wife->display_name }} <small>{{ trans('couple.edit') }}</small>
</h2>

@include('couples.partials.stat')

<div class="row">
    <div class="col-md-4 col-md-offset-4">
        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">{{ trans('couple.update') }}</h3></div>
            {!! Form::model($couple, ['route' => ['couples.update', $couple], 'method' => 'patch']) !!}
            <div class="panel-body">
                <div class="row">
                    <div class="col-md-4">
                        <div class="form-group">
                            {{ Form::label('spouse_order', trans('couple.spouse_order')) }}
                            {{ Form::number('spouse_order', $couple->spouse_order, ['class' => 'form-control', 'min' => 1, 'placeholder' => trans('couple.spouse_order')]) }}
                        </div>
                    </div>
                    <div class="col-md-4">
                        {!! FormField::text('marriage_date', ['label' => trans('couple.marriage_date')]) !!}
                    </div>
                    <div class="col-md-4">
                        {!! FormField::text('divorce_date', ['label' => trans('couple.divorce_date')]) !!}
                    </div>
                </div>
            </div>
            <div class="panel-footer">
                {!! Form::submit(trans('couple.update'), ['class' => 'btn btn-success']) !!}
                {{ link_to_route('couples.show', trans('app.cancel'), [$couple], ['class' => 'btn btn-default']) }}
            </div>
            {!! Form::close() !!}
        </div>
    </div>
</div>

@endsection

@section ('ext_css')
<link rel="stylesheet" href="{{ secure_asset('css/plugins/jquery.datetimepicker.css') }}">
@endsection

@section ('ext_js')
<script src="{{ secure_asset('js/plugins/jquery.datetimepicker.js') }}"></script>
@endsection

@section ('script')
<script>
(function () {
    $('#marriage_date, #divorce_date').datetimepicker({
        timepicker:false,
        format:'Y-m-d',
        closeOnDateSelect: true,
        scrollInput: false
    });
})();
</script>
@endsection
