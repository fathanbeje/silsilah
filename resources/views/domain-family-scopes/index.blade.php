@extends('layouts.app')

@section('title', 'Scope Domain')

@section('content')
<h3 class="page-header">Scope Domain Silsilah</h3>

@if (session('status'))
<div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="alert alert-info">
    <div><strong>Host aktif:</strong> {{ $currentHost ?: '-' }}</div>
    <div style="margin-top: 6px;">Satu host/subdomain cukup punya satu scope aktif yang menunjuk ke <strong>CORE</strong> keluarga untuk host tersebut.</div>
</div>

@if (!$canCreateScope)
<div class="alert alert-warning">
    Pada tenant scoped, halaman ini hanya menampilkan scope untuk host aktif. Pembuatan host baru sebaiknya dilakukan dari context admin global.
</div>
@endif

@if ($canCreateScope)
<div class="text-right" style="margin-bottom: 15px;">
    <a href="{{ route('domain-family-scopes.create') }}" class="btn btn-primary btn-sm">Tambah Scope Domain</a>
</div>
@endif

<div class="panel panel-default table-responsive">
    <table class="table table-condensed">
        <thead>
            <tr>
                <th>Host</th>
                <th>CORE</th>
                <th>Status</th>
                <th class="text-center">Aksi</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($scopes as $scope)
            <tr>
                <td>{{ $scope->host }}</td>
                <td>{{ $scope->coreUser?->display_name }}</td>
                <td>{{ $scope->is_active ? 'Aktif' : 'Nonaktif' }}</td>
                <td class="text-center">
                    <a href="{{ route('domain-family-scopes.edit', $scope) }}" class="btn btn-warning btn-xs">Edit</a>
                    {{ Form::open(['route' => ['domain-family-scopes.destroy', $scope], 'method' => 'delete', 'style' => 'display:inline']) }}
                        <button type="submit" class="btn btn-danger btn-xs" onclick="return confirm('Hapus scope domain ini?')">Hapus</button>
                    {{ Form::close() }}
                </td>
            </tr>
            @empty
            <tr>
                <td colspan="4">Belum ada scope domain.</td>
            </tr>
            @endforelse
        </tbody>
    </table>
</div>

{{ $scopes->render() }}
@endsection
