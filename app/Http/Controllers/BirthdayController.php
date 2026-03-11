<?php

namespace App\Http\Controllers;

use App\User;
use Illuminate\Support\Facades\DB;

class BirthdayController extends Controller
{
    public function index()
    {
        $users = $this->getUpcomingBirthdays();

        return view('birthdays.index', compact('users'));
    }

    private function getUpcomingBirthdays()
    {
        $birthdayDateRaw = "concat(YEAR(CURDATE()), '-', RIGHT(dob, 5)) as birthday_date";

        $userBirthdayQuery = User::whereNotNull('dob')
            ->where(function ($query) {
                $query->whereNull('users.is_deceased')
                    ->orWhere('users.is_deceased', false);
            })
            ->select('users.name', 'users.dob', 'users.id as user_id', DB::raw($birthdayDateRaw))
            ->orderBy('birthday_date', 'asc')
            ->havingBetween('birthday_date', [today()->format('Y-m-d'), today()->addDays(60)->format('Y-m-d')]);

        $users = $userBirthdayQuery->get();

        return $users;
    }
}
