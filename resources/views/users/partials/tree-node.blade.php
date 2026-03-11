@php
    $displaySpouseLabels = $node['spouse_labels'];
    $supportRowCount = $displaySpouseLabels->count();
    $entryMinHeight = max(64, 50 + ($supportRowCount * 18));
    $hasChildren = $node['has_children'];
    $isRootNode = !empty($isRoot);
    $isExpanded = $node['default_expanded'];
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
                {{ link_to_route('users.tree', $node['user']->name, [$node['user']->id], ['title' => $node['user']->name.' ('.$node['user']->gender.')']) }}
            </div>
            @if ($hasChildren && !$isRootNode)
            <span class="tree-node-box__toggle-indicator" aria-hidden="true"></span>
            @endif
            @if ($displaySpouseLabels->isNotEmpty())
            <div class="tree-node-box__spouses">
                @foreach ($displaySpouseLabels as $spouseLabel)
                <div class="tree-node-box__spouse">
                    <span class="tree-node-box__spouse-prefix">+</span>
                    {{ link_to_route('users.tree', $spouseLabel->name, [$spouseLabel->id], ['title' => $spouseLabel->name.' ('.$spouseLabel->gender.')']) }}
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
