<div class="panel panel-default">
    <div class="panel-heading"><h3 class="panel-title">{{ __('app.login_account') }}</h3></div>
    <div class="panel-body">
        <div class="checkbox" style="margin-top:0">
            <label>
                <input type="checkbox" name="update_login_account" value="1" class="js-login-account-toggle" {{ old('update_login_account') ? 'checked' : '' }}>
                Ubah email atau password akun login
            </label>
        </div>
        <p class="text-muted small">
            Email saat ini: <strong>{{ $user->email ?: 'Belum ada akun login' }}</strong>
        </p>
        {!! FormField::email('email', [
            'label' => __('auth.email'),
            'placeholder' => __('app.example').' nama@mail.com',
            'autocomplete' => 'off',
            'data-login-account-field' => '1',
            'disabled' => old('update_login_account') ? null : true,
        ]) !!}
        {!! FormField::password('password', [
            'label' => __('auth.password'),
            'placeholder' => '******',
            'value' => '',
            'autocomplete' => 'new-password',
            'data-login-account-field' => '1',
            'disabled' => old('update_login_account') ? null : true,
        ]) !!}
        <p class="text-muted small" style="margin-bottom:0">Bagian ini tidak ikut tersimpan kecuali dicentang dulu.</p>
    </div>
</div>
