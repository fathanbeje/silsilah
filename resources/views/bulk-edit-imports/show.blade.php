@extends('layouts.app')

@section('content')
<h3 class="page-header">Review Bulk Edit Import</h3>

@if (session('status'))
    <div class="alert alert-success">{{ session('status') }}</div>
@endif

@if ($errors->has('bulk_edit_import'))
    <div class="alert alert-danger">{{ $errors->first('bulk_edit_import') }}</div>
@endif

<div class="row">
    <div class="col-md-4">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Info Import</strong></div>
            <div class="panel-body">
                <table class="table table-bordered table-condensed">
                    <tr><th>File</th><td>{{ $bulkEditImport->source_name }}</td></tr>
                    <tr><th>Host</th><td>{{ $bulkEditImport->tenant_host ?: '-' }}</td></tr>
                    <tr><th>Status</th><td><span class="label label-primary">{{ $bulkEditImport->status }}</span></td></tr>
                    <tr><th>Uploader</th><td>{{ $bulkEditImport->uploader?->display_name ?: '-' }}</td></tr>
                    <tr><th>Dibuat</th><td>{{ $bulkEditImport->created_at }}</td></tr>
                </table>

                @if (!empty($bulkEditImport->summary_json))
                    <table class="table table-condensed">
                        @foreach ($bulkEditImport->summary_json as $key => $count)
                            <tr><th>{{ $key }}</th><td>{{ $count }}</td></tr>
                        @endforeach
                    </table>
                @endif

                <form method="POST" action="{{ route('bulk-edit-imports.approve-ready', $bulkEditImport) }}">
                    {{ csrf_field() }}
                    <button type="submit" class="btn btn-success" onclick="return confirm('Approve semua row berstatus ready sekarang?')">Approve Semua Ready</button>
                    <a href="{{ route('bulk-edit-imports.index') }}" class="btn btn-default">Kembali</a>
                </form>
            </div>
        </div>

        <div class="panel panel-default">
            <div class="panel-heading"><strong>Filter</strong></div>
            <div class="panel-body">
                <form method="GET" action="{{ route('bulk-edit-imports.show', $bulkEditImport) }}">
                    <div class="form-group">
                        <label>Status</label>
                        <select name="status" class="form-control">
                            <option value="">Semua</option>
                            @foreach (['ready','needs_mapping','needs_anchor','blocked','duplicate','invalid','approved','rejected'] as $statusOption)
                                <option value="{{ $statusOption }}" {{ request('status') === $statusOption ? 'selected' : '' }}>{{ $statusOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Sheet</label>
                        <select name="sheet" class="form-control">
                            <option value="">Semua</option>
                            @foreach (['UPDATES_EXISTING','NEW_SPOUSES','NEW_CHILDREN','NEW_STANDALONE'] as $sheetOption)
                                <option value="{{ $sheetOption }}" {{ request('sheet') === $sheetOption ? 'selected' : '' }}>{{ $sheetOption }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm">Terapkan Filter</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        @forelse ($rows as $row)
            @php
                $payload = $row->payload_json ?? [];
                $resolution = $row->resolution_json ?? [];
            @endphp
            <div class="panel panel-default">
                <div class="panel-heading">
                    <strong>{{ $row->sheet_name }}</strong>
                    <span class="label label-default">{{ $row->row_type }}</span>
                    <span class="label label-{{ $row->status === 'ready' ? 'success' : ($row->status === 'approved' ? 'primary' : ($row->status === 'rejected' ? 'default' : 'warning')) }}">{{ $row->status }}</span>
                    <span class="pull-right">Row {{ $row->row_number }} | Key: {{ $row->row_key ?: '-' }}</span>
                </div>
                <div class="panel-body">
                    @if (!empty($row->error_messages_json))
                        <div class="alert alert-warning">
                            <ul style="margin-bottom:0;padding-left:18px;">
                                @foreach ($row->error_messages_json as $message)
                                    <li>{{ $message }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    @if ($row->targetUser)
                        <p><strong>Target:</strong> {{ $row->targetUser->display_name }} <code>{{ $row->targetUser->id }}</code></p>
                    @endif

                    @if ($row->row_type === 'existing_update')
                        <div class="table-responsive">
                            <table class="table table-bordered table-condensed">
                                <thead>
                                    <tr>
                                        <th>Field</th>
                                        <th>Saat Ini</th>
                                        <th>Usulan</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach (($payload['profile'] ?? []) as $field => $value)
                                        <tr>
                                            <td>{{ $field }}</td>
                                            <td>{{ $row->targetUser?->{$field} }}</td>
                                            <td>{{ is_bool($value) ? ($value ? '1' : '0') : $value }}</td>
                                        </tr>
                                    @endforeach
                                    @foreach (($payload['metadata'] ?? []) as $field => $value)
                                        <tr>
                                            <td>{{ $field }}</td>
                                            <td>{{ $row->targetUser?->getMetadata($field) }}</td>
                                            <td>{{ $value }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <pre style="white-space: pre-wrap;">{{ json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
                    @endif

                    @if ($row->status === 'needs_mapping')
                        <form method="POST" action="{{ route('bulk-edit-imports.rows.update', [$bulkEditImport, $row]) }}" class="well well-sm">
                            {{ csrf_field() }}
                            {{ method_field('PATCH') }}
                            <div class="form-group">
                                <label>Pilih target_user_id</label>
                                <select name="resolved_target_user_id" class="form-control">
                                    <option value="">Pilih target user</option>
                                    @foreach ($visibleUserOptions as $option)
                                        <option value="{{ $option['id'] }}" {{ ($resolution['resolved_target_user_id'] ?? '') === $option['id'] ? 'selected' : '' }}>{{ $option['label'] }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Simpan Mapping</button>
                        </form>
                    @endif

                    @if ($row->status === 'needs_anchor')
                        <form method="POST" action="{{ route('bulk-edit-imports.rows.update', [$bulkEditImport, $row]) }}" class="well well-sm">
                            {{ csrf_field() }}
                            {{ method_field('PATCH') }}
                            <div class="row">
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Anchor Type</label>
                                        <select name="resolved_anchor_type" class="form-control">
                                            <option value="">Pilih</option>
                                            <option value="user" {{ ($resolution['resolved_anchor_type'] ?? $resolution['suggested_anchor_type'] ?? '') === 'user' ? 'selected' : '' }}>user</option>
                                            <option value="couple" {{ ($resolution['resolved_anchor_type'] ?? $resolution['suggested_anchor_type'] ?? '') === 'couple' ? 'selected' : '' }}>couple</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Anchor User</label>
                                        <select name="resolved_anchor_ref_id" class="form-control">
                                            <option value="">Pilih user/couple sesuai type</option>
                                            @foreach ($visibleUserOptions as $option)
                                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                            @endforeach
                                            @foreach ($visibleCoupleOptions as $option)
                                                <option value="{{ $option['id'] }}">{{ $option['label'] }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </div>
                                <div class="col-sm-4">
                                    <div class="form-group">
                                        <label>Aksi Relasi</label>
                                        <select name="resolved_relation_action" class="form-control">
                                            <option value="">Pilih</option>
                                            <option value="child" {{ ($resolution['resolved_relation_action'] ?? '') === 'child' ? 'selected' : '' }}>child</option>
                                            <option value="spouse" {{ ($resolution['resolved_relation_action'] ?? '') === 'spouse' ? 'selected' : '' }}>spouse</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            <button type="submit" class="btn btn-primary btn-sm">Simpan Anchor</button>
                        </form>
                    @endif

                    <div class="clearfix">
                        @if ($row->status === 'ready')
                            <form method="POST" action="{{ route('bulk-edit-imports.rows.approve', [$bulkEditImport, $row]) }}" style="display:inline-block;">
                                {{ csrf_field() }}
                                <button type="submit" class="btn btn-success btn-sm">Approve Row</button>
                            </form>
                        @endif

                        @if (!in_array($row->status, ['approved', 'rejected'], true))
                            <form method="POST" action="{{ route('bulk-edit-imports.rows.reject', [$bulkEditImport, $row]) }}" style="display:inline-block;">
                                {{ csrf_field() }}
                                <button type="submit" class="btn btn-danger btn-sm">Reject Row</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @empty
            <div class="panel panel-default">
                <div class="panel-body text-muted">Tidak ada row yang cocok dengan filter saat ini.</div>
            </div>
        @endforelse
    </div>
</div>
@endsection
