<div class="panel panel-default">
    <div class="panel-heading">{{ trans('auth.claim_registration_title') }}</div>
    <div class="panel-body">
<p>{{ trans('auth.claim_registration_help', ['name' => $user->display_name]) }}</p>

        @if ($user->email)
        <div class="alert alert-info">
            {{ trans('auth.claim_unavailable') }}
            <a href="{{ route('login') }}">{{ trans('auth.login') }}</a>
        </div>
        @elseif ($user->dob)
        <form method="POST" action="{{ route('claim-registration.store', $user) }}">
            {{ csrf_field() }}
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group{{ $errors->has('dob') ? ' has-error' : '' }}">
                        <label for="dob">{{ trans('user.dob') }}</label>
                        <input id="dob" type="date" class="form-control" name="dob" value="{{ old('dob') }}" required>
                        @if ($errors->has('dob'))
                        <span class="help-block"><strong>{{ $errors->first('dob') }}</strong></span>
                        @endif
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group{{ $errors->has('email') ? ' has-error' : '' }}">
                        <label for="email">{{ trans('user.email') }}</label>
                        <input id="email" type="email" class="form-control" name="email" value="{{ old('email') }}" required>
                        @if ($errors->has('email'))
                        <span class="help-block"><strong>{{ $errors->first('email') }}</strong></span>
                        @endif
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group{{ $errors->has('password') ? ' has-error' : '' }}">
                        <label for="password">{{ trans('auth.password') }}</label>
                        <input id="password" type="password" class="form-control" name="password" required>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="password_confirmation">{{ trans('auth.password_confirmation') }}</label>
                        <input id="password_confirmation" type="password" class="form-control" name="password_confirmation" required>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">{{ trans('auth.register') }}</button>
        </form>
        @else
        <div class="alert alert-warning">{{ trans('auth.claim_birthdate_missing') }}</div>
        <form method="POST" action="{{ route('registration-requests.store', $user) }}">
            {{ csrf_field() }}
            <div class="row">
                <div class="col-md-4">
                    <div class="form-group{{ $errors->has('request_email') ? ' has-error' : '' }}">
                        <label for="request_email">{{ trans('user.email') }}</label>
                        <input id="request_email" type="email" class="form-control" name="request_email" value="{{ old('request_email') }}" required>
                        @if ($errors->has('request_email'))
                        <span class="help-block"><strong>{{ $errors->first('request_email') }}</strong></span>
                        @endif
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group{{ $errors->has('requested_birth_date') ? ' has-error' : '' }}">
                        <label for="requested_birth_date">{{ trans('user.dob') }}</label>
                        <input id="requested_birth_date" type="date" class="form-control" name="requested_birth_date" value="{{ old('requested_birth_date') }}">
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="form-group">
                        <label for="notes">Catatan</label>
                        <input id="notes" type="text" class="form-control" name="notes" value="{{ old('notes') }}">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-warning">{{ trans('auth.request_registration') }}</button>
        </form>
        @endif
    </div>
</div>
