@extends('layouts.app')

@section('title', 'Deploy Sync')

@section('content')
<h3 class="page-header">Deploy Sync</h3>

@if (session('success'))
    <div class="alert alert-success" style="white-space: pre-wrap;">{{ session('success') }}</div>
@endif

@if (session('error'))
    <div class="alert alert-danger" style="white-space: pre-wrap;">{{ session('error') }}</div>
@endif

<div class="row">
    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">Status</h3></div>
            <div class="panel-body">
                <table class="table table-bordered table-condensed">
                    <tr>
                        <th style="width: 180px;">Branch</th>
                        <td>{{ $gitStatus['branch'] ?? '-' }}</td>
                    </tr>
                    <tr>
                        <th>Current Commit</th>
                        <td><code>{{ $gitStatus['commit'] ?? '-' }}</code></td>
                    </tr>
                    <tr>
                        <th>Remote Commit</th>
                        <td><code>{{ $gitStatus['remote_commit'] ?? '-' }}</code></td>
                    </tr>
                    <tr>
                        <th>Working Tree</th>
                        <td>
                            @if (!empty($gitStatus['dirty']))
                                <span class="text-danger">Dirty</span>
                            @else
                                <span class="text-success">Clean</span>
                            @endif
                        </td>
                    </tr>
                </table>

                @if (!empty($gitStatus['dirty']))
                    <div class="alert alert-warning">
                        <strong>Working tree is dirty.</strong><br>
                        <pre style="margin-top: 10px;">{{ $gitStatus['dirty'] }}</pre>
                    </div>
                @endif

                <form method="POST" action="{{ route('deploy-sync.run') }}">
                    {{ csrf_field() }}
                    <button type="submit" class="btn btn-primary" onclick="return confirm('Jalankan git pull/sync di VPS sekarang?')">
                        Pull / Sync From Git
                    </button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="panel panel-default">
            <div class="panel-heading"><h3 class="panel-title">Last Sync Log</h3></div>
            <div class="panel-body">
                @if ($syncLog)
                    <pre style="white-space: pre-wrap;">{{ $syncLog }}</pre>
                @else
                    <p class="text-muted">Belum ada log sync.</p>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
