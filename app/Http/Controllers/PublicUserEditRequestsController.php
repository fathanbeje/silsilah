<?php

namespace App\Http\Controllers;

use App\Services\CemeteryLocationOptions;
use App\Services\FamilyScopeResolver;
use App\User;
use App\UserEditRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Ramsey\Uuid\Uuid;

class PublicUserEditRequestsController extends Controller
{
    private const ALLOWED_PHOTO_MIME_TYPES = [
        'image/jpeg',
        'image/png',
        'image/webp',
    ];

    public function __construct(
        private CemeteryLocationOptions $cemeteryLocationOptions,
        private FamilyScopeResolver $familyScopeResolver
    )
    {
    }

    public function create(User $user)
    {
        $this->abortIfUserCannotSubmit();
        $this->abortIfUserOutsideScope($user);
        $user->loadMissing(['couples', 'metadata']);

        return view('user-edit-requests.partials.public-form', [
            'user' => $user,
            'existingSpouseOptions' => $user->couples->map(function (User $spouse) {
                return [
                    'value' => 'existing:'.$spouse->pivot->id,
                    'label' => $spouse->display_name,
                ];
            })->values(),
            'cemeteryLocationOptions' => $this->cemeteryLocationOptions->all(),
        ]);
    }

    public function store(Request $request, User $user)
    {
        $this->abortIfUserCannotSubmit();
        $this->abortIfUserOutsideScope($user);

        $validator = Validator::make($request->all(), [
            'requester_name' => 'required|string|max:255',
            'requester_whatsapp' => 'required|string|max:50',
            'name' => 'nullable|string|max:255',
            'nickname' => 'required|string|max:255',
            'gender_id' => 'required|in:1,2',
            'is_deceased' => 'nullable|boolean',
            'birth_order' => 'nullable|numeric|min:1',
            'dob' => 'nullable|date|date_format:Y-m-d',
            'yob' => 'nullable|date_format:Y',
            'dod' => 'nullable|date|date_format:Y-m-d',
            'yod' => 'nullable|date_format:Y',
            'phone' => 'nullable|string|max:255',
            'address' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255|unique:users,email,'.$user->id.',id',
            'cemetery_location_name' => 'nullable|string|max:255',
            'cemetery_location_address' => 'nullable|string|max:255',
            'cemetery_location_latitude' => 'nullable|string|max:255',
            'cemetery_location_longitude' => 'nullable|string|max:255',
            'photo' => 'nullable|file|mimes:jpg,jpeg,png,webp|mimetypes:image/jpeg,image/png,image/webp|max:10000|dimensions:min_width=100,min_height=100,max_width=8000,max_height=8000',
            'new_spouses' => 'array',
            'new_spouses.*.request_key' => 'nullable|string|max:80',
            'new_spouses.*.name' => 'nullable|string|max:255',
            'new_spouses.*.nickname' => 'nullable|string|max:255',
            'new_spouses.*.dob' => 'nullable|date|date_format:Y-m-d',
            'new_spouses.*.yob' => 'nullable|date_format:Y',
            'new_spouses.*.marriage_date' => 'nullable|date|date_format:Y-m-d',
            'new_children' => 'array',
            'new_children.*.name' => 'nullable|string|max:255',
            'new_children.*.nickname' => 'nullable|string|max:255',
            'new_children.*.gender_id' => 'nullable|in:1,2',
            'new_children.*.birth_order' => 'nullable|numeric|min:1',
            'new_children.*.dob' => 'nullable|date|date_format:Y-m-d',
            'new_children.*.yob' => 'nullable|date_format:Y',
            'new_children.*.spouse_context' => 'nullable|string|max:80',
        ]);

        if ($validator->fails()) {
            return $this->validationFailureResponse($request, $validator->errors()->toArray());
        }

        $validated = $validator->validated();
        $validated['is_deceased'] = !empty($validated['dod']) || !empty($validated['yod']) || !empty($validated['is_deceased']);

        $user->loadMissing(['metadata', 'couples']);

        try {
            $proposedProfile = $this->extractChangedProfile($user, $validated);
            $proposedMetadata = $this->extractChangedMetadata($user, $validated);
            $proposedSpouses = $this->extractNewSpouses($validated['new_spouses'] ?? []);
            $proposedChildren = $this->extractNewChildren($validated['new_children'] ?? [], $proposedSpouses, $user);
        } catch (\Illuminate\Validation\ValidationException $exception) {
            return $this->validationFailureResponse($request, $exception->errors());
        }

        $proposedPhotoPath = $request->hasFile('photo')
            ? $this->storeStagedPhoto($request->file('photo'))
            : null;

        if (
            empty($proposedProfile) &&
            empty($proposedMetadata) &&
            empty($proposedSpouses) &&
            empty($proposedChildren) &&
            !$proposedPhotoPath
        ) {
            return $this->validationFailureResponse($request, [
                'requester_name' => ['Belum ada perubahan yang diajukan.'],
            ]);
        }

        $editRequest = new UserEditRequest();
        $editRequest->id = Uuid::uuid4()->toString();
        $editRequest->target_user_id = $user->id;
        $editRequest->requester_name = trim($validated['requester_name']);
        $editRequest->requester_whatsapp = trim($validated['requester_whatsapp']);
        $editRequest->status = UserEditRequest::STATUS_PENDING;
        $editRequest->submitted_at = Carbon::now();
        $editRequest->proposed_profile = $proposedProfile;
        $editRequest->proposed_metadata = $proposedMetadata;
        $editRequest->proposed_new_spouses = $proposedSpouses;
        $editRequest->proposed_new_children = $proposedChildren;
        $editRequest->proposed_photo_path = $proposedPhotoPath;
        $editRequest->save();

        if ($request->ajax()) {
            return response()->json([
                'message' => 'Usulan perubahan berhasil dikirim dan menunggu peninjauan admin.',
            ]);
        }

        return redirect()->route('users.chart', $user)
            ->with('status', 'Usulan perubahan berhasil dikirim dan menunggu peninjauan admin.');
    }

