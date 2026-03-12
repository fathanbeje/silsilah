<div class="panel panel-default table-responsive family-chart-surface">
    @if (!empty($rootSpouseLabels) && $rootSpouseLabels->isNotEmpty())
    <div class="panel-body family-summary">
        <span class="family-summary__label">{{ trans('user.spouse') }}</span>
        @foreach ($rootSpouseLabels as $spouseLabel)
        <span class="family-chip">@include('users.partials.chart-person-link', ['linkedUser' => $spouseLabel]) <span>({{ $spouseLabel->gender }})</span></span>
        @endforeach
    </div>
    @endif
    <table class="table table-bordered table-striped">
        <tbody>
            <tr>
                <th style="width: 9%">{{ trans('user.grand_father') }} & {{ trans('user.grand_mother') }}</th>
                <td class="text-center">
                    @if ($fatherGrandpa)
                        @include('users.partials.chart-person-link', ['linkedUser' => $fatherGrandpa])
                    @else
                        ?
                    @endif
                </td>
                <td class="text-center">
                    @if ($fatherGrandma)
                        @include('users.partials.chart-person-link', ['linkedUser' => $fatherGrandma])
                    @else
                        ?
                    @endif
                </td>
                <td class="text-center">
                    @if ($motherGrandpa)
                        @include('users.partials.chart-person-link', ['linkedUser' => $motherGrandpa])
                    @else
                        ?
                    @endif
                </td>
                <td class="text-center">
                    @if ($motherGrandma)
                        @include('users.partials.chart-person-link', ['linkedUser' => $motherGrandma])
                    @else
                        ?
                    @endif
                </td>
            </tr>
            <tr>
                <th>{{ trans('user.father') }} & {{ trans('user.mother') }}</th>
                <td class="text-center" colspan="2">
                    @if ($father)
                        @include('users.partials.chart-person-link', ['linkedUser' => $father])
                    @else
                        ?
                    @endif
                </td>
                <td class="text-center" colspan="2">
                    @if ($mother)
                        @include('users.partials.chart-person-link', ['linkedUser' => $mother])
                    @else
                        ?
                    @endif
                </td>
            </tr>
            <tr>
                <th>&nbsp;</th>
                <td class="text-center lead" colspan="4">
                    <strong>@include('users.partials.chart-person-link', ['linkedUser' => $user]) <span>({{ $user->gender }})</span></strong>
                </td>
            </tr>
            <tr>
                <th>{{ trans('user.childs') }} & {{ trans('user.grand_childs') }}</th>
                <td colspan="4">
                    @foreach ($familyGroups as $familyGroup)
                    <div class="family-group">
                        <div class="family-group__header">
                            <strong>{{ $familyGroup['label'] }}</strong>
                            @if ($familyGroup['is_unmapped'])
                            <span class="family-badge family-badge--warning">{{ trans('app.family_branch_unmapped') }}</span>
                            @endif
                        </div>

                        @if ($familyGroup['children']->isEmpty())
                        <div class="text-muted">{{ trans('app.childs_were_not_recorded') }}</div>
                        @else
                        <div class="row">
                            @foreach ($familyGroup['children'] as $childCard)
                            <div class="col-md-4 col-sm-6">
                                <div class="family-member-card">
                                    <div class="family-member-card__title">
                                        <strong>@include('users.partials.chart-person-link', ['linkedUser' => $childCard['user']]) <span>({{ $childCard['user']->gender }})</span></strong>
                                    </div>
                                    @if ($childCard['spouse_labels']->isNotEmpty())
                                    <div class="family-member-card__meta">
                                        <span class="text-muted">{{ trans('user.spouse') }}:</span>
                                        @foreach ($childCard['spouse_labels'] as $spouseLabel)
                                        <span class="family-chip">@include('users.partials.chart-person-link', ['linkedUser' => $spouseLabel]) <span>({{ $spouseLabel->gender }})</span></span>
                                        @endforeach
                                    </div>
                                    @endif

                                    @if ($childCard['grandchild_groups']->isEmpty())
                                    <div class="text-muted">{{ trans('app.childs_were_not_recorded') }}</div>
                                    @else
                                        @foreach ($childCard['grandchild_groups'] as $grandchildGroup)
                                        <div class="grandchild-group">
                                            @if ($grandchildGroup['spouse'])
                                            <div class="grandchild-group__title">
                                                {{ trans('user.spouse') }}:
                                                <span class="family-chip">@include('users.partials.chart-person-link', ['linkedUser' => $grandchildGroup['spouse']]) <span>({{ $grandchildGroup['spouse']->gender }})</span></span>
                                            </div>
                                            @elseif ($grandchildGroup['is_unmapped'])
                                            <div class="grandchild-group__title text-muted">{{ trans('app.family_branch_unmapped') }}</div>
                                            @endif
                                            <ul class="grandchild-group__list">
                                                @foreach ($grandchildGroup['children'] as $grandchildCard)
                                                <li>@include('users.partials.chart-person-link', ['linkedUser' => $grandchildCard['user']]) <span>({{ $grandchildCard['user']->gender }})</span></li>
                                                @endforeach
                                            </ul>
                                        </div>
                                        @endforeach
                                    @endif
                                </div>
                            </div>
                            @endforeach
                        </div>
                        @endif
                    </div>
                    @endforeach
                </td>
            </tr>
        </tbody>
    </table>
</div>

<h4 class="page-header family-section-title">
    {{ trans('user.siblings') }}, {{ trans('user.nieces') }}, & {{ trans('user.grand_childs') }}
</h4>
@foreach ($siblingFamilyCards->chunk(3) as $chunkedSiblingCards)
<div class="row family-sibling-grid">
    @foreach ($chunkedSiblingCards as $siblingCard)
    <div class="col-sm-4">
        @include('users.partials.chart-sibling', ['siblingCard' => $siblingCard])
    </div>
    @endforeach
</div>
@endforeach
