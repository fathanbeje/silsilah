@extends('layouts.app')

@section('content')
<h3 class="page-header">Brand Header</h3>

@if (session('status'))
<div class="alert alert-success">{{ session('status') }}</div>
@endif

<div class="row">
    <div class="col-md-7">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Pengaturan Header Situs</strong></div>
            <div class="panel-body">
                <form method="POST" action="{{ route('app-settings.update') }}">
                    {{ csrf_field() }}
                    {{ method_field('PATCH') }}

                    <div class="form-group{{ $errors->has('site_header_name') ? ' has-error' : '' }}">
                        <label for="site_header_name">Nama header</label>
                        <input
                            id="site_header_name"
                            type="text"
                            name="site_header_name"
                            class="form-control"
                            value="{{ old('site_header_name', $headerName) }}"
                            maxlength="120"
                            required
                        >
                        <p class="help-block">Contoh: <strong>Silsilah Bani Syamsuri</strong>. Teks ini akan tampil di navbar dan judul tab browser.</p>
                        @if ($errors->has('site_header_name'))
                        <span class="help-block"><strong>{{ $errors->first('site_header_name') }}</strong></span>
                        @endif
                    </div>

                    <button type="submit" class="btn btn-primary">Simpan Header</button>
                </form>
            </div>
        </div>
    </div>

    <div class="col-md-5">
        <div class="panel panel-default">
            <div class="panel-heading"><strong>Preview</strong></div>
            <div class="panel-body">
                <div style="padding:18px 20px;border-radius:18px;background:linear-gradient(135deg,#f7f2df,#f3f5f8);border:1px solid #e8e0c7;">
                    <div style="font-family:'Berkshire Swash','Palatino Linotype',serif;font-size:30px;line-height:1.2;color:#2f2716;letter-spacing:.02em;">
                        {{ old('site_header_name', $headerName) }}
                    </div>
                    <div style="margin-top:8px;color:#6e6655;font-size:13px;">
                        Gaya judul kaligrafi dibuat dekoratif, tetapi tetap dijaga agar terbaca jelas di desktop dan mobile.
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