    private function extractChangedProfile(User $user, array $validated): array
    {
        $fields = [
            'name',
            'nickname',
            'gender_id',
            'birth_order',
            'dob',
            'yob',
            'dod',
            'yod',
            'is_deceased',
            'phone',
            'address',
            'city',
            'email',
        ];

        $proposed = [];

        foreach ($fields as $field) {
            $incoming = $this->normalizeValue($field, $validated[$field] ?? null);
            $current = $this->normalizeValue($field, $user->{$field});

            if ($incoming !== $current) {
                $proposed[$field] = $incoming;
            }
        }

        return $proposed;
    }

    private function extractChangedMetadata(User $user, array $validated): array
    {
        $proposed = [];

        foreach (User::METADATA_KEYS as $key) {
            $incoming = $this->normalizeValue($key, $validated[$key] ?? null);
            $current = $this->normalizeValue($key, $user->getMetadata($key));

            if ($incoming !== $current) {
                $proposed[$key] = $incoming;
            }
        }

        return $proposed;
    }

    private function extractNewSpouses(array $rows): array
    {
        return collect($rows)->map(function ($row, $index) {
            $name = $this->normalizeValue('name', $row['name'] ?? null);
            $nickname = $this->normalizeValue('nickname', $row['nickname'] ?? null) ?: $name;

            if (!$name) {
                return null;
            }

            return [
                'request_key' => $row['request_key'] ?? 'spouse_'.$index,
                'name' => $name,
                'nickname' => $nickname,
                'dob' => $this->normalizeValue('dob', $row['dob'] ?? null),
                'yob' => $this->normalizeValue('yob', $row['yob'] ?? null),
                'marriage_date' => $this->normalizeValue('dob', $row['marriage_date'] ?? null),
            ];
        })->filter()->values()->all();
    }

