@php
    $displaySpouseLabels = $node['spouse_labels'];
    $supportRowCount = $displaySpouseLabels->count();
    $entryMinHeight = max(78, 58 + ($supportRowCount * 24));
@endphp

<div class="entry {{ $node['children']->count() === 1 ? 'sole' : '' }} {{ !empty($isRoot) ? 'entry-root' : '' }}" data-tree-entry style="min-height: {{ $entryMinHeight }}px;">
    <div class="tree-node-card" data-tree-card>
        <div class="tree-node-box" data-tree-box>
            <div class="tree-node-box__primary">
                {{ link_to_route('users.tree', $node['user']->name, [$node['user']->id], ['title' => $node['user']->name.' ('.$node['user']->gender.')']) }}
            </div>
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
    <div class="branch lv{{ $level }} {{ $node['children']->count() === 1 ? 'branch--single' : '' }}" data-tree-branch>
        @foreach ($node['children'] as $childNode)
            @include('users.partials.tree-node', ['node' => $childNode, 'level' => $level + 1, 'isRoot' => false])
        @endforeach
    </div>
    @endif
</div>
