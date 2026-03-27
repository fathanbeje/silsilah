@if (!empty($allowPublicEditSuggestions))
<a
    href="{{ route('users.chart', $linkedUser) }}"
    class="js-public-edit-trigger public-chart-edit-link"
    data-edit-form-url="{{ route('user-edit-requests.create', $linkedUser) }}"
    data-user-name="{{ $linkedUser->display_name }}"
    title="Klik untuk usulkan perubahan data {{ $linkedUser->display_name }}"
    aria-label="Klik untuk usulkan perubahan data {{ $linkedUser->display_name }}"
>
    {{ $linkedUser->display_name }}
</a>
@else
    {{ link_to_route('users.show', $linkedUser->display_name, [$linkedUser->id]) }}
@endif
