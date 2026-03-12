@extends('layouts.app')

@section('content')
<h2 class="page-header">
    {{ trans('app.search_your_family') }}
    @if (request('q'))
    <small class="pull-right">{!! trans('app.user_found', ['total' => $users->total(), 'keyword' => request('q')]) !!}</small>
    @endif
</h2>

@if (session('status'))
<div class="alert alert-success">{{ session('status') }}</div>
@endif

{{ Form::open(['method' => 'get','class' => '']) }}
<div class="input-group" style="position: relative;">
    {{ Form::text('q', request('q'), ['class' => 'form-control', 'placeholder' => trans('app.search_your_family_placeholder'), 'autocomplete' => 'off', 'id' => 'family-search-input']) }}
    <span class="input-group-btn">
        {{ Form::submit(trans('app.search'), ['class' => 'btn btn-default']) }}
        {{ link_to_route('users.search', 'Reset', [], ['class' => 'btn btn-default']) }}
    </span>
    <div id="family-search-autocomplete" class="list-group" style="display:none; position:absolute; top:100%; left:0; right:110px; z-index:20; margin-top:4px;"></div>
</div>
{{ Form::close() }}

@if (request('q'))
<br>
{{ $users->appends(Request::except('page'))->render() }}
@foreach ($users->chunk(4) as $chunkedUser)
<div class="row">
    @foreach ($chunkedUser as $user)
    <div class="col-md-3">
        <div class="panel panel-default">
            <div class="panel-heading text-center">
                {{ userPhoto($user, ['style' => 'width:100%;max-width:300px']) }}
                @if ($user->age)
                    {!! $user->age_string !!}
                @endif
            </div>
            <div class="panel-body">
                <h3 class="panel-title">{{ link_to_route('users.chart', $user->display_name, [$user->id]) }} <span>({{ $user->gender }})</span></h3>
                <div>{{ trans('user.nickname') }} : {{ $user->nickname }}</div>
                <hr style="margin: 5px 0;">
                <div>{{ trans('user.father') }} : {{ optional($user->father)->display_name }}</div>
                <div>{{ trans('user.mother') }} : {{ optional($user->mother)->display_name }}</div>
            </div>
            <div class="panel-footer">
                {{ link_to_route('users.chart', trans('app.show_family_chart'), [$user->id], ['class' => 'btn btn-default btn-xs']) }}
            </div>
        </div>
    </div>
    @endforeach
</div>
@endforeach

{{ $users->appends(Request::except('page'))->render() }}
@endif
@endsection

@section('script')
<script>
    (function () {
        var input = document.getElementById('family-search-input');
        var list = document.getElementById('family-search-autocomplete');
        var timer = null;

        if (!input || !list) {
            return;
        }

        function hideList() {
            list.style.display = 'none';
            list.innerHTML = '';
        }

        function renderItems(items) {
            if (!items.length) {
                hideList();
                return;
            }

            function escapeHtml(value) {
                return String(value || '').replace(/[&<>"']/g, function (char) {
                    return {
                        '&': '&amp;',
                        '<': '&lt;',
                        '>': '&gt;',
                        '"': '&quot;',
                        "'": '&#39;'
                    }[char];
                });
            }

            list.innerHTML = items.map(function (item) {
                var parents = item.parents ? '<div class="small text-muted">' + escapeHtml(item.parents) + '</div>' : '';
                return '<a class="list-group-item" href="' + item.chart_url + '">' +
                    '<strong>' + escapeHtml(item.name) + '</strong> (' + escapeHtml(item.gender) + ')' +
                    '<div>' + escapeHtml(item.nickname) + '</div>' +
                    parents +
                '</a>';
            }).join('');
            list.style.display = 'block';
        }

        input.addEventListener('input', function () {
            var value = input.value.trim();
            window.clearTimeout(timer);

            if (value.length < 2) {
                hideList();
                return;
            }

            timer = window.setTimeout(function () {
                window.fetch('{{ route('users.autocomplete') }}?q=' + encodeURIComponent(value), {
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                    .then(function (response) { return response.json(); })
                    .then(renderItems)
                    .catch(hideList);
            }, 180);
        });

        document.addEventListener('click', function (event) {
            if (!list.contains(event.target) && event.target !== input) {
                hideList();
            }
        });
    })();
</script>
@endsection
