@extends('layouts.app')

@section('content')
<h3 class="page-header">Bulk Edit Import</h3>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->has('workbook'))
    <div class="alert alert-danger">{{ $errors->first('workbook') }}</div>
@endif

<div class="row">
    <div class="col-md-5">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Upload Workbook</strong></div>
            <div class="panel-body">
                <p class="text-muted">Download template resmi, isi di Google Sheet, export ke <code>.xlsx</code>, lalu upload ke sini untuk staging review.</p>
                <p>
                    <a href="{{ route('bulk-edit-imports.template') }}" class="btn btn-default">Download Template</a>
                </p>
                <form method="POST" action="{{ route('bulk-edit-imports.store') }}" enctype="multipart/form-data">
                    {{ csrf_field() }}
                    <div class="form-group">
                        <label>Pilih file <code>.xlsx</code></label>
                        <input type="file" class="form-control" name="workbook" accept=".xlsx" required>
                    </div>
                    <button type="submit" class="btn btn-primary">Upload dan Parse</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-7">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Riwayat Import</strong></div>
            <div class="panel-body">
                @if ($imports->isEmpty())
                    <p class="text-muted">Belum ada import batch pada tenant ini.</p>
                @else
                    <table class="table table-bordered table-condensed">
                        <thead>
                            <tr>
                                <th>File</th>
                                <th>Status</th>
                                <th>Uploader</th>
                                <th>Dibuat</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($imports as $item)
                            <tr>
                                <td>{{ $item->source_name }}</td>
                                <td><span class="label label-info">{{ $item->status }}</span></td>
                                <td>{{ $item->uploader?->display_name ?: '-' }}</td>
                                <td>{{ $item->created_at }}</td>
                                <td class="text-right">
                                    <a href="{{ route('bulk-edit-imports.show', $item) }}" class="btn btn-default btn-xs">Review</a>
                                </td>
                            </tr>
                            @endforeach
                        </tbody>
                    </table>
                    {{ $imports->links() }}
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
