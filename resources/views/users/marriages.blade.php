@extends('layouts.user-profile')

@section('subtitle', trans('user.marriages'))

@section('user-content')

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->has('couple'))
    <div class="alert alert-danger">{{ $errors->first('couple') }}</div>
@endif

<div class="row">
    @foreach ($marriages as $marriage)
    <div class="col-md-4">
        <div class="panel panel-default">
            <table class="table table-condensed">
                <tr><th class="col-xs-5">{{ trans('couple.husband') }}</th><td>{{ $marriage->husband->profileLink() }}</th></tr>
                <tr><th>{{ trans('couple.wife') }}</th><td>{{ $marriage->wife->profileLink() }}</th></tr>
                <tr><th>{{ trans('couple.spouse_order') }}</th><td>{{ $marriage->spouse_order ?: '-' }}</th></tr>
                <tr><th>{{ trans('couple.marriage_date') }}</th><td>{{ $marriage->marriage_date }}</th></tr>
                @if ($marriage->divorce_date)
                <tr><th>{{ trans('couple.divorce_date') }}</th><td>{{ $marriage->divorce_date }}</th></tr>
                @endif
                <tr><th>{{ trans('couple.childs_count') }}</th><td>{{ $marriage->childs_count }}</th></tr>
                {{-- <tr><th>{{ trans('couple.grand_childs_count') }}</th><td>?</th></tr> --}}
            </table>
            <div class="panel-footer">
                {{ link_to_route('couples.show', trans('couple.show'), [$marriage->id], ['class' => 'btn btn-default btn-xs']) }}
                @can('delete', $marriage)
                    @if ($marriage->childs_count === 0)
                        {{ Form::open(['route' => ['couples.destroy', $marriage], 'method' => 'delete', 'style' => 'display:inline']) }}
                            <button
                                type="submit"
                                class="btn btn-danger btn-xs"
                                onclick="return confirm('{{ trans('couple.delete_confirm') }}')"
                            >{{ trans('app.delete') }}</button>
                        {{ Form::close() }}
                    @else
                        <span class="btn btn-default btn-xs disabled" title="{{ trans('couple.delete_blocked_childs') }}">{{ trans('app.delete') }}</span>
                    @endif
                @endcan
            </div>
        </div>
    </div>
    @endforeach
</div>
@endsection
