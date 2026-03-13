<?php

namespace App\Http\Controllers;

use App\Services\FamilyScopeResolver;
use App\User;

class HomeController extends Controller
{
    public function __construct(private FamilyScopeResolver $familyScopeResolver)
    {
    }

    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        $user = auth()->user()->fresh([
            'father',
            'mother',
            'parent.husband',
            'parent.wife',
            'childs',
            'wifes',
            'husbands',
            'couples',
        ]);

        $usersMariageList = [];
        foreach ($user->couples as $spouse) {
            $usersMariageList[$spouse->pivot->id] = $user->display_name.' & '.$spouse->display_name;
        }

        $malePersonList = $this->getPersonList(1);
        $femalePersonList = $this->getPersonList(2);

        return view('users.show', [
            'user' => $user,
            'usersMariageList' => $usersMariageList,
            'malePersonList' => $malePersonList,
            'femalePersonList' => $femalePersonList,
        ]);
    }

    private function getPersonList(int $genderId)
    {
        return $this->familyScopeResolver->applyToUserQuery(
            User::query()->where('gender_id', $genderId)
        )
            ->orderBy('nickname')
            ->get(['id', 'nickname', 'name'])
            ->mapWithKeys(function (User $user) {
                $label = $user->nickname;

                if ($user->name && $user->name !== $user->nickname) {
                    $label .= ' - '.$user->name;
                }

                return [$user->id => $label];
            });
    }
}
