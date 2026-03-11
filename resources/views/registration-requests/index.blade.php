@extends('layouts.app')

@section('content')
<h2 class="page-header">Permintaan Registrasi</h2>

@if (session('status'))
<div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="panel panel-default">
    <div class="panel-heading">Daftar Permintaan</div>
    <div class="panel-body table-responsive">
        <table class="table table-striped table-bordered">
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Tgl Lahir Dikirim</th>
                    <th>Catatan</th>
                    <th>Status</th>
                    <th>Dikirim</th>
                    <th>Aksi</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $item)
                <tr>
                    <td>
                        <div><strong>{{ $item->name }}</strong></div>
                        <div>{{ link_to_route('users.chart', trans('app.show_family_chart'), [$item->user_id]) }}</div>
                    </td>
                    <td>{{ $item->email }}</td>
                    <td>{{ optional($item->requested_birth_date)->format('Y-m-d') ?: '-' }}</td>
                    <td>{{ $item->notes ?: '-' }}</td>
                    <td>
                        <span class="label label-{{ $item->status === 'pending' ? 'danger' : ($item->status === 'reviewed' ? 'success' : 'default') }}">
                            {{ strtoupper($item->status) }}
                        </span>
                    </td>
                    <td>{{ $item->created_at->format('Y-m-d H:i') }}</td>
                    <td>
                        <form method="POST" action="{{ route('registration-requests.update', $item) }}" style="display:inline-block">
                            {{ csrf_field() }}
                            {{ method_field('PATCH') }}
                            <input type="hidden" name="status" value="reviewed">
                            <button type="submit" class="btn btn-xs btn-success">Tandai Ditinjau</button>
                        </form>
                        <form method="POST" action="{{ route('registration-requests.update', $item) }}" style="display:inline-block">
                            {{ csrf_field() }}
                            {{ method_field('PATCH') }}
                            <input type="hidden" name="status" value="rejected">
                            <button type="submit" class="btn btn-xs btn-default">Tolak</button>
                        </form>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center">Belum ada permintaan registrasi.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $requests->render() }}
@endsection
