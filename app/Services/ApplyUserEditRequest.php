<?php

namespace App\Services;

use App\Couple;
use App\User;
use App\UserEditRequest;
use App\UserMetadata;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Ramsey\Uuid\Uuid;

class ApplyUserEditRequest
{
    public function handle(UserEditRequest $editRequest, User $reviewer, ?string $reviewNotes = null): void
    {
        DB::transaction(function () use ($editRequest, $reviewer, $reviewNotes) {
            $targetUser = User::with(['metadata', 'couples'])
                ->lockForUpdate()
                ->findOrFail($editRequest->target_user_id);

            $this->applyPayload($targetUser, [
                'proposed_profile' => $editRequest->proposed_profile ?? [],
                'proposed_metadata' => $editRequest->proposed_metadata ?? [],
                'proposed_new_spouses' => $editRequest->proposed_new_spouses ?? [],
                'proposed_new_children' => $editRequest->proposed_new_children ?? [],
                'proposed_photo_path' => $editRequest->proposed_photo_path,
            ], $reviewer, $reviewNotes);

            $editRequest->update([
                'status' => UserEditRequest::STATUS_APPROVED,
                'reviewed_at' => Carbon::now(),
                'reviewed_by' => $reviewer->id,
                'review_notes' => $reviewNotes,
            ]);
        });
    }

    public function applyPayload(User $targetUser, array $payload, User $reviewer, ?string $reviewNotes = null): array
    {
        $createdUserIds = [];
        $createdCoupleIds = [];

        $proposedProfile = $payload['proposed_profile'] ?? [];
        if (!empty($proposedProfile['email']) && User::query()->where('email', $proposedProfile['email'])->where('id', '!=', $targetUser->id)->exists()) {
            throw new \RuntimeException('Email usulan sudah dipakai oleh anggota lain.');
        }

        if (!empty($proposedProfile)) {
            $targetUser->fill($proposedProfile);
        }

        if (!empty($payload['proposed_photo_path']) && Storage::disk('public')->exists($payload['proposed_photo_path'])) {
            if ($targetUser->photo_path && Storage::disk('public')->exists($targetUser->photo_path)) {
                Storage::disk('public')->delete($targetUser->photo_path);
            }

            $targetUser->photo_path = $this->moveStagedPhoto($payload['proposed_photo_path']);
        }

        $targetUser->save();

        foreach (($payload['proposed_metadata'] ?? []) as $key => $value) {
            $this->saveMetadata($targetUser, $key, $value);
        }

        $newCouplesByRequestKey = [];

        foreach (($payload['proposed_new_spouses'] ?? []) as $spouseData) {
            $spouse = $this->createSpouse($targetUser, $spouseData, $reviewer);
            $couple = $this->createCouple($targetUser, $spouse, $spouseData['marriage_date'] ?? null, $reviewer);
            $newCouplesByRequestKey[$spouseData['request_key']] = $couple;
            $createdUserIds[] = $spouse->id;
            $createdCoupleIds[] = $couple->id;
        }

        foreach (($payload['proposed_new_children'] ?? []) as $childData) {
            $child = $this->createChild($targetUser, $childData, $newCouplesByRequestKey, $reviewer);
            $createdUserIds[] = $child->id;
        }

        return [
            'created_user_ids' => array_values(array_filter($createdUserIds)),
            'created_couple_ids' => array_values(array_filter($createdCoupleIds)),
        ];
    }

    private function moveStagedPhoto(string $stagedPath): string
    {
        $extension = pathinfo($stagedPath, PATHINFO_EXTENSION) ?: 'jpg';
        $livePath = 'images/'.Uuid::uuid4()->toString().'.'.$extension;

        Storage::disk('public')->copy($stagedPath, $livePath);
        Storage::disk('public')->delete($stagedPath);

        return $livePath;
    }

