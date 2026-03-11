@php
    $displaySpouseLabels = !empty($isRoot) ? $node['spouse_labels'] : collect();
    $supportRowCount = $displaySpouseLabels->count();
    $entryMinHeight = max(74, 54 + ($supportRowCount * 46));
@endphp

<div class="entry {{ $node['children']->count() === 1 ? 'sole' : '' }} {{ !empty($isRoot) ? 'entry-root' : '' }}" style="min-height: {{ $entryMinHeight }}px;">
    <div class="tree-node-card">
        <span class="label">{{ link_to_route('users.tree', $node['user']->name, [$node['user']->id], ['title' => $node['user']->name.' ('.$node['user']->gender.')']) }}</span>
        @if ($displaySpouseLabels->isNotEmpty())
        <div class="tree-node-card__supporting">
            <div class="tree-node-card__spouses">
                @foreach ($displaySpouseLabels as $spouseLabel)
                <div class="tree-node-card__spouse">
                    {{ link_to_route('users.tree', $spouseLabel->name, [$spouseLabel->id], ['title' => $spouseLabel->name.' ('.$spouseLabel->gender.')']) }}
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>

    @if ($node['children']->isNotEmpty())
    <div class="branch lv{{ $level }}">
        @foreach ($node['children'] as $childNode)
            @include('users.partials.tree-node', ['node' => $childNode, 'level' => $level + 1, 'isRoot' => false])
        @endforeach
    </div>
    @endif
</div>
