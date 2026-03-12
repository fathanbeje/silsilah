<div class="panel panel-default table-responsive family-sibling-surface">
    <table class="table table-bordered table-striped">
        <tbody>
            <tr>
                <th style="width: 35%">{{ trans('user.siblings') }}</th>
                <th class="text-center family-sibling-surface__name">
                    @include('users.partials.chart-person-link', ['linkedUser' => $siblingCard['user']]) <span>({{ $siblingCard['user']->gender }})</span>
                    @if ($siblingCard['spouse_labels']->isNotEmpty())
                    <div class="family-member-card__meta">
                        <span class="text-muted">{{ trans('user.spouse') }}:</span>
                        @foreach ($siblingCard['spouse_labels'] as $spouseLabel)
                        <span class="family-chip">@include('users.partials.chart-person-link', ['linkedUser' => $spouseLabel]) <span>({{ $spouseLabel->gender }})</span></span>
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
                        <ol class="family-branch-list">
                            @foreach($familyGroup['children'] as $childCard)
                            <li class="family-branch-list__item">
                                @include('users.partials.chart-person-link', ['linkedUser' => $childCard['user']]) <span>({{ $childCard['user']->gender }})</span>
                                @if ($childCard['spouse_labels']->isNotEmpty())
                                <div class="family-member-card__meta">
                                    <span class="text-muted">{{ trans('user.spouse') }}:</span>
                                    @foreach ($childCard['spouse_labels'] as $spouseLabel)
                                    <span class="family-chip">@include('users.partials.chart-person-link', ['linkedUser' => $spouseLabel]) <span>({{ $spouseLabel->gender }})</span></span>
                                    @endforeach
                                </div>
                                @endif
                                @if ($childCard['grandchild_groups']->isNotEmpty())
                                <ul class="family-grandchild-list">
                                    @foreach($childCard['grandchild_groups'] as $grandGroup)
                                        @foreach($grandGroup['children'] as $grandchildCard)
                                        <li>@include('users.partials.chart-person-link', ['linkedUser' => $grandchildCard['user']]) <span>({{ $grandchildCard['user']->gender }})</span></li>
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
