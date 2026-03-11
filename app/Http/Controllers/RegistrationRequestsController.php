<?php

namespace App\Http\Controllers;

use App\RegistrationRequest;
use App\Services\FamilyScopeResolver;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class RegistrationRequestsController extends Controller
{
    public function __construct(private FamilyScopeResolver $familyScopeResolver)
    {
        $this->middleware('guest')->only('store');
        $this->middleware(['auth', 'admin'])->except('store');
    }

    public function index()
    {
        $requests = RegistrationRequest::with(['user', 'reviewer'])
            ->orderByRaw("case when status = 'pending' then 0 else 1 end")
            ->latest()
            ->paginate(20);

        return view('registration-requests.index', compact('requests'));
    }

    public function store(Request $request, User $user)
    {
        $this->abortIfUserOutsideScope($user);

        if ($user->email) {
            return back()->withErrors([
                'request_email' => trans('auth.claim_unavailable'),
            ]);
        }

        $validated = $request->validate([
            'request_email' => 'required|string|email|max:255',
            'requested_birth_date' => 'nullable|date',
            'notes' => 'nullable|string|max:1000',
        ]);

        $registrationRequest = RegistrationRequest::firstOrNew([
            'user_id' => $user->id,
            'email' => strtolower($validated['request_email']),
            'status' => RegistrationRequest::STATUS_PENDING,
        ]);

        $registrationRequest->fill([
            'name' => $user->name,
            'requested_birth_date' => $validated['requested_birth_date'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ])->save();

        return back()->with('status', trans('auth.registration_request_sent'));
    }

    public function update(Request $request, RegistrationRequest $registrationRequest)
    {
        $validated = $request->validate([
            'status' => 'required|in:'.implode(',', [
                RegistrationRequest::STATUS_PENDING,
                RegistrationRequest::STATUS_REVIEWED,
                RegistrationRequest::STATUS_REJECTED,
            ]),
        ]);

        $registrationRequest->update([
            'status' => $validated['status'],
            'reviewed_at' => $validated['status'] === RegistrationRequest::STATUS_PENDING ? null : Carbon::now(),
            'reviewed_by' => $validated['status'] === RegistrationRequest::STATUS_PENDING ? null : auth()->id(),
        ]);

        return back()->with('status', trans('auth.registration_request_updated'));
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
}
