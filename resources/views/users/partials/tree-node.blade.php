@php
    $displaySpouseLabels = $node['spouse_labels'];
    $supportRowCount = $displaySpouseLabels->count();
    $hasChildren = $node['has_children'];
    $isRootNode = !empty($isRoot);
    $isExpanded = $node['default_expanded'];
    $rootActionHeight = $isRootNode && $hasChildren ? 44 : 0;
    $entryMinHeight = max(64, 50 + ($supportRowCount * 18) + $rootActionHeight);
@endphp

<div
    class="entry {{ $node['children']->count() === 1 ? 'sole' : '' }} {{ $isRootNode ? 'entry-root' : '' }}"
    data-tree-entry
    data-node-id="{{ $node['node_id'] }}"
    data-node-depth="{{ $node['node_depth'] }}"
    data-has-children="{{ $hasChildren ? 'true' : 'false' }}"
    data-expanded="{{ $isExpanded ? 'true' : 'false' }}"
    style="min-height: {{ $entryMinHeight }}px;"
>
    <div class="tree-node-card" data-tree-card>
        <div
            class="tree-node-box"
            data-tree-box
            @if ($hasChildren && !$isRootNode) data-tree-toggle="true" role="button" tabindex="0" aria-expanded="{{ $isExpanded ? 'true' : 'false' }}" @endif
        >
            <div class="tree-node-box__primary">
                {{ link_to_route('users.tree', $node['user']->display_name, [$node['user']->id], ['title' => $node['user']->displayNameWithGender()]) }}
            </div>
            @if ($hasChildren && !$isRootNode)
            <span class="tree-node-box__toggle-indicator" aria-hidden="true"></span>
            @endif
            @if ($displaySpouseLabels->isNotEmpty())
            <div class="tree-node-box__spouses">
                @foreach ($displaySpouseLabels as $spouseLabel)
                <div class="tree-node-box__spouse">
                    <span class="tree-node-box__spouse-prefix">+</span>
                    {{ link_to_route('users.tree', $spouseLabel->display_name, [$spouseLabel->id], ['title' => $spouseLabel->displayNameWithGender()]) }}
                </div>
                @endforeach
            </div>
            @endif
            @if ($isRootNode && $hasChildren)
            <div class="tree-node-box__root-actions" data-tree-root-actions>
                <button
                    type="button"
                    class="btn btn-default btn-xs tree-node-box__root-action"
                    data-tree-bulk-action="collapse"
                >Collapse Semua</button>
                <button
                    type="button"
                    class="btn btn-primary btn-xs tree-node-box__root-action"
                    data-tree-bulk-action="expand"
                >Expand Semua</button>
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
