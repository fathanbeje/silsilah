@extends('layouts.user-profile')

@section('subtitle', trans('user.profile'))

@section('user-content')
    <div class="row">
        <div class="col-md-4">
            @include('users.partials.profile')
        </div>
        <div class="col-md-8">
            @include('users.partials.parent-spouse')
            @include('users.partials.childs')
            @include('users.partials.siblings')
        </div>
    </div>
@endsection

@section ('ext_css')
<link rel="stylesheet" href="{{ asset('css/plugins/select2.min.css') }}">
<link rel="stylesheet" href="{{ asset('css/plugins/jquery.datetimepicker.css') }}">
@endsection

@section ('ext_js')
<script src="{{ asset('js/plugins/select2.min.js') }}"></script>
<script src="{{ asset('js/plugins/jquery.datetimepicker.js') }}"></script>
@endsection

@section ('script')
<script>
(function () {
    var matcher = function(params, data) {
        if ($.trim(params.term) === '') {
            return data;
        }

        if (typeof data.text === 'undefined') {
            return null;
        }

        var searchTerms = params.term.toLowerCase().split(/\s+/).filter(Boolean);
        var candidateText = data.text.toLowerCase();
        var isMatch = searchTerms.every(function(term) {
            return candidateText.indexOf(term) > -1;
        });

        return isMatch ? data : null;
    };

    $('select').select2({
        matcher: matcher
    });

    $('input[name=marriage_date]').datetimepicker({
        timepicker:false,
        format:'Y-m-d',
        closeOnDateSelect: true,
        scrollInput: false
    });
})();
</script>
@endsection
