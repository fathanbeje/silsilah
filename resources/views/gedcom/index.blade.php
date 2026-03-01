@extends('layouts.app')

@section('content')
    <h2 class="page-header">Import GEDCOM</h2>

    <div class="row">
        <div class="col-md-6">
            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            <div class="panel panel-default">
                <div class="panel-heading">Upload File .ged</div>
                <div class="panel-body">
                    <form action="{{ route('gedcom.store') }}" method="POST" enctype="multipart/form-data">
                        {{ csrf_field() }}
                        <div class="form-group">
                            <label for="gedcom">Pilih File GEDCOM</label>
                            <input type="file" name="gedcom" id="gedcom" class="form-control" required accept=".ged">
                            <p class="help-block">Mendukung file GEDCOM standar (.ged) untuk diimpor ke data silsilah
                                keluarga Anda.</p>
                        </div>
                        <button type="submit" class="btn btn-primary">Mulai Import</button>
                        <a href="{{ url('/') }}" class="btn btn-default">Kembali</a>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection