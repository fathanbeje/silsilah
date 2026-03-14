@php
    $displaySpouseLabels = $node['spouse_labels'];
    $supportRowCount = $displaySpouseLabels->count();
    $hasChildren = $node['has_children'];
    $isRootNode = !empty($isRoot);
    $isExpanded = $node['default_expanded'];
    $entryMinHeight = max(64, 50 + ($supportRowCount * 18));
    $user = $node['user'];
    $formatTreeStatus = static function ($person) {
        if ($person->isDeceased()) {
            $deathYear = optional($person->dod)->format('Y') ?: $person->yod;

            return $deathYear ? 'Wafat - '.$deathYear : 'Wafat';
        }

        return !is_null($person->age) ? 'Hidup - '.$person->age.' Thn' : 'Hidup';
    };
    $userStatusLabel = $formatTreeStatus($user);
@endphp

<div
    class="entry {{ $node['children']->count() === 1 ? 'sole' : '' }} {{ $isRootNode ? 'entry-root' : '' }}"
    data-tree-entry
    data-node-id="{{ $node['node_id'] }}"
    data-node-depth="{{ $node['node_depth'] }}"
    data-has-children="{{ $hasChildren ? 'true' : 'false' }}"
    data-expanded="{{ $isExpanded ? 'true' : 'false' }}"
    data-entry-base-height="{{ $entryMinHeight }}"
    style="min-height: {{ $entryMinHeight }}px;"
>
    <div class="tree-node-card" data-tree-card>
        <div
            class="tree-node-box"
            data-tree-box
            @if ($hasChildren && !$isRootNode) data-tree-toggle="true" role="button" tabindex="0" aria-expanded="{{ $isExpanded ? 'true' : 'false' }}" @endif
        >
            <div class="tree-node-box__primary tree-person-line">
                <span
                    class="tree-person-line__preview {{ $user->isDeceased() ? 'is-deceased' : 'is-alive' }}"
                    data-tree-preview
                >
                    <button
                        type="button"
                        class="tree-person-line__avatar"
                        data-tree-preview-trigger
                        aria-expanded="false"
                        aria-label="Lihat foto {{ $user->display_name }}"
                    >
                        <img
                            src="{{ userPhotoPath($user->photo_path, $user->gender_id) }}"
                            alt="{{ $user->display_name }}"
                            loading="lazy"
                        >
                    </button>
                    <span class="tree-person-line__popup" data-tree-preview-popup role="dialog" aria-hidden="true">
                        <img
                            src="{{ userPhotoPath($user->photo_path, $user->gender_id) }}"
                            alt="{{ $user->display_name }}"
                            class="tree-person-line__popup-photo"
                            loading="lazy"
                        >
                        <span class="tree-person-line__popup-name">{{ $user->display_name }}</span>
                        <span class="tree-person-line__popup-status {{ $user->isDeceased() ? 'is-deceased' : 'is-alive' }}">
                            {{ $userStatusLabel }}
                        </span>
                    </span>
                </span>
                <span class="tree-person-line__name">
                    {{ link_to_route('users.tree', $user->display_name, [$user->id], ['title' => $user->displayNameWithGender()]) }}
                </span>
            </div>
            @if ($hasChildren && !$isRootNode)
            <span class="tree-node-box__toggle-indicator" aria-hidden="true"></span>
            @endif
            @if ($displaySpouseLabels->isNotEmpty())
            <div class="tree-node-box__spouses">
                @foreach ($displaySpouseLabels as $spouseLabel)
                @php
                    $spouseStatusLabel = $formatTreeStatus($spouseLabel);
                @endphp
                <div class="tree-node-box__spouse">
                    <span class="tree-node-box__spouse-prefix">+</span>
                    <span class="tree-person-line tree-person-line--spouse">
                        <span
                            class="tree-person-line__preview {{ $spouseLabel->isDeceased() ? 'is-deceased' : 'is-alive' }}"
                            data-tree-preview
                        >
                            <button
                                type="button"
                                class="tree-person-line__avatar"
                                data-tree-preview-trigger
                                aria-expanded="false"
                                aria-label="Lihat foto {{ $spouseLabel->display_name }}"
                            >
                                <img
                                    src="{{ userPhotoPath($spouseLabel->photo_path, $spouseLabel->gender_id) }}"
                                    alt="{{ $spouseLabel->display_name }}"
                                    loading="lazy"
                                >
                            </button>
                            <span class="tree-person-line__popup" data-tree-preview-popup role="dialog" aria-hidden="true">
                                <img
                                    src="{{ userPhotoPath($spouseLabel->photo_path, $spouseLabel->gender_id) }}"
                                    alt="{{ $spouseLabel->display_name }}"
                                    class="tree-person-line__popup-photo"
                                    loading="lazy"
                                >
                                <span class="tree-person-line__popup-name">{{ $spouseLabel->display_name }}</span>
                                <span class="tree-person-line__popup-status {{ $spouseLabel->isDeceased() ? 'is-deceased' : 'is-alive' }}">
                                    {{ $spouseStatusLabel }}
                                </span>
                            </span>
                        </span>
                        <span class="tree-person-line__name">
                            {{ link_to_route('users.tree', $spouseLabel->display_name, [$spouseLabel->id], ['title' => $spouseLabel->displayNameWithGender()]) }}
                        </span>
                    </span>
                </div>
                @endforeach
            </div>
            @endif
        </div>
    </div>

    @if ($node['children']->isNotEmpty())
    <div
        class="branch lv{{ $level }} {{ $node['children']->count() === 1 ? 'branch--single' : '' }}"
        data-tree-branch
        @if (!$isExpanded) hidden @endif
    >
        @foreach ($node['children'] as $childNode)
            @include('users.partials.tree-node', ['node' => $childNode, 'level' => $level + 1, 'isRoot' => false])
        @endforeach
    </div>
    @endif
</div>
