@extends('layouts.app')

@section('content')
    <h2 class="page-header">{{ __('app.birth_order_management') }}</h2>

    @if (session('status'))
        <div class="alert alert-success">{{ session('status') }}</div>
    @endif

    @if ($errors->has('children'))
        <div class="alert alert-danger">{{ $errors->first('children') }}</div>
    @endif

    <div class="panel panel-default">
        <div class="panel-body">
            <form method="GET" action="{{ route('birth-orders.index') }}" class="form-inline">
                <div class="form-group">
                    <label for="q">{{ __('app.search') }}</label>
                    <input type="text" name="q" id="q" value="{{ $query }}" class="form-control" placeholder="{{ __('app.birth_order_search_placeholder') }}">
                </div>
                <div class="checkbox" style="margin-left: 10px;">
                    <label>
                        <input type="checkbox" name="all" value="1" {{ $showAll ? 'checked' : '' }}>
                        {{ __('app.birth_order_show_all_families') }}
                    </label>
                </div>
                <button type="submit" class="btn btn-primary">{{ __('app.search') }}</button>
            </form>
            <hr>
            <p class="help-block">
                {{ __('app.birth_order_total_missing', ['count' => $totalMissingUsers]) }}
            </p>
        </div>
    </div>

    @forelse ($familyGroups as $family)
        <div class="panel panel-default">
            <div class="panel-heading">
                <strong>{{ $family['father_name'] ?: '?' }}</strong> &amp; <strong>{{ $family['mother_name'] ?: '?' }}</strong>
                <span class="pull-right">
                    {{ trans_choice('app.birth_order_missing_in_family', $family['missing_count'], ['count' => $family['missing_count']]) }}
                </span>
            </div>
            <div class="panel-body">
                <form method="POST" action="{{ route('birth-orders.update') }}">
                    {{ csrf_field() }}
                    <input type="hidden" name="family_key" value="{{ $family['key'] }}">

                    <div class="table-responsive">
                        <table class="table table-bordered table-striped">
                            <thead>
                                <tr>
                                    <th style="width: 50px">#</th>
                                    <th>{{ __('user.name') }}</th>
                                    <th style="width: 160px">{{ __('user.birth_order') }}</th>
                                    <th style="width: 140px">{{ __('app.show_profile') }}</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($family['children'] as $index => $child)
                                    <tr>
                                        <td>{{ $index + 1 }}</td>
                                        <td>{{ $child->name }}</td>
                                        <td>
                                            <input
                                                type="number"
                                                min="1"
                                                class="form-control"
                                                name="children[{{ $child->id }}]"
                                                value="{{ old('children.'.$child->id, $child->birth_order) }}"
                                            >
                                        </td>
                                        <td>
                                            <a href="{{ route('users.show', $child->id) }}" class="btn btn-default btn-sm">
                                                {{ __('app.show_profile') }}
                                            </a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>

                    <div class="text-right">
                        <button type="submit" class="btn btn-success">{{ __('app.update') }}</button>
                    </div>
                </form>
            </div>
        </div>
    @empty
        <div class="alert alert-info">{{ __('app.birth_order_no_family_found') }}</div>
    @endforelse
@endsection