    private function extractNewChildren(array $rows, array $proposedSpouses, User $user): array
    {
        $validNewSpouseKeys = collect($proposedSpouses)->pluck('request_key')->all();
        $validExistingContexts = $user->couples->pluck('pivot.id')->map(fn ($id) => 'existing:'.$id)->all();

        return collect($rows)->map(function ($row) use ($validNewSpouseKeys, $validExistingContexts) {
            $name = $this->normalizeValue('name', $row['name'] ?? null);
            $nickname = $this->normalizeValue('nickname', $row['nickname'] ?? null) ?: $name;

            if (!$name) {
                return null;
            }

            $spouseContext = trim((string) ($row['spouse_context'] ?? 'none'));
            if (
                $spouseContext !== 'none' &&
                !in_array($spouseContext, $validExistingContexts, true) &&
                !(str_starts_with($spouseContext, 'new:') && in_array(substr($spouseContext, 4), $validNewSpouseKeys, true))
            ) {
                throw \Illuminate\Validation\ValidationException::withMessages([
                    'new_children' => 'Pilihan pasangan untuk anak baru tidak valid.',
                ]);
            }

            return [
                'name' => $name,
                'nickname' => $nickname,
                'gender_id' => (int) ($row['gender_id'] ?? 0),
                'birth_order' => $this->normalizeValue('birth_order', $row['birth_order'] ?? null),
                'dob' => $this->normalizeValue('dob', $row['dob'] ?? null),
                'yob' => $this->normalizeValue('yob', $row['yob'] ?? null),
                'spouse_context' => $spouseContext ?: 'none',
            ];
        })->filter()->values()->tap(function ($children) {
            foreach ($children as $child) {
                if (empty($child['gender_id'])) {
                    throw \Illuminate\Validation\ValidationException::withMessages([
                        'new_children' => 'Jenis kelamin anak baru wajib dipilih.',
                    ]);
                }
            }
        })->all();
    }

    private function normalizeValue(string $field, $value)
    {
        if ($value instanceof \Illuminate\Support\Carbon) {
            return $value->format('Y-m-d');
        }

        if (is_string($value)) {
            $value = trim($value);
            $value = $value === '' ? null : $value;
        }

        if (in_array($field, ['name', 'nickname'], true)) {
            return User::normalizeUppercase($value);
        }

        if (in_array($field, ['gender_id', 'birth_order'], true)) {
            return $value === null ? null : (int) $value;
        }

        if ($field === 'is_deceased') {
            return !empty($value);
        }

        if (in_array($field, ['yob', 'yod'], true)) {
            return $value === null ? null : (string) $value;
        }

        return $value;
    }

    private function storeStagedPhoto($uploadedPhoto): string
    {
        $this->assertSafePhotoUpload($uploadedPhoto);

        $directory = storage_path('app/public/edit-requests');
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $fileName = (string) Str::uuid().'.jpg';
        $relativePath = 'edit-requests/'.$fileName;
        $absolutePath = $directory.DIRECTORY_SEPARATOR.$fileName;

        $canvasSize = 800;
        $quality = 85;

        $image = Image::read($uploadedPhoto->getRealPath())
            ->cover($canvasSize, $canvasSize, 'center')
            ->toJpeg($quality);

        while (strlen((string) $image) > 200 * 1024 && $quality > 45) {
            $quality -= 5;
            $image = Image::read($uploadedPhoto->getRealPath())
                ->cover($canvasSize, $canvasSize, 'center')
                ->toJpeg($quality);
        }

        file_put_contents($absolutePath, (string) $image);

        return $relativePath;
    }

    private function assertSafePhotoUpload(UploadedFile $uploadedPhoto): void
    {
        if (!in_array($uploadedPhoto->getMimeType(), self::ALLOWED_PHOTO_MIME_TYPES, true)) {
            abort(422, 'Jenis file foto tidak diizinkan.');
        }
    }

    private function validationFailureResponse(Request $request, array $errors)
    {
        if ($request->ajax()) {
            return response()->json([
                'message' => 'Data usulan belum valid.',
                'errors' => $errors,
            ], 422);
        }

        return back()->withErrors($errors)->withInput();
    }

    private function abortIfUserOutsideScope(User $user): void
    {
        if (!$this->familyScopeResolver->hasActiveScope()) {
            return;
        }

        if (!$this->familyScopeResolver->isVisibleUser($user)) {
            abort(404);
        }
    }

    private function abortIfUserCannotSubmit(): void
    {
        if (auth()->check() && is_system_admin(auth()->user())) {
            abort(403);
        }
    }
}
