<div class="panel panel-default">
    <div class="panel-heading"><h3 class="panel-title">{{ trans('user.profile') }}</h3></div>
    <div class="panel-body text-center">
        {{ userPhoto($user, ['style' => 'width:100%;max-width:300px']) }}
    </div>
    @if (auth()->check() && is_system_admin(auth()->user()))
    <div class="panel-body" style="padding-top:0">
        {{ Form::open(['route' => ['users.quick-deceased', $user], 'method' => 'patch']) }}
            <input type="hidden" name="is_deceased" value="0">
            <div class="checkbox" style="margin:0">
                <label>
                    <input type="checkbox" name="is_deceased" value="1" {{ $user->isDeceased() ? 'checked' : '' }}>
                    {{ __('user.is_deceased') }}
                </label>
            </div>
            <div class="text-muted small">{{ __('user.deceased_status_hint') }}</div>
            <button type="submit" class="btn btn-xs btn-default" style="margin-top:8px">{{ __('user.save_deceased_status') }}</button>
        {{ Form::close() }}
    </div>
    @endif
    <table class="table">
        <tbody>
            <tr>
                <th class="col-sm-4">{{ trans('user.name') }}</th>
                <td class="col-sm-8">{{ $user->profileLink() }}</td>
            </tr>
            <tr>
                <th>{{ trans('user.nickname') }}</th>
                <td>{{ $user->nickname }}</td>
            </tr>
            <tr>
                <th>{{ trans('user.gender') }}</th>
                <td>{{ $user->gender }}</td>
            </tr>
            <tr>
                <th>{{ trans('user.dob') }}</th>
                <td>{{ $user->dob }}</td>
            </tr>
            <tr>
                <th>{{ trans('user.birth_order') }}</th>
                <td>{{ $user->birth_order }}</td>
            </tr>
            @if ($user->hasDeathInfo())
            <tr>
                <th>{{ trans('user.dod') }}</th>
                <td>{{ $user->dod ?: $user->yod ?: __('user.is_deceased') }}</td>
            </tr>
            @endif
            <tr>
                <th>{{ trans('user.age') }}</th>
                <td>
                    @if ($user->age)
                        {!! $user->age_string !!}
                    @endif
                </td>
            </tr>
            @if ($user->email)
            <tr>
                <th>{{ trans('user.email') }}</th>
                <td>{{ $user->email }}</td>
            </tr>
            @endif
            <tr>
                <th>{{ trans('user.phone') }}</th>
                <td>{{ $user->phone }}</td>
            </tr>
            <tr>
                <th>{{ trans('user.address') }}</th>
                <td>{!! nl2br($user->address) !!}</td>
            </tr>
        </tbody>
    </table>
</div>
