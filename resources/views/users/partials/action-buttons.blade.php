<div class="pull-right btn-group" role="group">
    @php($hasPublicFamilyScope = app(\App\Services\FamilyScopeResolver::class)->hasActiveScope())
    @can ('edit', $user)
    {{ link_to_route('users.edit', trans('app.edit'), [$user->id], ['class' => 'btn btn-warning']) }}
    @endcan
    @auth
    {{ link_to_route('users.show', trans('app.show_profile').' '.$user->display_name, [$user->id], ['class' => Request::segment(3) == null ? 'btn btn-default active' : 'btn btn-default']) }}
    @endauth
    {{ link_to_route('users.chart', trans('app.show_family_chart'), [$user->id], ['class' => Request::segment(3) == 'chart' ? 'btn btn-default active' : 'btn btn-default']) }}
    {{ link_to_route('users.tree', trans('app.show_family_tree'), [$user->id], ['class' => Request::segment(3) == 'tree' ? 'btn btn-default active' : 'btn btn-default']) }}
    @if ($hasPublicFamilyScope)
    {{ link_to_route('deaths.index', 'Database Wafat', [], ['class' => Request::routeIs('deaths.index') ? 'btn btn-default active' : 'btn btn-default']) }}
    @endif
    @auth
    {{ link_to_route('users.marriages', trans('app.show_marriages'), [$user->id], ['class' => Request::segment(3) == 'marriages' ? 'btn btn-default active' : 'btn btn-default']) }}
    @if ($user->hasDeathInfo())
        {{ link_to_route('users.death', trans('user.death'), [$user->id], ['class' => Request::segment(3) == 'death' ? 'btn btn-default active' : 'btn btn-default']) }}
    @endif
    @endauth
</div>
