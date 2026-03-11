<?php

namespace App\Http\Controllers;

use App\Couple;
use App\Http\Requests\Users\UpdateRequest;
use App\Jobs\Users\DeleteAndReplaceUser;
use App\Services\CemeteryLocationOptions;
use App\Support\FamilyViewBuilder;
use App\User;
use App\UserMetadata;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Ramsey\Uuid\Uuid;
use Storage;

class UsersController extends Controller
{
    public function __construct(
        private FamilyViewBuilder $familyViewBuilder,
        private CemeteryLocationOptions $cemeteryLocationOptions
    )
    {
    }

    /**
     * Search user by keyword.
     *
     * @return \Illuminate\View\View
     */
    public function search(Request $request)
    {
        $q = $request->get('q');
        $users = [];

        if ($q) {
            $users = $this->searchUsersQuery($q)
                ->with('father', 'mother')
                ->orderBy('name', 'asc')
                ->paginate(24);
        }

        return view('users.search', compact('users'));
    }

    /**
     * Display the specified User.
     *
     * @param  \App\User  $user
     * @return \Illuminate\View\View
     */
    public function show(User $user)
    {
        $usersMariageList = $this->getUserMariageList($user);
        $allMariageList = $this->getAllMariageList();
        $malePersonList = $this->getPersonList(1);
        $femalePersonList = $this->getPersonList(2);

        return view('users.show', [
            'user' => $user,
            'usersMariageList' => $usersMariageList,
            'malePersonList' => $malePersonList,
            'femalePersonList' => $femalePersonList,
            'allMariageList' => $allMariageList,
        ]);
    }

    /**
     * Display the user's family chart.
     *
     * @param  \App\User  $user
     * @return \Illuminate\View\View
     */
    public function chart(User $user)
    {
        $user = $this->familyViewBuilder->loadChartRelations($user);
        $father = $user->father_id ? $user->father : null;
        $mother = $user->mother_id ? $user->mother : null;

        $fatherGrandpa = $father && $father->father_id ? $father->father : null;
        $fatherGrandma = $father && $father->mother_id ? $father->mother : null;

        $motherGrandpa = $mother && $mother->father_id ? $mother->father : null;
        $motherGrandma = $mother && $mother->mother_id ? $mother->mother : null;

        $childs = $user->childs;
        $colspan = $childs->count();
        $colspan = $colspan < 4 ? 4 : $colspan;

        $siblings = $user->siblings();
        $chartData = $this->familyViewBuilder->buildChartData($user);

        $publicView = true;
        $allowPublicEditSuggestions = auth()->guest();
        $canClaim = !$user->email;

        return view('users.public-chart', compact(
            'user', 'childs', 'father', 'mother', 'fatherGrandpa',
            'fatherGrandma', 'motherGrandpa', 'motherGrandma',
            'siblings', 'colspan', 'publicView', 'allowPublicEditSuggestions', 'canClaim'
        ) + $chartData);
    }

    public function autocomplete(Request $request)
    {
        $q = trim((string) $request->get('q'));

        if ($q === '') {
            return response()->json([]);
        }

        $users = $this->searchUsersQuery($q)
            ->with('father', 'mother')
            ->orderBy('name', 'asc')
            ->limit(10)
            ->get()
            ->map(function (User $user) {
                return [
                    'id' => $user->id,
                    'name' => $user->display_name,
                    'nickname' => $user->nickname,
                    'gender' => $user->gender,
                    'chart_url' => route('users.chart', $user, false),
                    'parents' => trim(collect([
                        $user->father ? trans('user.father').': '.$user->father->display_name : null,
                        $user->mother ? trans('user.mother').': '.$user->mother->display_name : null,
                    ])->filter()->implode(' | ')),
                ];
            })
            ->values();

        return response()->json($users);
    }

    /**
     * Show user family tree.
     *
     * @param  \App\User  $user
     * @return \Illuminate\View\View
     */
    public function tree(User $user)
    {
        $user = $this->familyViewBuilder->loadTreeRelations($user);
        $treeData = $this->familyViewBuilder->buildTreeData($user);

        return view('users.tree', compact('user') + $treeData);
    }

    /**
     * Show user death info.
     *
     * @param  \App\User  $user
     * @return \Illuminate\View\View
     */
    public function death(User $user)
    {
        $mapZoomLevel = config('leaflet.detail_zoom_level');
        $mapCenterLatitude = $user->getMetadata('cemetery_location_latitude');
        $mapCenterLongitude = $user->getMetadata('cemetery_location_longitude');

        return view('users.death', compact('user', 'mapZoomLevel', 'mapCenterLatitude', 'mapCenterLongitude'));
    }

