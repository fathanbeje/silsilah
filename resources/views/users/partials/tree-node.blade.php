@php
    $hasOriginFamily = !empty($node['origin_family']) && (!empty($node['origin_family']['spouse']) || !empty($node['origin_family']['is_unmapped']));
    $supportRowCount = $node['spouse_labels']->count() + ($hasOriginFamily ? 1 : 0);
    $entryMinHeight = max(118, 96 + ($supportRowCount * 38));
@endphp

<div class="entry {{ $node['children']->count() === 1 ? 'sole' : '' }} {{ !empty($isRoot) ? 'entry-root' : '' }}" style="min-height: {{ $entryMinHeight }}px;">
    <div class="tree-node-card">
        <span class="label">{{ link_to_route('users.tree', $node['user']->name, [$node['user']->id], ['title' => $node['user']->name.' ('.$node['user']->gender.')']) }}</span>
        @if ($hasOriginFamily || $node['spouse_labels']->isNotEmpty())
        <div class="tree-node-card__supporting">
            @if ($hasOriginFamily)
            <div class="tree-node-card__origin">
                {{ trans('app.family_branch_origin') }}
                @if (!empty($node['origin_family']['spouse']))
                    {{ $node['origin_family']['spouse']->name }}
                @else
                    {{ trans('app.family_branch_unmapped_short') }}
                @endif
            </div>
            @endif
            @if ($node['spouse_labels']->isNotEmpty())
            <div class="tree-node-card__spouses">
                @foreach ($node['spouse_labels'] as $spouseLabel)
                <div class="tree-node-card__spouse">
                    {{ link_to_route('users.tree', $spouseLabel->name, [$spouseLabel->id], ['title' => $spouseLabel->name.' ('.$spouseLabel->gender.')']) }}
                </div>
                @endforeach
            </div>
            @endif
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
