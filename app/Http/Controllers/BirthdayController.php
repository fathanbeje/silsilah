<?php

namespace App\Http\Controllers;

use App\User;
use App\Services\FamilyScopeResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class BirthdayController extends Controller
{
    public function __construct(private FamilyScopeResolver $familyScopeResolver)
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index()
    {
        $users = $this->getUpcomingBirthdays();

        return view('birthdays.index', compact('users'));
    }

    private function getUpcomingBirthdays()
    {
        $birthdayDateRaw = "concat(YEAR(CURDATE()), '-', RIGHT(dob, 5)) as birthday_date";

        $userBirthdayQuery = $this->scopeUserQuery(User::query())
            ->whereNotNull('dob')
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

    private function scopeUserQuery(Builder $query): Builder
    {
        if (!$this->familyScopeResolver->hasActiveScope()) {
            return $query;
        }

        $visibleIds = $this->familyScopeResolver->visibleUserIds();

        if (empty($visibleIds)) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn('users.id', $visibleIds);
    }
}