    /**
     * Show the form for editing the specified User.
     *
     * @param  \App\User  $user
     * @return \Illuminate\View\View
     */
    public function edit(User $user)
    {
        $this->authorize('edit', $user);

        $replacementUsers = [];
        if (request('action') == 'delete') {
            $replacementUsers = $this->getPersonList($user->gender_id);
        }

        $validTabs = ['death', 'contact_address', 'login_account'];

        $mapZoomLevel = config('leaflet.zoom_level');
        $mapCenterLatitude = $user->getMetadata('cemetery_location_latitude');
        $mapCenterLongitude = $user->getMetadata('cemetery_location_longitude');
        if ($mapCenterLatitude && $mapCenterLongitude) {
            $mapZoomLevel = config('leaflet.detail_zoom_level');
        }
        $mapCenterLatitude = $mapCenterLatitude ?: config('leaflet.map_center_latitude');
        $mapCenterLongitude = $mapCenterLongitude ?: config('leaflet.map_center_longitude');

        return view('users.edit', array_merge(
            compact(
                'user',
                'replacementUsers',
                'validTabs',
                'mapZoomLevel',
                'mapCenterLatitude',
                'mapCenterLongitude'
            ),
            ['cemeteryLocationOptions' => $this->cemeteryLocationOptions->all()]
        ));
    }

    /**
     * Update the specified User in storage.
     *
     * @param  \App\Http\Requests\Users\UpdateRequest  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(UpdateRequest $request, User $user)
    {
        $userAttributes = $request->validated();
        $user->update($userAttributes);
        $userAttributes = collect($userAttributes);

        $this->updateUserMetadata($user, $userAttributes);

        return redirect()->route('users.show', $user->id);
    }

    /**
     * Remove the specified User from storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy(Request $request, User $user)
    {
        $this->authorize('delete', $user);

        if ($request->has('replace_delete_button')) {
            $attributes = $request->validate([
                'replacement_user_id' => 'required|exists:users,id',
            ], [
                'replacement_user_id.required' => __('validation.user.replacement_user_id.required'),
            ]);

            $this->dispatchNow(new DeleteAndReplaceUser($user, $attributes['replacement_user_id']));

            return redirect()->route('users.show', $attributes['replacement_user_id']);
        }

        $request->validate([
            'user_id' => 'required',
        ]);

        if ($request->get('user_id') == $user->id && $user->delete()) {
            return redirect()->route('users.search');
        }

        return back();
    }

    /**
     * Upload users photo.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\User  $user
     * @return \Illuminate\Http\RedirectResponse
     */
    public function photoUpload(Request $request, User $user)
    {
        $request->validate([
            'photo' => 'required|image|max:10000',
        ]);

        if (Storage::exists($user->photo_path)) {
            Storage::delete($user->photo_path);
        }

        $user->photo_path = $this->storeOptimizedSquarePhoto($request->file('photo'));
        $user->save();

        return back();
    }

    /**
     * Get User list based on gender.
     *
     * @param int $genderId
     *
     * @return \Illuminate\Support\Collection
     */
    private function getPersonList(int $genderId)
    {
        return User::query()
            ->where('gender_id', $genderId)
            ->orderBy('nickname')
            ->get(['id', 'nickname', 'name'])
            ->mapWithKeys(function (User $user) {
                $label = $user->nickname;

                if ($user->display_name && $user->display_name !== $user->nickname) {
                    $label .= ' - '.$user->display_name;
                }

                return [$user->id => $label];
            });
    }

    /**
     * Get marriage list of a user.
     *
     * @param \App\User $user
     *
     * @return array
     */
    private function getUserMariageList(User $user)
    {
        $usersMariageList = [];

        foreach ($user->couples as $spouse) {
            $usersMariageList[$spouse->pivot->id] = $user->display_name.' & '.$spouse->display_name;
        }

        return $usersMariageList;
    }

    /**
     * Get all marriage list.
     *
     * @return array
     */
    private function getAllMariageList()
    {
        $allMariageList = [];

        foreach (Couple::with('husband', 'wife')->get() as $couple) {
            $allMariageList[$couple->id] = $couple->husband->display_name.' & '.$couple->wife->display_name;
        }

        return $allMariageList;
    }

    private function updateUserMetadata(User $user, Collection $userAttributes)
    {
        foreach (User::METADATA_KEYS as $key) {
            if ($userAttributes->has($key) == false) {
                continue;
            }
            $userMeta = UserMetadata::firstOrNew(['user_id' => $user->id, 'key' => $key]);
            if (!$userMeta->exists) {
                $userMeta->id = Uuid::uuid4()->toString();
            }
            $userMeta->value = $userAttributes->get($key);
            $userMeta->save();
        }
    }

    private function searchUsersQuery(string $q)
    {
        return User::query()->where(function ($query) use ($q) {
            $query->where('name', 'like', '%'.$q.'%')
                ->orWhere('nickname', 'like', '%'.$q.'%');
        });
    }

    private function storeOptimizedSquarePhoto($uploadedPhoto)
    {
        $directory = storage_path('app/public/images');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $fileName = (string) Str::uuid().'.jpg';
        $relativePath = 'images/'.$fileName;
        $absolutePath = $directory.DIRECTORY_SEPARATOR.$fileName;

        $canvasSize = 800;
        $quality = 85;
        $encoded = null;

        do {
            $processed = Image::read($uploadedPhoto->getRealPath())->cover($canvasSize, $canvasSize);
            $encoded = $processed->toJpeg($quality);

            if ($encoded->size() <= 200 * 1024) {
                break;
            }

            if ($quality > 45) {
                $quality -= 5;
                continue;
            }

            $canvasSize -= 100;
        } while ($canvasSize >= 300);

        $encoded->save($absolutePath);

        return $relativePath;
    }
}
