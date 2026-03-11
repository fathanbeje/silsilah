@if (!empty($publicView))
<a
    href="{{ route('users.chart', $linkedUser) }}"
    class="js-public-edit-trigger"
    data-edit-form-url="{{ route('user-edit-requests.create', $linkedUser) }}"
    data-user-name="{{ $linkedUser->name }}"
>
    {{ $linkedUser->name }}
</a>
@else
    {{ $linkedUser->profileLink('chart') }}
@endif
