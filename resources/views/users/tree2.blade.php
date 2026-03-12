@extends('layouts.user-profile-wide')

@section('subtitle', trans('app.family_tree'))

@section('user-content')

<div class="tree">
    <ul>
        <li>
            {{ link_to_route('users.tree', $user->name, [$user->id], ['title' => $user->displayNameWithGender()]) }}
            @if ($user->childs->count())
            <ul>
                @foreach($user->childs as $child)
                <li>
                    {{ link_to_route('users.tree', $child->id, [$child->id], ['title' => $child->displayNameWithGender()]) }}
                    @if ($child->childs->count())
                    <ul>
                        @foreach($child->childs as $grand)
                        <li>
                            {{ link_to_route('users.tree', $grand->id, [$grand->id], ['title' => $grand->displayNameWithGender()]) }}
                            @if ($grand->childs->count())
                            <ul>
                                @foreach($grand->childs as $gg)
                                <li>
                                    {{ link_to_route('users.tree', $gg->id, [$gg->id], ['title' => $gg->displayNameWithGender()]) }}
                                    <?php /*
                                    @if ($gg->childs->count())
                                    <ul>
                                        @foreach($gg->childs as $ggc)
                                        <li>
                                            {{ link_to_route('users.tree', $ggc->id, [$ggc->id], ['title' => $ggc->displayNameWithGender()]) }}
                                        </li>
                                        @endforeach
                                    </ul>
                                    @endif
                                    */ ?>
                                </li>
                                @endforeach
                            </ul>
                            @endif
                        </li>
                        @endforeach
                    </ul>
                    @endif
                </li>
                @endforeach
            </ul>
            @endif
        </li>
    </ul>
    <div class="clearfix"></div>
</div>
@endsection

@section ('ext_css')
<link rel="stylesheet" href="{{ secure_asset('css/tree2.css') }}">
@endsection
