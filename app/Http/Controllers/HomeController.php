<?php

namespace App\Http\Controllers;

use App\User;

class HomeController extends Controller
{
    /**
     * Show the application dashboard.
     *
     * @return \Illuminate\Contracts\View\View
     */
    public function index()
    {
        $user = auth()->user();

        $usersMariageList = [];
        foreach ($user->couples as $spouse) {
            $usersMariageList[$spouse->pivot->id] = $user->name.' & '.$spouse->name;
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
        return User::query()
            ->where('gender_id', $genderId)
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
