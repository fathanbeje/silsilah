{{ Form::model($scope, ['route' => $scope->exists ? ['domain-family-scopes.update', $scope] : ['domain-family-scopes.store'], 'method' => $scope->exists ? 'patch' : 'post']) }}
<div class="panel panel-default">
    <div class="panel-body">
        {!! FormField::text('host', ['label' => 'Host / Subdomain', 'placeholder' => 'syamsuri.bani.my.id', 'readonly' => !empty($lockHost)]) !!}
        {!! FormField::select('core_user_id', $coreUserOptions, ['label' => 'CORE Silsilah', 'placeholder' => 'Pilih user inti']) !!}

        <div class="checkbox">
            <label>
                {{ Form::hidden('is_active', 0) }}
                {{ Form::checkbox('is_active', 1, old('is_active', $scope->is_active ?? true)) }} Aktif
            </label>
        </div>
    </div>
    <div class="panel-footer">
        <button type="submit" class="btn btn-primary">Simpan</button>
        <a href="{{ route('domain-family-scopes.index') }}" class="btn btn-default">Batal</a>
    </div>
</div>
{{ Form::close() }}