    private function saveMetadata(User $user, string $key, $value): void
    {
        $userMeta = UserMetadata::firstOrNew(['user_id' => $user->id, 'key' => $key]);

        if (!$userMeta->exists) {
            $userMeta->id = Uuid::uuid4()->toString();
        }

        $userMeta->value = $value;
        $userMeta->save();
    }

    private function createSpouse(User $targetUser, array $spouseData, User $reviewer): User
    {
        $spouse = new User();
        $spouse->id = Uuid::uuid4()->toString();
        $spouse->name = $spouseData['name'] ?? null;
        $spouse->nickname = $spouseData['nickname'] ?? ($spouseData['name'] ?? null);
        $spouse->gender_id = $targetUser->gender_id == 1 ? 2 : 1;
        $spouse->dob = $spouseData['dob'] ?? null;
        $spouse->yob = $spouseData['yob'] ?? null;
        $spouse->phone = $spouseData['phone'] ?? null;
        $spouse->address = $spouseData['address'] ?? null;
        $spouse->city = $spouseData['city'] ?? null;
        $spouse->dod = $spouseData['dod'] ?? null;
        $spouse->yod = $spouseData['yod'] ?? null;
        $spouse->is_deceased = !empty($spouseData['is_deceased']);
        $spouse->manager_id = $reviewer->id;
        $spouse->save();
        foreach (($spouseData['metadata'] ?? []) as $key => $value) {
            $this->saveMetadata($spouse, $key, $value);
        }

        return $spouse;
    }

    private function createCouple(User $targetUser, User $spouse, ?string $marriageDate, User $reviewer): Couple
    {
        $couple = new Couple();
        $couple->id = Uuid::uuid4()->toString();
        $couple->husband_id = $targetUser->gender_id == 1 ? $targetUser->id : $spouse->id;
        $couple->wife_id = $targetUser->gender_id == 2 ? $targetUser->id : $spouse->id;
        $couple->marriage_date = $marriageDate ?: null;
        $couple->spouse_order = $targetUser->nextSpouseOrder();
        $couple->manager_id = $reviewer->id;
        $couple->save();

        return $couple;
    }

    private function createChild(User $targetUser, array $childData, array $newCouplesByRequestKey, User $reviewer): User
    {
        $child = new User();
        $child->id = Uuid::uuid4()->toString();
        $child->name = $childData['name'] ?? null;
        $child->nickname = $childData['nickname'] ?? ($childData['name'] ?? null);
        $child->gender_id = (int) ($childData['gender_id'] ?? 1);
        $child->birth_order = $childData['birth_order'] ?: null;
        $child->dob = $childData['dob'] ?? null;
        $child->yob = $childData['yob'] ?? null;
        $child->phone = $childData['phone'] ?? null;
        $child->address = $childData['address'] ?? null;
        $child->city = $childData['city'] ?? null;
        $child->dod = $childData['dod'] ?? null;
        $child->yod = $childData['yod'] ?? null;
        $child->is_deceased = !empty($childData['is_deceased']);
        $child->manager_id = $reviewer->id;

        $context = $childData['spouse_context'] ?? 'none';

        if (str_starts_with($context, 'existing:')) {
            $coupleId = substr($context, 9);
            $couple = Couple::find($coupleId);
            if ($couple) {
                $child->parent_id = $couple->id;
                $child->father_id = $couple->husband_id;
                $child->mother_id = $couple->wife_id;
            }
        } elseif (str_starts_with($context, 'new:')) {
            $requestKey = substr($context, 4);
            $couple = $newCouplesByRequestKey[$requestKey] ?? null;
            if ($couple) {
                $child->parent_id = $couple->id;
                $child->father_id = $couple->husband_id;
                $child->mother_id = $couple->wife_id;
            }
        } else {
            if ($targetUser->gender_id == 1) {
                $child->father_id = $targetUser->id;
            } else {
                $child->mother_id = $targetUser->id;
            }
        }

        $child->save();

        foreach (($childData['metadata'] ?? []) as $key => $value) {
            $this->saveMetadata($child, $key, $value);
        }

        return $child;
    }
}
