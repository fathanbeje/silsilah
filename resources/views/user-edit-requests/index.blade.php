@extends('layouts.app')

@section('ext_css')
<link rel="stylesheet" href="{{ secure_asset('css/user-edit-requests.css') }}">
@endsection

@section('content')
<h2 class="page-header">Peninjauan Edit Keluarga</h2>

@if (session('status'))
<div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="panel panel-default">
    <div class="panel-body">
        <form method="GET" class="row">
            <div class="col-sm-3">
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" class="form-control">
                        <option value="">Semua</option>
                        <option value="pending" @selected(request('status') === 'pending')>Pending</option>
                        <option value="approved" @selected(request('status') === 'approved')>Approved</option>
                        <option value="rejected" @selected(request('status') === 'rejected')>Rejected</option>
                    </select>
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    <label>Nama target</label>
                    <input type="text" name="target" value="{{ request('target') }}" class="form-control">
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    <label>Nama pengaju</label>
                    <input type="text" name="requester" value="{{ request('requester') }}" class="form-control">
                </div>
            </div>
            <div class="col-sm-3">
                <div class="form-group">
                    <label>&nbsp;</label>
                    <div>
                        <button type="submit" class="btn btn-primary">Filter</button>
                        <a href="{{ route('user-edit-requests.index') }}" class="btn btn-default">Reset</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<div class="panel panel-default user-edit-review-panel">
    <div class="panel-body table-responsive" style="padding:0;">
        <table class="table table-hover user-edit-review-table">
            <thead>
                <tr>
                    <th>Target</th>
                    <th>Ringkasan</th>
                    <th>Pengaju</th>
                    <th>WA</th>
                    <th>Status</th>
                    <th>Dikirim</th>
                    <th>Reviewer</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($requests as $item)
                <tr class="user-edit-review-row" data-detail-url="{{ route('user-edit-requests.show', $item) }}">
                    <td>
                        <strong>{{ optional($item->targetUser)->display_name ?: '-' }}</strong>
                        <div class="small text-muted">{{ optional($item->targetUser)->nickname ?: '-' }}</div>
                    </td>
                    <td>{{ implode(', ', $item->summaryParts()) ?: 'Perubahan umum' }}</td>
                    <td>{{ $item->requester_name }}</td>
                    <td>{{ $item->requester_whatsapp }}</td>
                    <td><span class="label label-{{ $item->status === 'pending' ? 'warning' : ($item->status === 'approved' ? 'success' : 'default') }}">{{ strtoupper($item->status) }}</span></td>
                    <td>{{ optional($item->submitted_at ?: $item->created_at)->format('Y-m-d H:i') }}</td>
                    <td>{{ optional($item->reviewer)->display_name ?: '-' }}</td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center">Belum ada usulan edit.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{ $requests->render() }}

<div class="user-edit-drawer-backdrop" id="user-edit-drawer-backdrop"></div>
<aside class="user-edit-drawer" id="user-edit-drawer">
    <div class="user-edit-drawer__header">
        <h4 class="user-edit-drawer__title">Detail Usulan Edit</h4>
        <button type="button" class="close" id="user-edit-drawer-close" aria-label="Tutup"><span aria-hidden="true">&times;</span></button>
    </div>
    <div class="user-edit-drawer__body" id="user-edit-drawer-body">
        <div class="text-muted text-center" style="padding:32px 0;">Klik salah satu baris untuk melihat detail usulan.</div>
    </div>
</aside>
@endsection

@section('script')
<script>
    (function () {
        var drawer = document.getElementById('user-edit-drawer');
        var body = document.getElementById('user-edit-drawer-body');
        var backdrop = document.getElementById('user-edit-drawer-backdrop');
        var closeButton = document.getElementById('user-edit-drawer-close');

        if (!drawer || !body || !backdrop || !closeButton) {
            return;
        }

        function closeDrawer() {
            drawer.classList.remove('is-open');
            backdrop.classList.remove('is-open');
        }

        function openDrawer() {
            drawer.classList.add('is-open');
            backdrop.classList.add('is-open');
        }

        document.addEventListener('click', function (event) {
            var row = event.target.closest('.user-edit-review-row');
            if (!row) {
                return;
            }

            body.innerHTML = '<div class="text-muted text-center" style="padding:32px 0;">Memuat detail usulan...</div>';
            openDrawer();

            window.fetch(row.getAttribute('data-detail-url'), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (response) { return response.text(); })
                .then(function (html) {
                    body.innerHTML = html;
                })
                .catch(function () {
                    body.innerHTML = '<div class="alert alert-danger">Detail usulan tidak bisa dimuat.</div>';
                });
        });

        closeButton.addEventListener('click', closeDrawer);
        backdrop.addEventListener('click', closeDrawer);
    })();
</script>
@endsection
