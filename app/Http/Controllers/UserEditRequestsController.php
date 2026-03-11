<?php

namespace App\Http\Controllers;

use App\Services\ApplyUserEditRequest;
use App\User;
use App\UserEditRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class UserEditRequestsController extends Controller
{
    public function __construct(private ApplyUserEditRequest $applyUserEditRequest)
    {
        $this->middleware(['auth', 'admin']);
    }

    public function index(Request $request)
    {
        $requests = UserEditRequest::with(['targetUser', 'reviewer'])
            ->when($request->filled('status'), function ($query) use ($request) {
                $query->where('status', $request->get('status'));
            })
            ->when($request->filled('target'), function ($query) use ($request) {
                $query->whereHas('targetUser', function ($userQuery) use ($request) {
                    $target = trim((string) $request->get('target'));
                    $userQuery->where('name', 'like', '%'.$target.'%')
                        ->orWhere('nickname', 'like', '%'.$target.'%');
                });
            })
            ->when($request->filled('requester'), function ($query) use ($request) {
                $query->where('requester_name', 'like', '%'.trim((string) $request->get('requester')).'%');
            })
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->orderByDesc('submitted_at')
            ->paginate(20)
            ->appends($request->query());

        return view('user-edit-requests.index', compact('requests'));
    }

    public function show(UserEditRequest $userEditRequest)
    {
        $userEditRequest->loadMissing(['targetUser.metadata', 'targetUser.couples', 'reviewer']);

        return view('user-edit-requests.partials.detail', [
            'item' => $userEditRequest,
            'profileDiffs' => $this->profileDiffs($userEditRequest),
            'metadataDiffs' => $this->metadataDiffs($userEditRequest),
            'newSpouses' => collect($userEditRequest->proposed_new_spouses ?? []),
            'newChildren' => collect($userEditRequest->proposed_new_children ?? [])->map(function ($child) use ($userEditRequest) {
                $child['spouse_context_label'] = $this->spouseContextLabel($userEditRequest->targetUser, $child['spouse_context'] ?? 'none', $userEditRequest->proposed_new_spouses ?? []);
                return $child;
            }),
        ]);
    }

    public function update(Request $request, UserEditRequest $userEditRequest)
    {
        $validated = $request->validate([
            'action' => 'required|in:approve,reject',
            'review_notes' => 'nullable|string|max:2000',
        ]);

        if (!$userEditRequest->isPending()) {
            return back()->with('status', 'Usulan ini sudah ditinjau sebelumnya.');
        }

        if ($validated['action'] === 'approve') {
            try {
                $this->applyUserEditRequest->handle($userEditRequest, auth()->user(), $validated['review_notes'] ?? null);
            } catch (\RuntimeException $exception) {
                return back()->withErrors([
                    'review_notes' => $exception->getMessage(),
                ]);
            }

            return back()->with('status', 'Usulan perubahan berhasil disetujui.');
        }

        $userEditRequest->update([
            'status' => UserEditRequest::STATUS_REJECTED,
            'reviewed_at' => Carbon::now(),
            'reviewed_by' => auth()->id(),
            'review_notes' => $validated['review_notes'] ?? null,
        ]);

        return back()->with('status', 'Usulan perubahan ditolak.');
    }

    private function profileDiffs(UserEditRequest $editRequest): Collection
    {
        $fieldLabels = [
            'name' => 'Nama',
            'nickname' => 'Nama panggilan',
            'gender_id' => 'Jenis kelamin',
            'birth_order' => 'Urutan lahir',
            'dob' => 'Tanggal lahir',
            'yob' => 'Tahun lahir',
            'dod' => 'Tanggal wafat',
            'yod' => 'Tahun wafat',
            'phone' => 'Telepon',
            'address' => 'Alamat',
            'city' => 'Kota',
            'email' => 'Email',
        ];

        return collect($fieldLabels)->map(function ($label, $field) use ($editRequest) {
            if (!array_key_exists($field, $editRequest->proposed_profile ?? [])) {
                return null;
            }

            return [
                'label' => $label,
                'current' => $this->formatFieldValue($field, $editRequest->targetUser->{$field}),
                'proposed' => $this->formatFieldValue($field, $editRequest->proposed_profile[$field]),
            ];
        })->filter()->values();
    }

    private function metadataDiffs(UserEditRequest $editRequest): Collection
    {
        $fieldLabels = [
            'cemetery_location_name' => 'Nama lokasi makam',
            'cemetery_location_address' => 'Alamat makam',
            'cemetery_location_latitude' => 'Latitude makam',
            'cemetery_location_longitude' => 'Longitude makam',
        ];

        return collect($fieldLabels)->map(function ($label, $field) use ($editRequest) {
            if (!array_key_exists($field, $editRequest->proposed_metadata ?? [])) {
                return null;
            }

            return [
                'label' => $label,
                'current' => $editRequest->targetUser->getMetadata($field) ?: '-',
                'proposed' => $editRequest->proposed_metadata[$field] ?: '-',
            ];
        })->filter()->values();
    }

    private function formatFieldValue(string $field, $value): string
    {
        if ($value === null || $value === '') {
            return '-';
        }

        if ($field === 'gender_id') {
            return (int) $value === 1 ? 'Laki-laki' : 'Perempuan';
        }

        return (string) $value;
    }

    private function spouseContextLabel(User $targetUser, string $context, array $newSpouses): string
    {
        if ($context === 'none') {
            return 'Tanpa pasangan tercatat';
        }

        if (str_starts_with($context, 'existing:')) {
            $coupleId = substr($context, 9);
            $spouse = $targetUser->couples->first(function (User $user) use ($coupleId) {
                return $user->pivot->id === $coupleId;
            });

            return $spouse ? 'Pasangan existing: '.$spouse->name : 'Pasangan existing';
        }

        if (str_starts_with($context, 'new:')) {
            $requestKey = substr($context, 4);
            $spouse = collect($newSpouses)->firstWhere('request_key', $requestKey);

            return $spouse ? 'Pasangan baru: '.$spouse['name'] : 'Pasangan baru';
        }

        return '-';
    }
}
