<div class="panel panel-default table-responsive">
    <table class="table table-bordered table-striped">
        <tbody>
            <tr>
                <th style="width: 35%">{{ trans('user.siblings') }}</th>
                <th class="text-center">
                    {{ $siblingCard['user']->profileLink('chart') }} ({{ $siblingCard['user']->gender }})
                    @if ($siblingCard['spouse_labels']->isNotEmpty())
                    <div class="family-member-card__meta">
                        @foreach ($siblingCard['spouse_labels'] as $spouseLabel)
                        <span class="family-chip">{{ $spouseLabel->profileLink('chart') }} ({{ $spouseLabel->gender }})</span>
                        @endforeach
                    </div>
                    @endif
                </th>
            </tr>
            <tr>
                <th>{{ trans('user.nieces') }} & {{ trans('user.grand_childs') }}</th>
                <td>
                    @forelse($siblingCard['family_groups'] as $familyGroup)
                    <div class="grandchild-group">
                        <div class="grandchild-group__title">
                            {{ $familyGroup['label'] }}
                            @if ($familyGroup['is_unmapped'])
                            <span class="family-badge family-badge--warning">{{ trans('app.family_branch_unmapped') }}</span>
                            @endif
                        </div>
                        <ol style="padding-left: 15px">
                            @foreach($familyGroup['children'] as $childCard)
                            <li style="margin-top: 10px;">
                                {{ $childCard['user']->profileLink('chart') }} ({{ $childCard['user']->gender }})
                                @if ($childCard['spouse_labels']->isNotEmpty())
                                <div class="family-member-card__meta">
                                    @foreach ($childCard['spouse_labels'] as $spouseLabel)
                                    <span class="family-chip">{{ $spouseLabel->profileLink('chart') }} ({{ $spouseLabel->gender }})</span>
                                    @endforeach
                                </div>
                                @endif
                                @if ($childCard['grandchild_groups']->isNotEmpty())
                                <ul style="padding-left: 18px">
                                    @foreach($childCard['grandchild_groups'] as $grandGroup)
                                        @foreach($grandGroup['children'] as $grandchildCard)
                                        <li>{{ $grandchildCard['user']->profileLink('chart') }} ({{ $grandchildCard['user']->gender }})</li>
                                        @endforeach
                                    @endforeach
                                </ul>
                                @endif
                            </li>
                            @endforeach
                        </ol>
                    </div>
                    @empty
                    <div class="text-muted">{{ trans('app.childs_were_not_recorded') }}</div>
                    @endforelse
                </td>
            </tr>
        </tbody>
    </table>
</div>
