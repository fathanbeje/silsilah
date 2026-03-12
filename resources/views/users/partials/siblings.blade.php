<div class="panel panel-default">
    <div class="panel-heading"><h3 class="panel-title">{{ trans('user.siblings') }}</h3></div>
    <table class="table">
        <tbody>
            @foreach(($siblings ?? $user->siblings()) as $sibling)
            <tr>
                <td>
                    {{ $sibling->profileLink() }} <span>({{ $sibling->gender }})</span>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
</div>
