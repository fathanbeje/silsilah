@if (!empty($allowPublicEditSuggestions))
<a
    href="{{ route('users.chart', $linkedUser) }}"
    class="js-public-edit-trigger"
    data-edit-form-url="{{ route('user-edit-requests.create', $linkedUser) }}"
    data-user-name="{{ $linkedUser->display_name }}"
>
    {{ $linkedUser->display_name }}
</a>
@else
    {{ link_to_route('users.show', $linkedUser->display_name, [$linkedUser->id]) }}
@endif
